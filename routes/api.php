<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\CaseController;
//開放的routes
Route::post("register",[ApiController::class,"register"]);
Route::post("login",[ApiController::class,"login"]);
//被保護的routes
Route::group(["middleware"=>["auth:api"]],function(){

    Route::get("profile",[ApiController::class,"profile"]);
    Route::get("logout",[ApiController::class,"logout"]); 
    //以下為民眾端模組people的routes
    Route::get('people/accounts/{account}',[PersonController::class,'findbyAccount']);
    Route::put('people/{id}',[PersonController::class,'update']);
    Route::delete('people/{id}',[PersonController::class,'destroy']);
    Route::post('people',[PersonController::class,'store']);
    //以下為督導端模組admins的routes
    Route::get("admins/people",[AdminController::class,"findAllPeople"]);
    Route::get("admins/people/accounts/{account}",[AdminController::class,"findPersonbyAccount"]);
    Route::get("admins/people/{id}",[AdminController::class,"findPersonbyId"]);
    Route::post("admins",[AdminController::class,"store"]);
    Route::get("admins",[AdminController::class,"index"]);
    Route::get("admins/{id}",[AdminController::class,"show"]);
    //以下為需求書模組applications的routes
    Route::post("applications",[ApplicationController::class,"store"]);
    Route::get("applications",[ApplicationController::class,"index"]);
    Route::get("applications/{id}",[ApplicationController::class,"show"]);
    Route::put("applications/{id}",[ApplicationController::class,"update"]);
    Route::delete("applications/{id}",[ApplicationController::class,"destroy"]);
    //以下為服務案件模組cases的routes
    Route::post("cases",[CaseController::class,"store"]);
    Route::get("cases",[CaseController::class,"index"]);
    Route::get("cases/{id}",[CaseController::class,"show"]);
    Route::put("cases/{id}",[CaseController::class,"update"]);
    Route::delete("cases/{id}",[CaseController::class,"destroy"]);
});
