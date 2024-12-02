<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\Api\ApiController;

Route::get('/', function () {
    return view('welcome');
});