<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    //以下為顯示所有民眾端身分資料(限2督導端)
    public function findAllPeople()
    {
        if(auth()->user()->role ==2){
            $data = DB::table("people")->get();
        return response($data,200);
        }else{
            return response("非督導端不得存取所有民眾資料！",401);
        }
    }
    //以下根據帳號顯示身分資料
    public function findPersonbyAccount(string $account)
    {
        if(auth()->user()->role !=2){
            return response("非督導端不得存取民眾資料！",401);
        }
        $data = DB::select('select id,name,phone,account,password from people where account = ?',[$account]);
        if(count($data) !== 0){
            $data= $data[0];
            $data->role = "民眾";
            return response()->json($data,200);
        }
         return response()->json(["message"=>"你所尋找的民眾資料找不到"],404);
    }
    //以下為查詢所有督導端身份資料
    public function index()
    { 
        if(auth()->user()->role ==2){
            $data = DB::table("admins")->get();
            return response($data,200);
        }else{
            return response("非督導端不得存取所有督導端資料！",401);
        }
    }
    //以下為查詢所有督導端身份資料(個別)
    public function show(string $id)
    {   
        if(auth()->user()->role !=2){
            return response("非督導端不得存取督導端資料！",401);
        }
        $data = DB::select('select id,name,account,password from admins where id = ?',[$id]);
        if(count($data) !== 0){
            $data= $data[0];
            $data->role = "民眾";
            return response()->json($data,200);
        }
         return response()->json(["message"=>"你所尋找的民眾資料id為 {$id} 找不到"],404);
    }
    //以下根據id顯示民眾身分資料
    public function findPersonbyId(string $id)
    {
        if(auth()->user()->role !=2){
            return response("非督導端不得存取民眾資料！",401);
        }
        $data = DB::select('select id,name,phone,account,password from people where id = ?',[$id]);
        if(count($data) !== 0){
            $data= $data[0];
            $data->role = "民眾";
            return response()->json($data,200);
        }
         return response()->json(["message"=>"你所尋找的民眾資料id為 {$id} 找不到"],404);
    }
}
