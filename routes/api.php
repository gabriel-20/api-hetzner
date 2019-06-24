<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('login', 'PassportController@login');
Route::post('register', 'PassportController@register');

Route::middleware('auth:api')->group(function () {
    //Route::get('user', 'PassportController@details');

    Route::get('modelsapproved', 'ModelsController@getModelsApproved');

    Route::get('modelspriority', 'ModelsController@getModelsPriority');

    //get model that will receive next hit
    Route::get('getmodelforredirect', 'ModelsController@getModelForRedirect2');

    //add hits to model
    Route::put('addhits/{model}', 'ModelsController@addHitsToModel');

    //return hits today
    Route::get('gethits', 'ModelsController@getHitsLast30Days');
    Route::get('gethits2', 'ModelsController@getHitsLast30Days2');

    //Route::get('gethits2', 'ModelsController@getHitsLast30Days');

    //Route::get('gethitsnew', 'ModelsController@getHitsNewModels');

    Route::get('gethitsnew2', 'ModelsController@getHitsNewModels2');

    Route::get('getRealFirstLogin/{model}', 'ModelsController@getRealFirstLogin');



    //java app
    Route::post('modellogin', 'AppModelsController@apiLogin');

    Route::post('randommodels', 'AppModelsController@randomModels');

    Route::post('sendreservations', 'AppModelsController@sendReservations');

    //model start shift
    Route::post('modelstartshift', 'ModelsController@startShift');

    //get model info by modelname
    Route::get('getInfo/{modelname}', 'ModelsController@getInfo');

    //get model shift time
    Route::get('shifttime/{modelname}', 'ModelsController@modelShift');

    //get all models (trainer)
    Route::get('allmodels', 'AppModelsController@getAllModels');

    //trainer auth
    Route::post('authtrainer', 'AppModelsController@authTrainer');

    //trainer update mymodels
    Route::post('updatemymodels', 'AppModelsController@updateMyModelsTrainer');

    //trainer get mymodels
    //Route::post('getmymodels', 'AppModelsController@getMyModelsTrainer');
    Route::post('getmymodels', 'AppModelsController@getMyModelsTrainerWeb');

    //trainer get model info mymodels
    Route::post('trainergetmodelinfo', 'AppModelsController@trainerGetModelInfo');

    //get list with trainers
    Route::get('gettrainers', 'AppModelsController@getTrainers');

    //add trainer
    Route::post('addtrainer', 'AppModelsController@addTrainer');

    //get trainers from studio x
    Route::post('gettrainers', 'AppModelsController@getTrainersStudio');

    //remove model from trainer x -> to trainer y or 'remove'
    Route::post('removemodeltrainer', 'AppModelsController@removeModelFromTrainer');

    //insert chat history
    Route::post('insertchathistory', 'AppModelsController@chatHistoryInsert');

    //get chat history
    Route::post('getchathistory', 'AppModelsController@chatHistoryGet');

    //trainer call 'model is present' (start shift for model)
    Route::post('modelpresent', 'AppModelsController@modelpresent');

    //trainer call 'model end shift'
    Route::post('modelendshift', 'AppModelsController@modelendshift');

    //get selected model shfit report data
    Route::post('getshiftreportdata', 'AppModelsController@getshiftreportdata');

    //store selected model feedback
    Route::post('sendshiftreportdata', 'AppModelsController@sendshiftreportdata');

    //trainer start shift
    Route::post('trainerstartshift', 'AppModelsController@trainerstartshift');

    //trainer end shift
    Route::post('trainerendshift', 'AppModelsController@trainerendshift');

    //log trainer disconnect
    Route::post('logtrainerdisconnect', 'AppModelsController@logtrainerdisconnect');


    //------------------------------------------------------------------------------
    //cron jobs api
    //cron job to keep 'approved models'  updated (run every 1h)
    Route::get('updateapprovedmodels', 'ModelsController@updateApprovedModels');

    //cron job check models (run every minute)
    Route::get('checkmodelstatus', 'ModelsController@checkModelsStatus');

    //cron job calculate models average income - 30 days/shifts (run every day)
    Route::get('getShiftAverage', 'ModelsController@getShiftAverage');


});