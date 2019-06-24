<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;


class ModelsController extends Controller
{
    public function getModelsApproved()
    {
        //$products = auth()->user()->products;
        $models = DB::connection('mysql2')->table('models')->select('Modelname')->where('status','Approved')->get();

        return response()->json([
            'success' => true,
            'data' => $models
        ]);
    }

    public function getModelsPriority()
    {
        //$models = DB::connection('mysql2')->table('models')->select('id','Modelname','priority')->where('status','Approved')->get();
        $models = DB::connection('mysql2')->table('models')->select('Modelname')->where('status', 'Approved')->pluck('Modelname')->toArray();

        $resultModels = json_encode($this->getAjaxData($models));

        return response()->json([
            'success' => true,
            'data' => $resultModels
        ]);
    }

    public function test(){

        $models = DB::connection('mysql2')->table('models')->get();

        return 'test';

    }

    public function newmodels(){
        $start = microtime(true);

        $newsynctable = DB::table('models_free_chat')->where('status', 'free_chat')->get();
        DB::connection('mysql3')->table('smartend_models2')->truncate();


        foreach ($newsynctable as $item){

            DB::connection('mysql3')->table('smartend_models2')->insert(['name' => $item->modelname, 'state' => $item->status]);

//            $idd = DB::connection('mysql3')->table('smartend_models2')->where('id', $item->id)->first();
//
//            if ($idd) {
//                DB::connection('mysql3')->table('smartend_models2')->where('id',$idd->id)->update(['name' => $item->modelname, 'state' => $item->status]);
//            } else {
//                DB::connection('mysql3')->table('smartend_models2')->insert(['name' => $item->modelname, 'state' => $item->status]);
//            }

        }

        $time_elapsed_secs = microtime(true) - $start;
        echo $time_elapsed_secs;

    }

    public function getRealFirstLogin($model){

        $flogin = 'NO';
        $res = DB::table('models_timestamp')->where('modelname', $model)->orderBy('status_start','asc')->first();

        if ($res) {
            $flogin = $res->status_start;
            $flogin = Carbon::createFromTimestamp($flogin, 'Europe/Bucharest')->format('Y-m-d');
        }

        return response()->json([
            'success' => true,
            'firstlogin' => $flogin
        ]);

    }


    public function checkModelsStatus(){

        $time = time();
        $date = Carbon::today()->toDateString();

        //DB::table('models_free_chat')->truncate();

        $models = DB::table('sync_models')->select('sync_Modelname')->pluck('sync_Modelname')->toArray();

        $resultModels = $this->getAjaxData($models);

        foreach($resultModels as $model){


            $newres = DB::table('models_free_chat')->where('modelname',$model['performerId'])->first();
            if ($newres) {
                //delete
                //DB::table('models_free_chat')->where('modelname',$model['performerId'])->delete();
                //update
                DB::table('models_free_chat')->where('modelname',$model['performerId'])->update(['status' => $model['status']]);
                //DB::table('models_free_chat')->insert(['modelname' => $model['performerId'],'status' => $model['status']]);
            } else {
                //insert
                DB::table('models_free_chat')->insert(['modelname' => $model['performerId'],'status' => $model['status']]);
            }

            //sort desc today
            // check today, if modelname exist -> 1)read last_status -> if current_status =/= last_status -> 2)add end time to current status + update last_status 3)insert modelname, date, status -> free_chat (ex) start & end time & last_status
            //                                                                else -> current_status == 2)last_status update end_time
            //                  else           -> insert modelname, date, status -> free_chat (ex) start & end time & last_status



            $resTime = DB::table('models_timestamp')->where('modelname', $model['performerId'])->where('date', '=', $date)->orderBy('status_start', 'DESC')->first();
            //dd($resTime);
            if (!$resTime) {
                //dd('here1');
                DB::table('models_timestamp')->insert(['modelname' => $model['performerId'], 'status_start' => $time, 'status_end' => $time, 'last_status' => $model['status'], 'date' => $date]);

            } else {
                //dd('here2');
                if ($resTime->last_status == $model['status']) {

                    DB::table('models_timestamp')->where('id',$resTime->id)->update(['status_end' => $time]);
                }
                else {
                    //dd('here3');
                    DB::table('models_timestamp')->where('id',$resTime->id)->update(['status_end' => $time]);
                    DB::table('models_timestamp')->insert(['modelname' => $model['performerId'], 'status_start' => $time, 'status_end' => $time, 'last_status' => $model['status'], 'date' => $date]);
                }
            }

            //DB::table('models_timestamp')->insert(['modelname' => $model['performerId'], 'status_start' => $time, 'status_end' => $time, 'last_status' => $model['status']]);
        }

        $newsynctable = DB::table('models_free_chat')->where('status', 'free_chat')->get();
        DB::connection('mysql3')->table('smartend_models2')->truncate();


        foreach ($newsynctable as $item){

            $checktraffic = DB::table('sync_models')->where('sync_Modelname', $item->modelname)->where('priority', 0)->first();
            if ($checktraffic) DB::connection('mysql3')->table('smartend_models2')->insert(['name' => $item->modelname, 'state' => $item->status]);

        }


        return 'ok';
    }

    public function modelShift ($modelname){

        //$date = Carbon::today()->toDateString();
        $date = Carbon::yesterday()->toDateString();
        $shiftStart = 0;

        $resTime = DB::table('models_timestamp')->where('modelname', $modelname)->where('date', '>=', $date)->orderBy('status_start', 'DESC')->get();

        foreach($resTime as $res){

            if ( ($res->last_status == 'offline') && (($res->status_end - $res->status_start) > 4 * 3600) ) {
                $shiftStart = $res->status_end;
                break;

            }
        }

        $resTime = DB::table('models_timestamp')
            ->where('modelname', $modelname)
            ->where('status_start','>=',$shiftStart)
            ->where('date', '>=', $date)
            ->orderBy('status_start', 'ASC')->get();


        return response()->json([
            'success' => true,
            'data' => $resTime,
            'modelname' => $modelname
        ]);


    }

    public function startShift(Request $request){

        $lastShift = Carbon::now()->subHours(12)->toDateTimeString();

        $model = $request->input('modelname');
        //$model = 'Mymodel';

        //search if model has started today shift
        $res = DB::table('model_startshift')->where('modelname', $model)->where('time' , '>', $lastShift)->first();

        if(!$res) {
            DB::table('model_startshift')->insert(['modelname' => $model]);

            return response()->json([
                'success' => true,
                'shift' => 'started now',
                'modelname' => $model
            ]);

        } else {

            return response()->json([
                'success' => true,
                'shift' => 'already started',
                'modelname' => $model
            ]);

        }

    }

    public function getAjaxData($models){

        $modelStatus = array();
        $modelStatus2 = array();

        $arrchuck = array_chunk($models, 50);

        foreach($arrchuck as $arr){
            $modelstring = '';
            foreach($arr as $ar){
                $modelstring .= $ar.',';
            }

            $cSession = curl_init();
            $url = "http://pt.ptawe.com/api/model/feed?siteId=gjasmin&psId=14noiembrie&psTool=213_1&psProgram=revs&campaignId=&category=girl&limit=10&showOffline=0&extendedDetails=0&responseFormat=json&performerId=".$modelstring."&subAffId={SUBAFFID}&accessKey=ea0f7bf083974543186e2abb1f8ac09c&legacyRedirect=1";
            $url = "http://pt.ptawe.com/api/model/feed?siteId=gjasmin&psId=14noiembrie&psTool=213_1&psProgram=revs&campaignId=&category=girl&limit=10&imageSizes=320x180&imageType=glamour&showOffline=1&extendedDetails=1&responseFormat=json&performerId=".$modelstring."&subAffId={SUBAFFID}&accessKey=ea0f7bf083974543186e2abb1f8ac09c&legacyRedirect=1";

            curl_setopt($cSession, CURLOPT_URL, $url);
            curl_setopt($cSession, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($cSession, CURLOPT_FOLLOWLOCATION, 1);
            $resCurl = curl_exec($cSession);
            curl_close($cSession);
            $res = json_decode($resCurl, true);

            if ( (isset($res['status'])) && ($res['status'] == 'OK')  && ($res['errorCode'] == 0)) {

                $modelStatus = $res['data']['models'];
                $modelStatus2 = array_merge($modelStatus,$modelStatus2);
            }

        }

        return $modelStatus2;

    }

    /*public function show($id)
    {
        $product = auth()->user()->products()->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product with id ' . $id . ' not found'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $product->toArray()
        ], 400);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'price' => 'required|integer'
        ]);

        $product = new Product();
        $product->name = $request->name;
        $product->price = $request->price;

        if (auth()->user()->products()->save($product))
            return response()->json([
                'success' => true,
                'data' => $product->toArray()
            ]);
        else
            return response()->json([
                'success' => false,
                'message' => 'Product could not be added'
            ], 500);
    }

    public function update(Request $request, $id)
    {
        $product = auth()->user()->products()->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product with id ' . $id . ' not found'
            ], 400);
        }

        $updated = $product->fill($request->all())->save();

        if ($updated)
            return response()->json([
                'success' => true
            ]);
        else
            return response()->json([
                'success' => false,
                'message' => 'Product could not be updated'
            ], 500);
    }

    public function destroy($id)
    {
        $product = auth()->user()->products()->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product with id ' . $id . ' not found'
            ], 400);
        }

        if ($product->delete()) {
            return response()->json([
                'success' => true
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Product could not be deleted'
            ], 500);
        }
    }*/

    public function addHitsToModel($model){

        DB::table('log')->insert(['modelname' => $model]);
        //$hits = DB::table('model_hits')->where('modelname', $model)->first();
        $hits = DB::table('model_hits')->where('modelname', $model)->whereDate('created_at', DB::raw('CURDATE()'))->first();

        if (isset($hits->hits)) {
            DB::table('model_hits')->where('modelname', $model)->update(['hits' => $hits->hits+1]);

            return response()->json([
                'success' => true
            ]);

        } else {
            return response()->json([
                'success' => false,
                'message' => 'Model ' . $model . ' not found! '
            ], 400);
        }

    }

    public function getTwitterMonth(){

        $tweetres = DB::connection('mysql2')
            ->table('models_tweets')
            ->select('models_tweets.twitter_account', DB::raw('count(*) as total'))
            ->groupBy('models_tweets.twitter_account')
            ->where('models_tweets.created_at', '>',Carbon::today()->subDays(30))
            ->get();

        foreach($tweetres as $tweet){
            $modelnameres = DB::connection('mysql2')->table('models')->select('Modelname')->where('twitter_account',$tweet->twitter_account)->first();
            if(($modelnameres) && isset($modelnameres->Modelname)){
                $tweet->Modelname = $modelnameres->Modelname;

                $newrow = DB::table('model_tweets_month')->where('twitter_account',$tweet->twitter_account)->first();
                if(!$newrow){
                    DB::table('model_tweets_month')->insert(['modelname' => $tweet->Modelname,'twitter_account' =>  $tweet->twitter_account,'tweets' =>  $tweet->total]);
                } else {
                    DB::table('model_tweets_month')->where('twitter_account',$tweet->twitter_account)->update(['modelname' => $tweet->Modelname,'tweets' =>  $tweet->total]);
                }
            }
        }

        //dd($tweetres);

    }

    public function updateApprovedModels(){

        $time = time();

        $models = DB::connection('mysql2')->table('models')->select('id','Modelname','priority','studios_id')->where('status', 'Approved')->get();

        foreach($models as $model){

            $myModel = DB::table('sync_models')->where('sync_Modelname',$model->Modelname)->first();
            if(!$myModel){
                DB::table('sync_models')->insert(['sync_Modelname' => $model->Modelname, 'studio' => $model->studios_id, 'priority' => $model->priority, 'time' => $time]);
            } else {
                DB::table('sync_models')->where('sync_Modelname', $model->Modelname)->update(['studio' => $model->studios_id, 'priority' => $model->priority, 'time' => $time]);
            }


        }

        $modelsnew = DB::table('sync_models')->select('sync_Modelname')->pluck('sync_Modelname')->toArray();

        $resultModels = $this->getAjaxData($modelsnew);

        foreach($resultModels as $modelzz){

            DB::table('sync_models')->where('sync_Modelname',$modelzz['performerId'])->update([
                'data_language' => implode(', ',$modelzz['details']['languages']),
                'data_streamQuality' => $modelzz['details']['streamQuality'],
                'data_willingnesses' =>  implode(', ',$modelzz['details']['willingnesses']),
                'data_modelRating' => $modelzz['details']['modelRating'],
                'data_chargeAmount' => $modelzz['details']['chargeAmount'],
                'data_sex' => $modelzz['persons'][0]['sex'],
                'data_age' => $modelzz['persons'][0]['age'],
                'data_category' => $modelzz['category'],
                'data_bannedCountries' =>  implode(', ',$modelzz['bannedCountries']),
                'data_profilePictureUrl' => $modelzz['profilePictureUrl']['size320x180']
            ]);

        }

        $this->getTwitterMonth();

        //$this->getFirstLogin();

        //remove models that are 'suspended' or other status
        $this->clearApproved();

        return 'ok';

    }

    public function clearApproved(){

        $models = DB::connection('mysql2')->table('models')->where('status', 'Approved')->pluck('Modelname')->toArray();

        $myres = DB::table('sync_models')->pluck('sync_Modelname')->toArray();

        foreach($myres as $model){
            if (!in_array($model, $models)) DB::table('sync_models')->where('sync_Modelname', $model)->delete();

        }

    }

    public function getFirstLogin(){

            $modelsnew = DB::table('sync_models')->select('sync_Modelname')->where('first_login',null)->pluck('sync_Modelname')->toArray();

            foreach ($modelsnew as $model){

                $firstday = $this->getFirstLoginFunction($model);

                DB::table('sync_models')->where('sync_Modelname', $model)->update(['first_login' => $firstday]);

            }

    }

    function getFirstLoginFunction($modelname) {

        $models = DB::connection('mysql2')->table('models')->select('first_login','signupdate')->where('Modelname', $modelname)->first();

        if ($models) return $models->signupdate;
        else  return null;




    }

    function getFirstLoginFunction_old($modelname) {

        $date2017 = '2017-01-01';

        $url = "https://partner-api.modelcenter.jasmin.com/v1/statistics/chat-times?fromDate=$date2017&screenNames[]=$modelname";

        $authorization = "Authorization: Bearer 406ad45b5ed5748abb871cc99ef69bc45d0ad2779f9034f91de5af254f8a9902";
        $authorization1 = "Authorization: Bearer 8c23e7366a3e7096a30914edb145efc95fe241cd5be682a5ec1a366ca01e6c70";

        $res = $this->curlAuth($authorization, $url);
        if (array_key_exists($modelname, $res)) {

        } else $res = $this->curlAuth($authorization1, $url);

        $day = null;

        if (array_key_exists($modelname, $res)) {
            $model_name = $res[$modelname];
            foreach ($model_name as $key => $value){

                $day = $key;
                break;
            }

            return $day;

        } else {
            return $day;
        }


    }

    function curlAuth($auth, $url){

        $authorization = $auth;
        $cSession = curl_init();

        //step2
        curl_setopt($cSession, CURLOPT_URL, $url);
        curl_setopt($cSession, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $authorization));
        curl_setopt($cSession, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cSession, CURLOPT_FOLLOWLOCATION, 1);
        $result = curl_exec($cSession);
        curl_close($cSession);
        $res = json_decode($result, true);

        return $res;

    }

    public function getModelForRedirect(){

        $model = DB::table('models_free_chat')
            ->leftJoin('sync_models','models_free_chat.modelname','=','sync_models.sync_Modelname')
            ->orderBy('sync_models.hits_today','ASC')
            ->first()->modelname;

        return response()->json([
            'success' => true,
            'data' => $model
        ]);


    }

    public function getModelForRedirect2(){

        // check if any records today
        $res = DB::table('model_hits')->whereDate('created_at', DB::raw('CURDATE()'))->orderBy('hits','ASC')->get();
        if (count($res) > 0){

            $model = DB::table('models_free_chat')
                ->where('models_free_chat.status','free_chat')
                ->leftJoin('model_hits','models_free_chat.modelname','=','model_hits.modelname')
                ->orderBy('model_hits.hits','ASC')
                ->first()->modelname;

            return response()->json([
                'success' => true,
                'data' => $model
            ]);

        } else {
            $getModels = DB::table('sync_models')->get();
            foreach($getModels as $model){
                DB::table('model_hits')->insert(['modelname' => $model->sync_Modelname,'created_at' => DB::raw('CURDATE()')]);
            }

            $modelres = DB::table('models_free_chat')
                ->where('models_free_chat.status','free_chat')
                ->leftJoin('model_hits','models_free_chat.modelname','=','model_hits.modelname')
                ->first()->modelname;

            return response()->json([
                'success' => true,
                'data' => $modelres
            ]);


        }

    }

    public function getHits(){

        $models = DB::table('model_hits')
            ->leftJoin('models_free_chat','model_hits.modelname','=','models_free_chat.modelname')
            ->leftJoin('sync_models','model_hits.modelname','=','sync_models.sync_Modelname')
            ->leftJoin('model_tweets_month','model_hits.modelname','=','model_tweets_month.modelname')
            ->where('model_hits.created_at',DB::raw('CURDATE()'))
            ->get();

        //$resultModels = json_encode($models);
        $resultModels = ($models);

        return response()->json([
            'success' => true,
            'data' => $resultModels
        ]);

    }

    public function testdb(){

        DB::enableQueryLog();

        $models = DB::table('sync_models')
            ->selectRaw('sync_models.*, studios.name as StudioName, model_average.total as avgTotal, model_average.days as avgDays, models_free_chat.*, model_tweets_month.*')
            ->leftJoin('models_free_chat','sync_models.sync_Modelname','=','models_free_chat.modelname')
            //->leftJoin('model_hits','sync_models.sync_Modelname','=','model_hits.modelname')
            ->leftJoin('model_tweets_month','sync_models.sync_Modelname','=','model_tweets_month.modelname')
            ->leftJoin('studios','sync_models.studio','=','studios.id')
            ->leftJoin('model_average','sync_models.sync_Modelname','=','model_average.modelname')
            //->where('created_at', '>=', Carbon::now()->subDays(30)->toDateTimeString())
            ->groupBy('sync_models.sync_Modelname')
            ->get();

        dd(DB::getQueryLog());

        dd($models);

    }


    public function getHitsLast30Days2(){

        $models = DB::table('sync_models')
            ->selectRaw('sync_models.*, studios.name as StudioName, model_average.total as avgTotal, model_average.days as avgDays, models_free_chat.*, model_tweets_month.*')
            ->leftJoin('models_free_chat','sync_models.sync_Modelname','=','models_free_chat.modelname')
            //->leftJoin('model_hits','sync_models.sync_Modelname','=','model_hits.modelname')
            ->leftJoin('model_tweets_month','sync_models.sync_Modelname','=','model_tweets_month.modelname')
            ->leftJoin('studios','sync_models.studio','=','studios.id')
            ->leftJoin('model_average','sync_models.sync_Modelname','=','model_average.modelname')
            //->where('created_at', '>=', Carbon::now()->subDays(30)->toDateTimeString())
            ->groupBy('sync_models.sync_Modelname')
            ->get();

//        foreach ($models as $model){
////            $studioRes = DB::table('studios')->where('id',$model->studio)->first();
////            if ($studioRes) $model->StudioName = $studioRes->name;
////            else $model->StudioName = 'Home';
//
//            $model->avgTotal = 0;
//            $model->avgDays = 0;
//
//            $ress = DB::table('model_average')->where('modelname', $model->sync_Modelname)->first();
//            if ($ress) {
//                $model->avgTotal = $ress->total;
//                $model->avgDays = $ress->days;
//            }
//
//        }

        return response()->json([
            'success' => true,
            'data' => $models
        ]);


    }

    public function getHitsLast30Days(){

        $models = DB::table('sync_models')
            ->selectRaw('sync_models.*, models_free_chat.*, model_hits.*, model_tweets_month.*, SUM(model_hits.hits) as hits')
            ->leftJoin('models_free_chat','sync_models.sync_Modelname','=','models_free_chat.modelname')
            ->leftJoin('model_hits','sync_models.sync_Modelname','=','model_hits.modelname')
            ->leftJoin('model_tweets_month','sync_models.sync_Modelname','=','model_tweets_month.modelname')
            //->where('created_at', '>=', Carbon::now()->subDays(30)->toDateTimeString())
            ->groupBy('sync_models.sync_Modelname')
            ->get();

        foreach ($models as $model){
            $studioRes = DB::table('studios')->where('id',$model->studio)->first();
            if ($studioRes) $model->StudioName = $studioRes->name;
            else $model->StudioName = 'Home';

            $model->avgTotal = 0;
            $model->avgDays = 0;

            $ress = DB::table('model_average')->where('modelname', $model->sync_Modelname)->first();
            if ($ress) {
                $model->avgTotal = $ress->total;
                $model->avgDays = $ress->days;
            }

        }

        return response()->json([
            'success' => true,
            'data' => $models
        ]);


    }

    public function getInfo($modelname){


        if ($modelname == "trainer") {
            $pic = "https://ae01.alicdn.com/kf/HTB18Q6BPXXXXXb3XXXXq6xXFXXXJ/TITIVATE-Halloween-Amazing-Circus-Performance-Circus-Trainer-Costume-Circus-Cosplay-Carnival-Halloween-Fancy-Dress-For-Women.jpg_640x640.jpg";
        } else {
            $models = DB::table('sync_models')->where('sync_Modelname',$modelname)->first();
            $pic = $models->data_profilePictureUrl;
        }


        return response()->json([
            'success' => true,
            'data' => $pic
        ]);


    }

    public function getShiftAverage() {

        $datess = array();

        $enddate = Carbon::today()->toDateString();

        $res = DB::table('sync_models')->pluck('sync_Modelname');

        foreach($res as $model){
            $mres = DB::table('models_timestamp')
                ->where('modelname',$model)
                ->where('last_status','!=','offline')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->limit(30)
                ->pluck('date')->toArray();

            $tmedia = $this->getIncome($model, end($mres), $enddate);

            $insert = DB::table('model_average')->where('modelname', $model)->first();
            if ($insert) {
                //update
                DB::table('model_average')->where('modelname', $model)->update(['total' => $tmedia, 'days' => sizeof($mres), 'last_update' => $enddate]);
            } else {
                //insert
                DB::table('model_average')->insert(['modelname' => $model,'total' => $tmedia, 'days' => sizeof($mres), 'last_update' => $enddate]);

            }


        }

        //get first login
        $rr = DB::table('sync_models')->whereNull('first_login')->pluck('sync_Modelname')->toArray();
        foreach($rr as $r){
            $this->getflogin($r);
        }

    }

    public function getonlyflog(){

        $rr = DB::table('sync_models')->whereNull('first_login')->pluck('sync_Modelname')->toArray();
        foreach($rr as $r){
            $this->getflogin($r);
        }

        }

    public function getflogin($model){

        $flogin = 'NO';
        $res = DB::table('models_timestamp')->where('modelname', $model)->orderBy('status_start','asc')->first();

        if ($res) {
            $flogin = $res->status_start;
            $flogin = Carbon::createFromTimestamp($flogin, 'Europe/Bucharest')->format('Y-m-d');
            DB::table('sync_models')->where('sync_modelname', $model)->update(['first_login' => $flogin]);
        }

    }

    public function getIncome($model, $startdate, $enddate){

        $authorization = "Authorization: Bearer 406ad45b5ed5748abb871cc99ef69bc45d0ad2779f9034f91de5af254f8a9902";
        $authorization1 = "Authorization: Bearer 8c23e7366a3e7096a30914edb145efc95fe241cd5be682a5ec1a366ca01e6c70";

        $data = array();


        $url = "https://partner-api.modelcenter.jasmin.com/v1/reports/performers?fromDate=$startdate&toDate=$enddate"."&screenNames[]=".$model."&reports[]=general-overview";

        $res = $this->curlAuth($authorization, $url);
        if ($res && array_key_exists('data', $res)) $data = $res['data'];


        if (array_key_exists('errors', $res)){

            $res = $this->curlAuth($authorization1, $url);

            if ($res && array_key_exists('data', $res)) {
                $data = $res['data'];

            }
        }



        $myModelTotal = 0;
        foreach($data as $mData){
            if (array_key_exists('screenName', $mData)) $myModelTotal = $mData['total']['earnings']['value'];
        }

        return $myModelTotal;

    }


    public function getHitsNewModels3(){

        $endDate = Carbon::now()->format('Y-m-d');
        $startDate = Carbon::today()->subDays( 15 )->format('Y-m-d');

        $models = DB::table('sync_models')
            ->where('sync_models.first_login', '>', Carbon::today()->subDays( 15 ))
            ->leftJoin('models_free_chat','sync_models.sync_Modelname','=','models_free_chat.modelname')
            ->leftJoin('model_tweets_month','sync_models.sync_Modelname','=','model_tweets_month.modelname')
            ->get();

        $models2 = $models->pluck('sync_Modelname');

        dd($models2);

        $modelRes = $this->getHoursJasmin($models2, $startDate, $endDate);

        foreach ($models as $key => $model){
            $mTime = 0;
            if (array_key_exists($model->sync_Modelname, $modelRes)) $mTime = $modelRes[$model->sync_Modelname];

            $model->totalTime15 = $mTime;
        }

        foreach ($models as $model){

            $fmodel = DB::table('models_timestamp')->where('modelname',$model->sync_Modelname)->orderBy('status_start','asc')->first();
            if ($fmodel && $fmodel->status_start) {
                $carbon = Carbon::createFromTimestamp($fmodel->status_start)->format('Y-m-d');

                $model->firsttime = $carbon;

                $model->newTime = $this->getHoursJasminIndividual($model->sync_Modelname, $carbon, $endDate);

            }

            $studioRes = DB::table('studios')->where('id',$model->studio)->first();
            if ($studioRes) $model->StudioName = $studioRes->name;
            else $model->StudioName = 'Home';

        }


        return response()->json([
            'success' => true,
            'data' => $models
        ]);

    }

    public function getHitsNewModels2(){

        //time 50H to sec
        $time50h = 3600 * 24;

        $endDate = Carbon::now()->format('Y-m-d');
        $startDate = Carbon::today()->subDays( 15 )->format('Y-m-d');


        $models = DB::table('sync_models')
            ->where('sync_models.first_login', '>', Carbon::today()->subDays( 15 ))
            ->leftJoin('models_free_chat','sync_models.sync_Modelname','=','models_free_chat.modelname')
            ->leftJoin('model_tweets_month','sync_models.sync_Modelname','=','model_tweets_month.modelname')
            ->leftJoin('studios','sync_models.studio','=','studios.id')
            ->get();

        $models2 = $models->pluck('sync_Modelname');

        $modelRes = $this->getHoursJasmin($models2, $startDate, $endDate);

        foreach ($models as $key => $model){
            $mTime = 0;
            if (array_key_exists($model->sync_Modelname, $modelRes)) $mTime = $modelRes[$model->sync_Modelname];

            $model->totalTime15 = $mTime;


        }

        foreach ($models as $model){

            $fmodel = DB::table('models_timestamp')->where('modelname',$model->sync_Modelname)->orderBy('status_start','asc')->first();
            if ($fmodel && $fmodel->status_start) {
                $model->firsttime = Carbon::createFromTimestamp($fmodel->status_start)->format('Y-m-d');

                $model->newTime = $this->getHoursJasminIndividual($model->sync_Modelname, $model->firsttime, $endDate);

            }

            $model->avgTotal = 0;
            $model->avgDays = 0;

            $ress = DB::table('model_average')->where('modelname', $model->sync_Modelname)->first();
            if ($ress) {
                $model->avgTotal = $ress->total;
                $model->avgDays = $ress->days;
            }

        }


        return response()->json([
            'success' => true,
            'data' => $models
        ]);

    }

    function getMyModelsTrainer(){

        $id = 1;
        $res = DB::table('trainer')->where('id',$id)->first();


        if (($res->mymodels !== null) || ($res->mymodels !== "")) {

            $mymodels = explode(',', $res->mymodels);

            $result = DB::table('models_free_chat')
                ->leftJoin('model_shift_report','models_free_chat.modelname','=','model_shift_report.model')
                ->whereIn('models_free_chat.modelname', $mymodels)
                ->select('models_free_chat.status','models_free_chat.modelname', 'model_shift_report.model')
                ->get()->toArray();

            return response()->json([
                'success' => true,
                'data' => $result,
                'name' => $res->name
            ]);

        } else {
            return response()->json([
                'success' => false,
                'data' => null
            ]);
        }
    }

    public function getHitsNewModels(){

        //time 50H to sec
        $time50h = 3600 * 24;

        $endDate = Carbon::now()->format('Y-m-d');
        $startDate = Carbon::today()->subDays( 15 )->format('Y-m-d');

        $models = DB::table('model_hits')
            ->leftJoin('models_free_chat','model_hits.modelname','=','models_free_chat.modelname')
            ->leftJoin('sync_models','model_hits.modelname','=','sync_models.sync_Modelname')
            ->leftJoin('model_tweets_month','model_hits.modelname','=','model_tweets_month.modelname')
            ->where('model_hits.created_at',DB::raw('CURDATE()'))
            ->whereDate('sync_models.first_login', '>', Carbon::today()->subDays( 15 ))
            ->get();

        $models2 = DB::table('model_hits')
            ->leftJoin('models_free_chat','model_hits.modelname','=','models_free_chat.modelname')
            ->leftJoin('sync_models','model_hits.modelname','=','sync_models.sync_Modelname')
            ->leftJoin('model_tweets_month','model_hits.modelname','=','model_tweets_month.modelname')
            ->where('model_hits.created_at',DB::raw('CURDATE()'))
            ->whereDate('sync_models.first_login', '>', Carbon::today()->subDays( 15 ))
            ->pluck('sync_Modelname');



        $modelRes = $this->getHoursJasmin($models2, $startDate, $endDate);

        foreach ($models as $key => $model){
            $mTime = 0;
            if (array_key_exists($model->sync_Modelname, $modelRes)) $mTime = $modelRes[$model->sync_Modelname];

            $model->totalTime15 = $mTime;
        }


        return response()->json([
            'success' => true,
            'data' => $models
        ]);

    }

    function getHoursJasminIndividual($model, $startdate, $enddate) {

        $authorization = "Authorization: Bearer 406ad45b5ed5748abb871cc99ef69bc45d0ad2779f9034f91de5af254f8a9902";
        $authorization1 = "Authorization: Bearer 8c23e7366a3e7096a30914edb145efc95fe241cd5be682a5ec1a366ca01e6c70";
        //$url = "https://partner-api.modelcenter.jasmin.com/v1/statistics/chat-times?fromDate=$startdate&toDate=$enddate&screenNames[]=$modelname";

        $param = '&screenNames[]='.$model;
        $data = array();


        $url = "https://partner-api.modelcenter.jasmin.com/v1/reports/performers?fromDate=$startdate&toDate=$enddate".$param."&reports[]=working-time";

        $res = $this->curlAuth($authorization, $url);
        if ($res && array_key_exists('data', $res)) $data = $res['data'];


        if (array_key_exists('errors', $res)){

            $res = $this->curlAuth($authorization1, $url);

            if ($res && array_key_exists('data', $res)) {
                $data = $res['data'];

            }
        }



        $myModelTime = 0;
        foreach($data as $mData){
            if (array_key_exists('screenName', $mData)) $myModelTime = $this->workTime($mData['workingTime']);
        }

        return $myModelTime;

    }


    function getHoursJasmin($models, $startdate, $enddate) {

        $authorization = "Authorization: Bearer 406ad45b5ed5748abb871cc99ef69bc45d0ad2779f9034f91de5af254f8a9902";
        $authorization1 = "Authorization: Bearer 8c23e7366a3e7096a30914edb145efc95fe241cd5be682a5ec1a366ca01e6c70";
        //$url = "https://partner-api.modelcenter.jasmin.com/v1/statistics/chat-times?fromDate=$startdate&toDate=$enddate&screenNames[]=$modelname";

        $param = '';
        foreach ($models as $model){
            $param .= '&screenNames[]='.$model;
        }

        $data = array();

        $url = "https://partner-api.modelcenter.jasmin.com/v1/reports/performers?fromDate=$startdate&toDate=$enddate".$param."&reports[]=working-time";

        $res = $this->curlAuth($authorization, $url);
        if ($res && array_key_exists('data', $res)) $data = $res['data'];

        if (array_key_exists('errors', $res)){
            $errors = $res['errors'][0]['meta']['notFoundScreenNames'];
            $param = '';
            foreach ($errors as $model){
                $param .= '&screenNames[]='.$model;
            }
            $url = "https://partner-api.modelcenter.jasmin.com/v1/reports/performers?fromDate=$startdate&toDate=$enddate".$param."&reports[]=working-time";
            $res = $this->curlAuth($authorization1, $url);

            if ($res && array_key_exists('data', $res)) {
                $data1 = $res['data'];
                $data = array_merge($data, $data1);
            }
        }

        $myModelTime = array();
        foreach($data as $mData){
            if (array_key_exists('screenName', $mData)) $myModelTime[$mData['screenName']] = $this->workTime($mData['workingTime']);
        }

        return $myModelTime;

    }

    function workTime($arrWorkTime){

        $totaltime = 0;

        $model_name = $arrWorkTime;

        $totaltime = $model_name['vip_show']['value'] + $model_name['pre_vip_show']['value'] + $model_name['private']['value'] + $model_name['free']['value'];

        return $totaltime;

    }

    function getHoursJasmin2($modelname, $startdate, $enddate) {

        $authorization = "Authorization: Bearer 406ad45b5ed5748abb871cc99ef69bc45d0ad2779f9034f91de5af254f8a9902";
        $authorization1 = "Authorization: Bearer 8c23e7366a3e7096a30914edb145efc95fe241cd5be682a5ec1a366ca01e6c70";
        //$url = "https://partner-api.modelcenter.jasmin.com/v1/statistics/chat-times?fromDate=$startdate&toDate=$enddate&screenNames[]=$modelname";
        $url = "https://partner-api.modelcenter.jasmin.com/v1/reports/performers?fromDate=$startdate&toDate=$enddate&screenNames[]=$modelname&reports[]=working-time";


        $res = $this->curlAuth($authorization, $url);


        if (array_key_exists('errors', $res)){
            $res = $this->curlAuth($authorization1, $url);
        }


        if ( (array_key_exists('data', $res)) && ($res['data'][0]['screenName'] == $modelname)) {

            $model_name = $res['data'][0]['workingTime'];


            $totaltime = $model_name['vip_show']['value'] + $model_name['pre_vip_show']['value'] + $model_name['private']['value'] + $model_name['free']['value'];

            return $totaltime;

        } else {
            return 0;
        }


    }

    public function cronjobGetTrafic(){


        $resStudio20cam = DB::connection('mysql3')->table('smartend_trafic')->get();

        dd($resStudio20cam);

//        foreach ($resStudio20cam as $model){
//
//            $old = $model->Date;
//            $old = substr($old, 0, 10);
//            $old = explode('-', $old);
//            $old = $old[2].'-'.$old[1].'-'.$old[0];
//
//            DB::table('trafic')->insert(['modelname' => $model->Model, 'ip' => $model->Ip, 'old_Date' => $model->Date, 'date' => $old,'source' => $model->Source, 'medium' => $model->Medium, 'campaign' => $model->Campaign]);
//        }
//
//        DB::connection('mysql4')->table('trafic')->truncate();



    }


}