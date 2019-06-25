<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 1/30/2019
 * Time: 10:45 AM
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;


class AppModelsController extends Controller
{

    public function apiLogin(Request $request){

        $model_id = null;
        $total["totalamount"] = 0;
        $total["hours"] = "00:00:00";
        $total["totalhistory"] = 0;
        $sales = null;
        $mytrainer = "NoTrainer";
        $mytrainerProfile = '';

        $modelEmail = $request->input('email');

        $model_id_res = DB::connection('mysql2')->table('users')->where('email',$modelEmail)->first();
        if (($model_id_res !== null) && ($model_id_res->id !== null)) {
            $model_id = $model_id_res->id;

            $modelnameres = DB::connection('mysql2')->table('models')->where('id',$model_id)->first();
            if (($modelnameres) && ($modelnameres->Modelname)){
                $modelname = $modelnameres->Modelname;
                $pass = $modelnameres->Pass;
                $artisticEmail = $modelnameres->artistic_email;
                $artisticPassword = $modelnameres->artistic_password;

                $model = DB::table('sync_models')->select('sync_Modelname','data_profilePictureUrl')->where('sync_Modelname',$modelname)->first();

                if (($model !== null) && ($model->sync_Modelname !== null)) {

                    $model->artistic_email = $artisticEmail;
                    $model->artistic_password = $artisticPassword;
                    $model->model_id = $model_id;
                    $model->password = $pass;
                    //get reservations
                    $reservations = $this->reservations($model->sync_Modelname);

                    if($model_id){

                        $totalhours = $this->getOnlineHoursThisPeriod($model_id);
                        $total["totalamount"] = $totalhours["totalamount"];
                        $total["hours"] = $totalhours["hours"];
                        $total["totalhistory"] = $this->getIncomeHistory($model_id);
                        $sales = $this->getSalesPeriod($model_id);

                        //find trainer
                        $trainerres = DB::table('trainer')->get();
                        foreach($trainerres as $trainer){
                            if ( ($trainer->mymodels) && ($trainer->mymodels !== '') ){
                                $mymodels = explode(',', $trainer->mymodels);
                                if (in_array($model->sync_Modelname, $mymodels)) {
                                    $mytrainer = $trainer->name;
                                    $mytrainerProfile = $trainer->profile;
                                    break;
                                }
                            }

                        }


                    }

                    return response()->json([
                        'success' => true,
                        'data' => $model,
                        'reservations' => $reservations,
                        'total' => $total,
                        'sales' => $sales,
                        'trainer' => $mytrainer,
                        'trainerprofile' => $mytrainerProfile
                    ]);

                } else {
                    return response()->json([
                        'success' => false,
                        'data' => 'model not found'
                    ]);
                }
            } else return response()->json([
                'success' => false,
                'data' => 'model name not found'
            ]);


        } else
            return response()->json([
                'success' => false,
                'data' => 'model not found'
            ]);



    }

    public function reservations($modelname = "modelname"){

        $date = \Carbon\Carbon::now();
        $datenext = \Carbon\Carbon::now();
        $monthF = $date->format('F');
        $next_monthF = $datenext->addMonths(1)->format('F');
        $monthD = (strlen((string)$date->month) > 1) ? $date->month : "0".$date->month;
        $year = $date->year;

        $this_period = $monthD . "-" . $year;

        $this_year = $year;
        $next_period = $monthD . "-" . $this_year;
        if($date->month == 12) {
            $this_year = $year+1;
            $next_period = "01-" . $this_year;
            $nextMonthD = "01";
        } else {
            $date->month++;
            $nextMonthD = (strlen((string)$date->month) > 1) ? $date->month : "0".$date->month;
            $next_period = $nextMonthD . "-" . $year;
        }

        $arr = array();

        //get model id
        $modelidres = DB::connection('mysql2')->table('models')->where('Modelname',$modelname)->first();

        if ( ($modelidres !== null) && isset($modelidres->id) ){

            $reservations = $this->getReservations($modelidres->id, $this_period);
            $this_hour = $reservations["hour"];
            $this_count = (strlen($reservations["days"]) > 0) ? (strlen($reservations["days"]) + 1)/3 : 0;

            $reservations_next = $this->getReservations($modelidres->id, $next_period);
            $next_hour = $reservations_next["hour"];
            $next_count = (strlen($reservations_next["days"]) > 0) ? (strlen($reservations_next["days"]) + 1)/3 : 0;

            //$reZ = explode(',',$reservations["days"]);

            $arr = ["this_month" => $monthF,
                    "this_month_nr" => $monthD,
                    "this_year" => $year,
                    "this_period" => $this_period,
                    "this_days" => $reservations["days"],
                    "this_count" => $this_count,
                    "this_hour" => $this_hour,
                    "next_month" => $next_monthF,
                    "next_month_nr" => $nextMonthD,
                    "next_year" => $this_year,
                    "next_period" => $next_period,
                    "next_days" => $reservations_next["days"],
                    "next_count" => $next_count,
                    "next_hour" => $next_hour,
                ];

            return $arr;

        } else {

            return $arr;

        }


        //returns {"this_month":""February","this_year":2019, "this_period":"02-2019","this_days":"01,02,03...","this_count":4,"this_hour":"06:00 AM","next_month":"Mars", "this_year":2019, "next_period":"03-2019","next_days":"01,02,03...","next_count":4}
        //or {"this_month":""February", "this_year":2019, "this_period":"02-2019","this_days":"","this_count":0,"next_month":"Mars", "this_year":2019, "next_period":"03-2019","next_days":"01,02,03...","next_count":4}

    }

    function getReservations($id, $month){

        $arr["days"] = '';
        $arr["hour"] = '';

        $res = DB::connection('mysql2')->table('reservations')->where('modelid',$id)->where('month',$month)->first();

        if(($res !== null) && isset($res->days) && (strlen($res->days) > 0) ) {

            $arr["days"] = $res->days;
            $arr["hour"] = $res->hour;

            return $arr;

        } else return $arr;

    }

    function getSalesPeriod($modelid){

        $now = Carbon::now()->format('Y-m');
        $today = Carbon::now()->format('d');
        $days = $this->getDaysThisPeriod();
        $sales = [];

        foreach($days as $day){
            if($day < $today) {
                $fday = $now . "-" . $day;
                $result = DB::connection('mysql2')->table('sales')->where('modelid',$modelid)->where('date',$fday)->first();
                if ($result) {
                    $sales[] = $result;
                } else {

                }

            } else break;

        }

        return $sales;
    }

    function getDaysThisPeriod(){

        $day = Carbon::now()->format('d');

        $month = Carbon::now()->format('Y-m');
        $start = Carbon::parse($month)->startOfMonth();
        $end = Carbon::parse($month)->endOfMonth();

        $dates = [];
        while ($start->lte($end)) {
            $dates[] = $start->copy()->format('d');
            $start->addDay();
        }

        $half = array_chunk($dates,15);
        $arr = ($day <= 15) ? $half[0] : $half[1];

        //echo "<pre>", print_r($arr), "</pre>";
        return $arr;

    }

    function getPeriodName(){
        $now = Carbon::now()->format('F');
        $day = Carbon::now()->format('d');
        $tag = ($day <= 15) ? 'I' : 'II';
        $period = $now . ' ' . $tag;
        return $period;
        }

    function getOnlineHoursThisPeriod($modelid){

        $period = $this->getPeriodName();

        $total["totalamount"] = 0;
        $total["hours"] = 0;

        $total_hours_amount = DB::connection('mysql2')->table('periods')->where('modelid',$modelid)->where('period',$period)->first();


        if ($total_hours_amount !== null)  {

            $total["totalamount"] = intval($total_hours_amount->totalamount);
            $total["hours"] = $total_hours_amount->hours;

        }

        return $total;

    }

    function getIncomeHistory($modelid){
        //get last six periods

        $total = 0;
        $total_hours_amount = DB::connection('mysql2')->table('periods')->where('modelid',$modelid)->orderBy('id','DESC')->limit(6)->get();

        foreach ($total_hours_amount as $totalamount){

                $total += $totalamount->totalamount;

            }

            return intval($total);


    }

    function updateMyModelsTrainer(Request $request){

        $id = $request->input('id');
        $models = $request->input('models');

        $res = DB::table('trainer')->where('id',$id)->first();
        if ($res){
            if ( !(($res->mymodels == '') || ($res->mymodels == null)) ) $models = $res->mymodels . "," . $models;
            DB::table('trainer')->where('id',$id)->update(['mymodels' => $models]);
            return response()->json([
                'success' => true,
                'data' => $models,
                'trainer' => $res->name
            ]);
        } else {
            return response()->json([
                'success' => false,
                'data' => null
            ]);
        }

    }

    public function trainerGetModelInfo(Request $request){

        $model = $request->input('modelname');
        $trainer = $request->input('trainer');

        $artisticemail = 'no email';
        $artisticpassword = 'no password';

        $modelartistic = DB::connection('mysql2')->table('models')->where('Modelname', $model)->first();
        if ($modelartistic){
            if ($modelartistic->artistic_email) $artisticemail = $modelartistic->artistic_email;
            if ($modelartistic->artistic_password) $artisticpassword = $modelartistic->artistic_password;

            }



        $model_res = DB::table('sync_models')->where('sync_Modelname',$model)->first();

        if ($model_res) {

            if ($model_res->data_language == null) $model_res->data_language = "null";
            if ($model_res->data_streamQuality == null) $model_res->data_streamQuality = "null";
            if ($model_res->data_willingnesses == null) $model_res->data_willingnesses = "null";
            if ($model_res->data_sex == null) $model_res->data_sex = "null";
            if ($model_res->data_age == null) $model_res->data_age = "null";
            if ($model_res->data_category == null) $model_res->data_category = "null";
            if ($model_res->data_bannedCountries == null) $model_res->data_bannedCountries = "null";
            if ($model_res->data_modelRating == null) $model_res->data_modelRating = "null";
            if ($model_res->data_chargeAmount == null) $model_res->data_chargeAmount = "null";
            if ($model_res->data_profilePictureUrl == null) $model_res->data_profilePictureUrl = "null";
            if ($model_res->first_login == null) $model_res->first_login = "null";

            $model_res->artistic_email = $artisticemail;
            $model_res->artistic_password = $artisticpassword;

            $model_res->shift = $this->modelShift($model);


            $total["totalamount"] = 0;
            $total["hours"] = "00:00:00";
            $total["totalhistory"] = 0;
            $sales = null;

                $modelnameres = DB::connection('mysql2')->table('models')->where('Modelname',$model)->first();
                if (($modelnameres) && ($modelnameres->Modelname)) {

                        if ($modelnameres->id) {

                            $totalhours = $this->getOnlineHoursThisPeriod($modelnameres->id);
                            $total["totalamount"] = $totalhours["totalamount"];
                            $total["hours"] = $totalhours["hours"];
                            $total["totalhistory"] = $this->getIncomeHistory($modelnameres->id);
                            $sales = $this->getSalesPeriod($modelnameres->id);

                        }

                }


            return response()->json([
                'success' => true,
                'data' => $model_res,
                'total' => $total,
                'sales' => $sales,
                'trainer' => $trainer
            ]);

        } else {
            return response()->json([
                'success' => false,
                'data' => "Model not found!!"
            ]);
        }



    }

    public function trainerstartshift(Request $request){

        $trainer_id = $request->input('trainer_id');
        $time_shift = $request->input('time');

        DB::table('trainer_shift_report')->insert(['trainer_id' => $trainer_id, 'date' => $time_shift]);

        return response()->json([
            'success' => true,
            'trainerShift' => $trainer_id ." -> ". $time_shift
        ]);
    }

    public function trainerendshift(Request $request){

        $trainer_id = $request->input('trainer_id');
        $time_shift = $request->input('time');

        DB::table('trainer_shift_report')->where('trainer_id', $trainer_id)->whereNull('end_date')->update(['end_date' => $time_shift]);

        return response()->json([
            'success' => true,
            'trainerShift' => $trainer_id ." -> ". $time_shift
        ]);
    }


    public function getTrainersStudio(Request $request){

        $trainer = $request->input('trainer');
        $studio = $request->input('studio');
        $model = $request->input('model');

        $trainers = DB::table('trainer')->where('studio',$studio)->get();

        $tday = Carbon::now()->format('Y-m-d');
        $yday = Carbon::yesterday()->format('Y-m-d');

        $resShiftReportData = DB::table('model_shift_report')->where('model', $model)->whereDate('created_at', $tday)->first();
        $resShiftReportDataYday = DB::table('model_shift_report')->where('model', $model)->whereNull('logout_time')->whereDate('created_at', $yday)->first();

        $shiftStarted = $resShiftReportData ? true : false;

        //check if shift is started from yesterday and not ended
        $shiftStarted = $resShiftReportDataYday ? true : $shiftStarted;

        return response()->json([
            'success' => true,
            'trainer' => $trainer,
            'model' => $model,
            'shift_start' => $shiftStarted,
            'studio' => $studio,
            'trainers' => $trainers
        ]);


        }

    public function removeModelFromTrainer(Request $request){
        //fromtrainer: msg[0], model: msg[1], totrainer: msg[2]

        $fromtrainer = $request->input('fromtrainer');
        $totrainer = $request->input('totrainer');
        $model = $request->input('model');

        $frmTrnRes = DB::table('trainer')->where('name',$fromtrainer)->first();

        if ( ($frmTrnRes) && ($frmTrnRes->mymodels) ){

            //remove from trainer
            if ($frmTrnRes->mymodels == $model) {
                DB::table('trainer')->where('name',$fromtrainer)->update(['mymodels' => '']);
            } else {
                $arr = explode(',',$frmTrnRes->mymodels);
                foreach ($arr as $key => $value) {
                    if($value == $model) unset($arr[$key]);
                    $strArr = implode(',',$arr);
                    DB::table('trainer')->where('name',$fromtrainer)->update(['mymodels' => $strArr]);
                }
            }

        if ($totrainer == 'Remove') {
            //only remove

        } else {
            //add to trainer
            $toTrnRes = DB::table('trainer')->where('name',$totrainer)->first();
            if  ($toTrnRes) {
                if ( ($toTrnRes->mymodels == '') || ($toTrnRes->mymodels == null) ){
                    DB::table('trainer')->where('name',$totrainer)->update(['mymodels' => $model]);
                } else {
                    DB::table('trainer')->where('name',$totrainer)->update(['mymodels' => $toTrnRes->mymodels.','.$model]);
                }


            }


            }


        }

        return response()->json([
            'success' => true,
            'data' => 'model removed/ transfered ->> '.$fromtrainer.' - '.$totrainer.' - '.$model,
            'from' => $fromtrainer,
            'to' => $totrainer

        ]);

    }

    public function modelpresent(Request $request){

        $trainer_id = $request->input('trainer_id');
        $model = $request->input('model');
        $text = $request->input('text');
        $time = $request->input('time');

        $resTrainer = DB::table('trainer')->where('id', $trainer_id)->first();
        $trainerName = ($resTrainer) ? $resTrainer->name : 'NoNameErr';
        $trainerStudio = ($resTrainer) ? $resTrainer->studio : 0;

        $arr = [$trainerName];
        $support = json_encode($arr);

        $tday = Carbon::now()->format('Y-m-d');
        $fLogin = $time;
        $lLogin = $time;
        $resFLogin = DB::table('models_timestamp')->where('modelname', $model)->where('last_status', '!=', 'offline')->whereDate('date', $tday)->orderBy('status_start', 'asc')->first();
        if ($resFLogin) {
            $fLogin = $resFLogin->status_start;
            $lLogin = $this->get_last_shif_end($model);
        }

        $pLoginTime = $time - $fLogin;

        $timeOnlinePeriod = $this->time_online_period($model, $tday, $fLogin);

        $incomeLastLogout = $this->income_last_logout($model, $fLogin, $lLogin);

        $resModelPrice = DB::table('sync_models')->where('sync_Modelname', $model)->first();
        $modelPrice = ($resModelPrice) ? $resModelPrice->data_chargeAmount : 0;



        DB::table('model_shift_report')->insert(['model' => $model,
            'support_main' => $trainerName,
            'support_main_id' => $trainer_id,
            'support' => $support,
            'arrival_time' => $time,
            'login_time' => $fLogin,
            'pre_login_time' => $pLoginTime,
            'login_time_period_before' => $timeOnlinePeriod,
            'income_last_logout' => $incomeLastLogout,
            'offline_income' => $incomeLastLogout,
            'studio' => $trainerStudio,
            'price_minute_awemp' => $modelPrice
            ]);


        return response()->json([
            'success' => true,
            'trainer_id' => $trainer_id,
            'model' => $model,
            'text' => $text

        ]);


    }

    function testModel(){

//        $tday = '2019-05-23';
//        $model = 'RebecaGlamy';
//
//        $resFLogin = DB::table('models_timestamp')->where('modelname', $model)->where('last_status', '!=', 'offline')->whereDate('date', $tday)->orderBy('status_start', 'asc')->first();
//
//        dd($resFLogin);

        //return date('H:m:s', '1558584841');

        return Carbon::createFromTimestamp('1558584841', 'Europe/Bucharest')->format('H:i:s');


    }

    function getshiftreportdata(Request $request){

        $trainer_id = $request->input('trainer_id');
        $model = $request->input('model');
        $tday = Carbon::now()->format('Y-m-d');
        $yday = Carbon::yesterday()->format('Y-m-d');

        $resShiftReportData = DB::table('model_shift_report')->where('model', $model)->whereDate('created_at', $tday)->first();
        $resShiftReportDataYday = DB::table('model_shift_report')->where('model', $model)->whereNull('logout_time')->whereDate('created_at', $yday)->first();

        $resShiftReportData = $resShiftReportData ? $resShiftReportData : $resShiftReportDataYday;

        $success = ($resShiftReportData) ? true : false;

        return response()->json([
            'success' => $success,
            'data' => $resShiftReportData,
            'model' => $model,
            'trainer' => $trainer_id

        ]);

    }

    function getShiftReportDataModule($model){

        $tday = Carbon::now()->format('Y-m-d');
        $yday = Carbon::yesterday()->format('Y-m-d');

        $resShiftReportData = DB::table('model_shift_report')->where('model', $model)->whereDate('created_at', $tday)->first();
        $resShiftReportDataYday = DB::table('model_shift_report')->where('model', $model)->whereNull('logout_time')->whereDate('created_at', $yday)->first();

        $resShiftReportData = $resShiftReportData ? $resShiftReportData : $resShiftReportDataYday;

        return $resShiftReportData;

    }

    function sendshiftreportdata2(){

        $message = 'Data stored successfuly!';

        $trainer_id = 13;
        $model = 'NikkySauvage';
        $tday = Carbon::now()->format('Y-m-d');
        $yday = Carbon::yesterday()->format('Y-m-d');

        $room = 5;
        $place = 5;
        $points = 5;
        $field1 = 'xx';
        $field2 = '';
        $field3 = '';
        $field4 = '';
        $field5 = '';
        $field6 = '';

        $resTday =  DB::table('model_shift_report')->where('model', $model)->orderByDesc('created_at')->whereDate('created_at', $tday)->first();
        $resYday =  DB::table('model_shift_report')->where('model', $model)->orderByDesc('created_at')->whereDate('created_at', $yday)->first();

        $resTday = $resTday ? $resTday : $resYday;

        if ($resTday) {

            $id = $resTday->id;

            DB::table('model_shift_report')
                ->where('id', $id)
                ->update([
                    'room' => $room,
                    'place_awd' => $place,
                    'awd_points' => $points,
                    'field1' => $field1,
                    'field2' => $field2,
                    'field3' => $field3,
                    'field4' => $field4,
                    'field5' => $field5,
                    'field6' => $field6
                ]);
        } else {
            $message = 'Shift Not Found!';
        }


        return response()->json([
            'success' => true,
            'data' => $message,
            'model' => $model,
            'trainer' => $trainer_id

        ]);



    }

    function sendshiftreportdata(Request $request){

        $message = 'Data stored successfuly!';

        $trainer_id = $request->input('trainer_id');
        $model = $request->input('model');
        $tday = Carbon::now()->format('Y-m-d');
        $yday = Carbon::yesterday()->format('Y-m-d');

        $room = $request->input('room');
        $place = $request->input('place');
        $points = $request->input('points');
        $field1 = $request->input('field1');
        $field2 = $request->input('field2');
        $field3 = $request->input('field3');
        $field4 = $request->input('field4');
        $field5 = $request->input('field5');
        $field6 = $request->input('field6');

        $resTday =  DB::table('model_shift_report')->where('model', $model)->orderByDesc('created_at')->whereDate('created_at', $tday)->first();
        $resYday =  DB::table('model_shift_report')->where('model', $model)->orderByDesc('created_at')->whereDate('created_at', $yday)->first();

        $resTday = $resTday ? $resTday : $resYday;

        if ($resTday) {

            $id = $resTday->id;

            DB::table('model_shift_report')
                ->where('id', $id)
                ->update([
                    'room' => $room,
                    'place_awd' => $place,
                    'awd_points' => $points,
                    'field1' => $field1,
                    'field2' => $field2,
                    'field3' => $field3,
                    'field4' => $field4,
                    'field5' => $field5,
                    'field6' => $field6
                ]);
        } else {
            $message = 'Shift Not Found!';
        }


        return response()->json([
            'success' => true,
            'data' => $message,
            'model' => $model,
            'trainer' => $trainer_id

        ]);


    }

    function modelendshift(Request $request){

        $trainer_id = $request->input('trainer_id');
        $model = $request->input('model');
        $text = $request->input('text');
        $time = $request->input('time');
        $room = $request->input('room');
        $awards = $request->input('awards');
        $place = $request->input('place');

        $tday = Carbon::now()->format('Y-m-d');

        $resModelLoginTime = DB::table('model_shift_report')->where('model', $model)->orderByDesc('created_at')->first();
        if ($resModelLoginTime) {
            $id = $resModelLoginTime->id;
            $login_time = $resModelLoginTime->login_time;
            $login_time_period_after = $this->login_time_period_after($model, $tday, $time);
            $total_time_shift_w = $this->total_time_shift_w($model, $login_time, $time);
            $break_time = ($time - $login_time) - $this->login_time_period_after2($model, $tday, $time);
            $totalShiftSales = $this->login_earnings_period_after2($model, $tday, $time);

            $resModelAverage = DB::table('model_average')->where('modelname', $model)->first();
            $avg = 1;
            if ($resModelAverage) $avg = $resModelAverage->total / $resModelAverage->days;

            $shiftAvg = $totalShiftSales / $avg * 100;

            $total_period_sales = $this->total_period_sales($model, $tday, $time);

            DB::table('model_shift_report')->where('id', $id)->update(
                ['room' => $room,
                    'awd_points' => $awards,
                    'place_awd' => $place,
                    'login_time_period_after' => $login_time_period_after,
                    'total_time_shift_w' => $total_time_shift_w,
                    'total_break_time_shift' => $break_time,
                    'total_this_shift' => $totalShiftSales,
                    'avg_shift_sales' => $avg,
                    'shift_avg' => $shiftAvg,
                    'total_period' => $total_period_sales,
                    //'price_minute_awemp' => $modelPrice,
                    'logout_time' => $time]);
        }

        return response()->json([
            'success' => true,
            'trainer_id' => $trainer_id,
            'model' => $model,
            'text' => $text

        ]);


    }

    public function getPrice(){

        $trainerName = 'AvahRise';

        $arr = array([$trainerName,$trainerName]);
        $support = $arr->toJson();

    }

    function total_time_shift_w($modelname, $fLogin, $lLogin){

        date_default_timezone_set('Europe/Bucharest');

        $arrF = explode(':',date('H:m:s', $fLogin));
        $arrE = explode(':',date('H:m:s', $lLogin));

        $startd = date('Y-m-d', $fLogin).'T'.$arrF[0].'%3A'.$arrF[1].'%3A'.$arrF[2].'%2B03%3A00';
        $endd = date('Y-m-d', $lLogin).'T'.$arrE[0].'%3A'.$arrE[1].'%3A'.$arrE[2].'%2B03%3A00';

        $time = 0;

        $authorization = "Authorization: Bearer 406ad45b5ed5748abb871cc99ef69bc45d0ad2779f9034f91de5af254f8a9902";
        $authorization1 = "Authorization: Bearer 8c23e7366a3e7096a30914edb145efc95fe241cd5be682a5ec1a366ca01e6c70";

        $url = "https://partner-api.modelcenter.jasmin.com/v1/reports/performers?fromDate=$startd&toDate=$endd&screenNames[]=$modelname&reports[]=working-time";

        $res = $this->curlAuth($authorization, $url);


        if (array_key_exists('errors', $res)){
            $res = $this->curlAuth($authorization1, $url);
        }

        if (!array_key_exists('errors', $res)){

            $restotaltime =($res['data'][0]['workingTime']);
            $totaltime = $restotaltime['vip_show']['value'] + $restotaltime['pre_vip_show']['value'] + $restotaltime['private']['value'] + $restotaltime['free']['value'];

            $time = $totaltime;
        }
        return $time;

    }

    /*function total_time_shift_w2(){

        date_default_timezone_set('Europe/Bucharest');

        $arrF = explode(':',date('H:m:s', $fLogin));
        $arrE = explode(':',date('H:m:s', $lLogin));

        $startd = date('Y-m-d', $fLogin).'T'.$arrF[0].'%3A'.$arrF[1].'%3A'.$arrF[2].'%2B03%3A00';
        $endd = date('Y-m-d', $lLogin).'T'.$arrE[0].'%3A'.$arrE[1].'%3A'.$arrE[2].'%2B03%3A00';

        $time = 0;

        $authorization = "Authorization: Bearer 406ad45b5ed5748abb871cc99ef69bc45d0ad2779f9034f91de5af254f8a9902";
        $authorization1 = "Authorization: Bearer 8c23e7366a3e7096a30914edb145efc95fe241cd5be682a5ec1a366ca01e6c70";

        $url = "https://partner-api.modelcenter.jasmin.com/v1/reports/performers?fromDate=$startd&toDate=$endd&screenNames[]=$modelname&reports[]=working-time";

        $res = $this->curlAuth($authorization, $url);

        if (array_key_exists('errors', $res)){
            $res = $this->curlAuth($authorization1, $url);
        }

        if (!array_key_exists('errors', $res)){

            $restotaltime =($res['data'][0]['workingTime']);
            $totaltime = $restotaltime['vip_show']['value'] + $restotaltime['pre_vip_show']['value'] + $restotaltime['private']['value'] + $restotaltime['free']['value'];

            $time = $totaltime;
        }

        return $time;

    }*/

    function login_time_period_after2($modelname, $fLogin, $lLogin){

        $totaltime = 0;

        $startd = $fLogin.'T00%3A00%3A00%2B02%3A00';
        $endd = date('Y-m-d', $lLogin).'T23%3A59%3A59%2B02%3A00';


        $authorization = "Authorization: Bearer 406ad45b5ed5748abb871cc99ef69bc45d0ad2779f9034f91de5af254f8a9902";
        $authorization1 = "Authorization: Bearer 8c23e7366a3e7096a30914edb145efc95fe241cd5be682a5ec1a366ca01e6c70";

        $url = "https://partner-api.modelcenter.jasmin.com/v1/reports/performers?fromDate=$startd&toDate=$endd&screenNames[]=$modelname&reports[]=general-overview";

        $res = $this->curlAuth($authorization, $url);



        if (array_key_exists('errors', $res)){
            $res = $this->curlAuth($authorization1, $url);
        }

        if (!array_key_exists('errors', $res)){

            $restotaltime =($res['data'][0]['total']);
            $totaltime = $restotaltime['workTime']['value'];
        }

        return $totaltime;

    }

    function login_earnings_period_after2($modelname, $fLogin, $lLogin){

        $totaltime = 0;

        $startd = $fLogin.'T00%3A00%3A00%2B02%3A00';
        $endd = date('Y-m-d', $lLogin).'T23%3A59%3A59%2B02%3A00';


        $authorization = "Authorization: Bearer 406ad45b5ed5748abb871cc99ef69bc45d0ad2779f9034f91de5af254f8a9902";
        $authorization1 = "Authorization: Bearer 8c23e7366a3e7096a30914edb145efc95fe241cd5be682a5ec1a366ca01e6c70";

        $url = "https://partner-api.modelcenter.jasmin.com/v1/reports/performers?fromDate=$startd&toDate=$endd&screenNames[]=$modelname&reports[]=general-overview";

        $res = $this->curlAuth($authorization, $url);



        if (array_key_exists('errors', $res)){
            $res = $this->curlAuth($authorization1, $url);
        }

        if (!array_key_exists('errors', $res)){

            $restotaltime =($res['data'][0]['total']);
            $totaltime = $restotaltime['earnings']['value'];
        }

        return $totaltime;

    }


    function total_period_sales($modelname, $fLogin, $lLogin){

        $totaltime = 0;

        $d_arr = explode('-', $fLogin);
        $start_date = $d_arr[0].'-'.$d_arr[1].'-01';
        if ($d_arr[2] > 15) $start_date = $d_arr[0].'-'.$d_arr[1].'-16';

        $startd = $start_date.'T00%3A00%3A00%2B02%3A00';
        $endd = date('Y-m-d', $lLogin).'T23%3A59%3A59%2B02%3A00';


        $authorization = "Authorization: Bearer 406ad45b5ed5748abb871cc99ef69bc45d0ad2779f9034f91de5af254f8a9902";
        $authorization1 = "Authorization: Bearer 8c23e7366a3e7096a30914edb145efc95fe241cd5be682a5ec1a366ca01e6c70";

        $url = "https://partner-api.modelcenter.jasmin.com/v1/reports/performers?fromDate=$startd&toDate=$endd&screenNames[]=$modelname&reports[]=general-overview";

        $res = $this->curlAuth($authorization, $url);



        if (array_key_exists('errors', $res)){
            $res = $this->curlAuth($authorization1, $url);
        }

        if (!array_key_exists('errors', $res)){

            $restotaltime =($res['data'][0]['total']);
            $totaltime = $restotaltime['earnings']['value'];
        }

        return $totaltime;

    }


    function login_time_period_after($modelname, $fLogin, $lLogin){

        $totaltime = 0;

        $d_arr = explode('-', $fLogin);
        $start_date = $d_arr[0].'-'.$d_arr[1].'-01';
        if ($d_arr[2] > 15) $start_date = $d_arr[0].'-'.$d_arr[1].'-16';

        $startd = $start_date.'T00%3A00%3A00%2B02%3A00';
        $endd = date('Y-m-d', $lLogin).'T23%3A59%3A59%2B02%3A00';


        $authorization = "Authorization: Bearer 406ad45b5ed5748abb871cc99ef69bc45d0ad2779f9034f91de5af254f8a9902";
        $authorization1 = "Authorization: Bearer 8c23e7366a3e7096a30914edb145efc95fe241cd5be682a5ec1a366ca01e6c70";

        $url = "https://partner-api.modelcenter.jasmin.com/v1/reports/performers?fromDate=$startd&toDate=$endd&screenNames[]=$modelname&reports[]=general-overview";

        $res = $this->curlAuth($authorization, $url);



        if (array_key_exists('errors', $res)){
            $res = $this->curlAuth($authorization1, $url);
        }

        if (!array_key_exists('errors', $res)){

            $restotaltime =($res['data'][0]['total']);
            $totaltime = $restotaltime['workTime']['value'];
        }

        return $totaltime;

    }

    function income_last_logout($modelname, $fLogin, $lLogin){

        $startd = date('Y-m-d\TH:m:sO', $lLogin);
        $endd = date('Y-m-d\TH:m:sO', $fLogin);
        $total = 0;

        $authorization = "Authorization: Bearer 406ad45b5ed5748abb871cc99ef69bc45d0ad2779f9034f91de5af254f8a9902";
        $authorization1 = "Authorization: Bearer 8c23e7366a3e7096a30914edb145efc95fe241cd5be682a5ec1a366ca01e6c70";

        $url = "https://partner-api.modelcenter.jasmin.com/v1/reports/performers?fromDate=$startd&toDate=$endd&screenNames[]=$modelname&reports[]=general-overview";

        $res = $this->curlAuth($authorization, $url);

        if (array_key_exists('errors', $res)){
            $res = $this->curlAuth($authorization1, $url);
        }

        if (!array_key_exists('errors', $res)){

            $restotaltime =($res['data'][0]['total']);
            $total = $restotaltime['earnings']['value'];

        }
        return $total;

    }

    function income_last_logout2(){

        $modelname = 'RebeccaBlussh';
        $fLogin = '1560726961';
        $lLogin = '1560718801';

        $startd = date('Y-m-d\TH:m:sO', $lLogin);
        $endd = date('Y-m-d\TH:m:sO', $fLogin);
        $total = 0;

        $authorization = "Authorization: Bearer 406ad45b5ed5748abb871cc99ef69bc45d0ad2779f9034f91de5af254f8a9902";
        $authorization1 = "Authorization: Bearer 8c23e7366a3e7096a30914edb145efc95fe241cd5be682a5ec1a366ca01e6c70";

        $url = "https://partner-api.modelcenter.jasmin.com/v1/reports/performers?fromDate=$startd&toDate=$endd&screenNames[]=$modelname&reports[]=general-overview";

        $res = $this->curlAuth($authorization, $url);

        if (array_key_exists('errors', $res)){
            $res = $this->curlAuth($authorization1, $url);
        }

        if (!array_key_exists('errors', $res)){

            $restotaltime =($res['data'][0]['total']);
            $total = $restotaltime['earnings']['value'];

        }
        return $total;

    }

    function time_online_period2() {

        $modelname = "RebeccaBlussh";
        $ttday = Carbon::now()->format('Y-m-d');

        $resFLogin = DB::table('models_timestamp')->where('modelname', $modelname)->where('last_status', '!=', 'offline')->whereDate('date', $ttday)->orderBy('status_start', 'asc')->first();
        if ($resFLogin) {
            $flogin = $resFLogin->status_start;
        }

        $date =$ttday;

        $flogin = Carbon::createFromTimestamp($flogin, 'Europe/Bucharest')->format('H:i:s');

        $hlogin = explode(':',$flogin);

        dd($hlogin);

        $d_arr = explode('-', $date);
        $start_date = $d_arr[0].'-'.$d_arr[1].'-01';
        $end_date = $date;
        if ($d_arr[2] > 15) $start_date = $d_arr[0].'-'.$d_arr[1].'-16';



        $startd = $start_date.'T00%3A00%3A00%2B02%3A00';
        //$endd = $end_date.'T23%3A59%3A59%2B02%3A00';
        //$endd = $flogin.'T23%3A59%3A59%2B02%3A00';
        $endd = $date.'T'.$hlogin[0].'%3A'.$hlogin[1].'%3A'.$hlogin[2].'%2B02%3A00';



        $time = 0;

        $authorization = "Authorization: Bearer 406ad45b5ed5748abb871cc99ef69bc45d0ad2779f9034f91de5af254f8a9902";
        $authorization1 = "Authorization: Bearer 8c23e7366a3e7096a30914edb145efc95fe241cd5be682a5ec1a366ca01e6c70";

        $url = "https://partner-api.modelcenter.jasmin.com/v1/reports/performers?fromDate=$startd&toDate=$endd&screenNames[]=$modelname&reports[]=working-time";

        $res = $this->curlAuth($authorization, $url);


        if (array_key_exists('errors', $res)){
            $res = $this->curlAuth($authorization1, $url);
        }

        if (!array_key_exists('errors', $res)){

            $restotaltime =($res['data'][0]['workingTime']);
            $totaltime = $restotaltime['vip_show']['value'] + $restotaltime['pre_vip_show']['value'] + $restotaltime['video_call']['value'] + $restotaltime['private']['value'] + $restotaltime['free']['value'] + $restotaltime['member']['value'];

            $time = $totaltime;
        }
        return $time;
    }

    function get_last_shif_end($modelname){

        //offline time is > 4h
        $breakPoint = 4 * 3600;
        $time = time();
        $resTime = DB::table('models_timestamp')->where('modelname', $modelname)->where('last_status', 'offline')->orderBy('status_start', 'DESC')->limit(10)->get();
        if ($resTime) {
            foreach($resTime as $row){
                if ( ($row->status_end - $row->status_start) > $breakPoint) {
                    $time = $row->status_start;
                }
            }
        }
        return $time;

    }

    function time_online_period($modelname, $date, $flogin) {

        $flogin = Carbon::createFromTimestamp($flogin, 'Europe/Bucharest')->format('H:i:s');
        $hlogin = explode(':',$flogin);
        $d_arr = explode('-', $date);
        $start_date = $d_arr[0].'-'.$d_arr[1].'-01';
        if ($d_arr[2] > 15) $start_date = $d_arr[0].'-'.$d_arr[1].'-16';


        $startd = $start_date.'T00%3A00%3A00%2B02%3A00';
        $endd = $date.'T'.$hlogin[0].'%3A'.$hlogin[1].'%3A'.$hlogin[2].'%2B02%3A00';

        $time = 0;

        $authorization = "Authorization: Bearer 406ad45b5ed5748abb871cc99ef69bc45d0ad2779f9034f91de5af254f8a9902";
        $authorization1 = "Authorization: Bearer 8c23e7366a3e7096a30914edb145efc95fe241cd5be682a5ec1a366ca01e6c70";

        $url = "https://partner-api.modelcenter.jasmin.com/v1/reports/performers?fromDate=$startd&toDate=$endd&screenNames[]=$modelname&reports[]=working-time";

        $res = $this->curlAuth($authorization, $url);


        if (array_key_exists('errors', $res)){
            $res = $this->curlAuth($authorization1, $url);
        }

        if (!array_key_exists('errors', $res)){

            $restotaltime =($res['data'][0]['workingTime']);
            $totaltime = $restotaltime['vip_show']['value'] + $restotaltime['pre_vip_show']['value'] + $restotaltime['video_call']['value'] + $restotaltime['private']['value'] + $restotaltime['free']['value'] + $restotaltime['member']['value'];

            $time = $totaltime;
        }
        return $time;
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


        public function chatHistoryInsert(Request $request){

            $fromId = $request->input('fromid');
            $toId = $request->input('toid');
            $message = $request->input('message');
            $timestamp = $request->input('timestamp');

        DB::table('chat_history')->insert(['fromID' => $fromId, 'toID' => $toId, 'message' => $message, 'time' => $timestamp]);


    }

    public function chatHistoryGet(Request $request){

        $trainer = $request->input('trainer');
        $model = $request->input('model');

        //$trainer = 'trainer1';
        //$model = 'DesignerMissy';

        $items = [$trainer, $model];

        $res = DB::table('chat_history')->whereIn('fromID', $items)->whereIn('toID', $items)->orderBy('time', 'asc')->get();


        return response()->json([
            'success' => true,
            'data' => $res,
            'model' => $model,
            'trainer' => $trainer

        ]);
    }



    public function modelShift ($modelname){

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


        return $resTime;


    }
    function addTrainer(Request $request){

        $name = $request->input('name');
        $email = $request->input('email');
        $profile = $request->input('profile');
        $password = $request->input('password');

        DB::table('trainer')->insert(["name" => $name, "email" => $email, "pass" => $password, "profile" => $profile, "mymodels" => '']);

        return response()->json([
            'success' => true,
            'data' => 'trainer added'
        ]);

    }

    function getTrainers(){

        $trainers = DB::table('trainer')->get();

        return response()->json([
            'success' => true,
            'data' => $trainers
        ]);
    }

    function getMyModelsTrainer(Request $request){

        $id = $request->input('id');
        $res = DB::table('trainer')->where('id',$id)->first();


            if (($res->mymodels !== null) || ($res->mymodels !== "")) {

                $mymodels = explode(',', $res->mymodels);

                $result = DB::table('models_free_chat')->whereIn('modelname', $mymodels)->pluck('status','modelname');

                $inShift = DB::table('model_shift_report')->whereIn('model', $mymodels)->whereDate('created_at', Carbon::today())->pluck('model');

            return response()->json([
                'success' => true,
                'data' => $result,
                'name' => $res->name,
                'shift' => $inShift
            ]);

        } else {
            return response()->json([
                'success' => false,
                'data' => null
            ]);
        }
    }


    function getMyModelsTrainerWeb(Request $request){

        $id = $request->input('id');
        $res = DB::table('trainer')->where('id',$id)->first();


        if (($res->mymodels !== null) || ($res->mymodels !== "")) {

            $mymodels = explode(',', $res->mymodels);

            $result = DB::table('models_free_chat')->whereIn('modelname', $mymodels)->pluck('status','modelname');
            $result2 = DB::table('models_free_chat')->whereIn('modelname', $mymodels)->get();
            foreach($result2 as $model){
                $model->shiftReport = $this->getShiftReportDataModule($model->modelname);
                $model->name = $model->modelname;
                $model->shift = $model->shiftReport ? true : false;
                $model->chat = "inactive";
            }

            //deprecated
            $inShift = DB::table('model_shift_report')->whereIn('model', $mymodels)->whereDate('created_at', Carbon::today())->pluck('model');

            return response()->json([
                'success' => true,
                'data_old' => $result,
                'shift_data' => $result2,
                'name' => $res->name,
                'shift' => $inShift
            ]);

        } else {
            return response()->json([
                'success' => false,
                'data' => null
            ]);
        }
    }

    function getMyModelsTrainerWeb2(){

        $id = 13;
        $res = DB::table('trainer')->where('id',$id)->first();


        if (($res->mymodels !== null) || ($res->mymodels !== "")) {

            $mymodels = explode(',', $res->mymodels);

            $result = DB::table('models_free_chat')->whereIn('modelname', $mymodels)->pluck('status','modelname');
            $result2 = DB::table('models_free_chat')->whereIn('modelname', $mymodels)->get();
            foreach($result2 as $model){
                $model->shiftReport = $this->getShiftReportDataModule($model->modelname);
                $model->name = $model->modelname;
                $model->shift = $model->shiftReport ? true : false;
                $model->chat = "inactive";
            }

            //deprecated
            $inShift = DB::table('model_shift_report')->whereIn('model', $mymodels)->whereDate('created_at', Carbon::today())->pluck('model');

            return response()->json([
                'success' => true,
                'data_old' => $result,
                'shift_data' => $result2,
                'name' => $res->name,
                'shift' => $inShift
            ]);

        } else {
            return response()->json([
                'success' => false,
                'data' => null
            ]);
        }
    }

    function getMyModelsTrainer2($email = "trainer1@studio20.com"){

        //$email = $request->input('email');

        $res = DB::table('trainer')
            ->where('email',$email)->first();

        if ($res){

            if (($res->mymodels !== null) || ($res->mymodels !== "")) {

                $mymodels = explode(',', $res->mymodels);

                $result = DB::table('models_free_chat')->whereIn('modelname', $mymodels)->pluck('status','modelname');


            }



            return response()->json([
                'success' => true,
                'data' => $res->mymodels
            ]);

        } else {
            return response()->json([
                'success' => false,
                'data' => null
            ]);
        }
    }


    function logtrainerdisconnect(Request $request){
        $trainer_id = $request->input('trainer_id');
        DB::table('trainer_log')->insert(['trainer_id' => $trainer_id, 'action' => 'disconnect', 'other' => '..']);


    }

    function test2(){

        $trainerId = 13;

        $shiftRes = DB::table('trainer_shift_report')->where('trainer_id',$trainerId)->orderByDesc('created_at')->whereDate('created_at', Carbon::today())->whereNull('end_date')->first();
        $shiftResYday = DB::table('trainer_shift_report')->where('trainer_id',$trainerId)->orderByDesc('created_at')->whereDate('created_at', Carbon::yesterday())->whereNull('end_date')->first();

        $shiftRes = $shiftRes ? $shiftRes : $shiftResYday;

        dd($shiftRes);

    }

    function authTrainer(Request $request){

        $trainer = $request->input('email');
        $pass = $request->input('pass');
        $studio = 'Studio Error';

        $res = DB::table('trainer')->where('email',$trainer)->first();

        //log auth
        if($res) {
            $action = ($res->pass === $pass) ? 'login-success' : 'login-fail';
            DB::table('trainer_log')->insert(['trainer_id' => $res->id, 'action' => $action, 'other' => $pass]);
        }

        if ( $res && ($res->pass === $pass) ) {


            if  ($res->studio == 0)  $studio = 'Home';
            $studios = DB::connection('mysql2')->table('studios')->where('id', $res->studio)->first();
            if ($studios) $studio = $studios->name;

            $res->studioName = $studio;

            $trainerId = $res->id;
            $shiftRes = DB::table('trainer_shift_report')->where('trainer_id',$trainerId)->orderByDesc('created_at')->whereDate('created_at', Carbon::today())->whereNull('end_date')->first();
            $shiftResYday = DB::table('trainer_shift_report')->where('trainer_id',$trainerId)->orderByDesc('created_at')->whereDate('created_at', Carbon::yesterday())->whereNull('end_date')->first();

            $shiftRes = $shiftRes ? $shiftRes : $shiftResYday;

            if ($shiftRes) {
                $shiftStart = $shiftRes || false;
                $shiftStartHour = $shiftRes->date;
                $shiftEnd = $shiftRes->end_date ? true : false;
                $shiftEndHour = $shiftRes->end_date ? $shiftRes->end_date : "00:00:00";
            } else {
                $shiftStart = false;
                $shiftStartHour = "00:00:00";
                $shiftEnd = false;
                $shiftEndHour = "00:00:00";
            }

            $proxy = DB::table('proxy_list')->orderBy('priority', 'desc')->first()->ip;


            return response()->json([
                'success' => true,
                'data' => $res,
                'shiftStart' => $shiftStart,
                'shiftStartHour' => $shiftStartHour,
                'shiftEnd' => $shiftEnd,
                'shiftEndHour' => $shiftEndHour,
                'proxy' => $proxy
            ]);
        } else {
            return response()->json([
                'success' => false,
                'data' => 'Error!'
            ]);
        }
    }


    public function getAllModels_old(){

        $res = DB::table('sync_models')->pluck('sync_Modelname');

        return response()->json([
            'success' => true,
            'data' => $res
        ]);


    }

    public function getAllModels(Request $request){

        $studio = $request->input('studio');
        $name = $request->input('name');

        $res = DB::table('models_free_chat')
            ->where('sync_models.studio', $studio)
            ->leftJoin('sync_models','models_free_chat.modelname','=','sync_models.sync_Modelname')
            ->pluck('status','modelname');

        return response()->json([
            'success' => true,
            'data' => $res,
            'name' => $name
        ]);


    }

    public function getAllModels2(){

        $studio = 16;

        $res = DB::table('models_free_chat')
            ->where('sync_models.studio', $studio)
            ->leftJoin('sync_models','models_free_chat.modelname','=','sync_models.sync_Modelname')
            ->pluck('status','modelname');

        return response()->json([
            'success' => true,
            'data' => $res
        ]);


    }


    public function sendReservations(Request $request){

        $model_id = $request->input('model_id');
        $period = $request->input('period');
        $hour = $request->input('hour');
        $daysSent = $request->input('days');

        if ($model_id){
            $reservations = DB::connection('mysql2')->table('reservations')->where('modelid',$model_id)->where('month',$period)->first();
            if (($reservations)){
                $result = $reservations->days;

                if (strlen($result) > 0) {
                    //add reservations
                    $result = $result . "," . $daysSent;
                    DB::connection('mysql2')->table('reservations')->where('modelid',$model_id)->where('month',$period)->update(['days' => $result, 'hour' => $hour]);

                } else {
                    //insert reservations
                    DB::connection('mysql2')->table('reservations')->where('modelid',$model_id)->where('month',$period)->update(['days' => $daysSent, 'hour' => $hour]);
                }

            } else {
                //create reservations
                DB::connection('mysql2')->table('reservations')->insert(['modelid' => $model_id, 'month' => $period,'days' => $daysSent, 'hour' => $hour]);

            }
        }
        $reservations = DB::connection('mysql2')->table('reservations')->where('modelid',$model_id)->where('month',$period)->first()->days;

        return response()->json([
            'success' => true,
            'data' => $reservations
        ]);


        }

    public function randomModels(){

        $random = DB::connection('mysql2')->table('models')->where('Status','Approved')->inRandomOrder()->limit(5)->pluck('Email');

        if($random){
            return response()->json([
                'success' => true,
                'data' => $random
            ]);
        } else {
            return response()->json([
                'success' => false,
                'data' => 'error'
            ]);
        }

    }

    public function websocket(){

        return view('websocket');

        }

}