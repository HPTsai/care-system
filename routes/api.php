<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CarerController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\QualificationController;
use App\Http\Controllers\SigninController;
use App\Http\Controllers\RecordController;
//開放的routes
Route::post("register",[ApiController::class,"register"]);
Route::post("login",[ApiController::class,"login"]);
Route::post("setVerified",[ApiController::class,"setVerified"]);
Route::post("carers/login",[CarerController::class,"login"]);
//被保護的routes
Route::group(["middleware"=>["auth:api"]],function(){
    Route::get("profile",[ApiController::class,"profile"]);
    Route::get("logout",[ApiController::class,"logout"]);
    Route::put("update",[ApiController::class,"update"]);
    Route::delete("delete",[ApiController::class,"destory"]);
    Route::get("checktoken",[ApiController::class,"checktoken"]);
    //以下為督導端模組admins的routes
    Route::get("admins/people",[AdminController::class,"findAllPeople"]);
    Route::get("admins/people/accounts/{account}",[AdminController::class,"findPersonbyAccount"]);
    Route::get("admins/people/{id}",[AdminController::class,"findPersonbyId"]);
    Route::get("admins",[AdminController::class,"index"]);
    Route::get("admins/{id}",[AdminController::class,"show"]);
    //以下為需求書模組applications的routes
    Route::post("applications",[ApplicationController::class,"store"]);
    Route::get("applications",[ApplicationController::class,"index"]);
    Route::get("applications/{id}",[ApplicationController::class,"show"]);
    Route::put("applications/{id}",[ApplicationController::class,"update"]);
    Route::delete("applications/{id}",[ApplicationController::class,"destroy"]);
    Route::get("applications/company/{id}",[ApplicationController::class,"findApplicationsByCompany_id"]);
    Route::get("applications/company/{company_id}/id/{id}",[ApplicationController::class,"findApplicationByCompany_idAndId"]);
    Route::put("applications/service/{id}",[ApplicationController::class,"createServiceId"]);
    //以下為試辦單位端模組companies的routes
    Route::get("companies",[CompanyController::class,"index"]);
    Route::get("companies/{gui}",[CompanyController::class,"findCompanyByGui"]);
    Route::get("companies/city/{city}",[CompanyController::class,"findCompanyByCity"]);
    Route::get("companies/id/{id}",[CompanyController::class,"findCompanyById"]);
    //以下為陪伴員模組carers的routes
    Route::get("carers",[CarerController::class,"index"]);
    Route::get("carers/id/{id}",[CarerController::class,"show"]);
    Route::get("carers/profile",[CarerController::class,"profile"]);
    Route::get("carers/companies/{id}",[CarerController::class,"findCarersByCompany_id"]);
    Route::post("carers",[CarerController::class,"store"]);
    Route::get("carers/logout",[CarerController::class,"logout"]);
    Route::put("carers/id/{id}",[CarerController::class,"update"]);
    Route::delete("carers/id/{id}",[CarerController::class,"destroy"]);
    //以下為合約模組contracts的routes
    Route::get("contracts/applications/{id}",[ContractController::class,"show"]);
    Route::post("contracts/applications/{id}",[ContractController::class,"store"]);
    Route::put("contracts/applications/{id}",[ContractController::class,"update"]);
    Route::delete("contracts/applications/{id}",[ContractController::class,"destroy"]);
    //以下為合約模組qualifications的routes
    Route::get("qualifications/applications/{id}",[QualificationController::class,"show"]);
    Route::post("qualifications/applications/{id}",[QualificationController::class,"store"]);
    Route::put("qualifications/applications/{id}",[QualificationController::class,"update"]);
    Route::delete("qualifications/applications/{id}",[QualificationController::class,"destroy"]);
    //以下為合約模組signins的routes
    Route::post("signins/application/{id}",[SigninController::class,"store"]);
    Route::put("signins/id/{id}",[SigninController::class,"update"]);
    Route::put("signins/signin/id/{id}",[SigninController::class,"signin"]);
    Route::put("signins/signout/id/{id}",[SigninController::class,"signout"]);
    Route::get("signins/application/{id}",[SigninController::class,"findSignins"]);
    Route::get("signins/id/{id}",[SigninController::class,"show"]);
    Route::delete("signins/id/{id}",[SigninController::class,"destroy"]);
    //以下為合約模組records的routes
    Route::get("records/signin/{id}",[RecordController::class,"show"]);
    Route::post("records/signin/{id}",[RecordController::class,"store"]);
    Route::put("records/signin/{id}",[RecordController::class,"update"]);
    Route::delete("records/signin/{id}",[RecordController::class,"destroy"]);
});
