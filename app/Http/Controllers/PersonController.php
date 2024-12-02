<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class PersonController extends Controller
{
    //以下根據account帳號顯示身分資料
    public function findbyAccount(string $account)
    {
        if(auth()->user()->role !==0){
            return response("非民眾端不得存取民眾資料！",401);
        }
        $data = DB::select('select id,name,phone,account,password from people where account = ?',[$account]);
        if(count($data) !== 0){
            $data= $data[0];
            $data->role = "民眾";
            return response()->json($data,200);
        }
         return response()->json(["message"=>"你所尋找的民眾資料id為 {$id} 找不到"],404);
    }
    //以下為新增一筆民眾身份資料
    public function store()
    { 
        if(auth()->user()->role ==0){
            DB::table("people")->insert(['name'=>auth()->user()->name,
                                         'account'=>auth()->user()->account,
                                         'phone'=>auth()->user()->phone,
                                         'password'=>auth()->user()->password,
                                         'create_date'=>now(),'modified_date'=>now()]);
            return response()->json(["message"=>"民眾資料已建立！"],201);     
        }else{
            return response("你使用的身分非民眾，不得建立民眾資料！",401);
        }
    }
    //待修改
    public function update(Request $request, string $id)
    {
        if(intval($id) === 0){
            return response()->json(["message"=>"你所尋找的民眾資料id必須為數字，請重新輸入！"],404);
        }
        $person = DB::select('select name,phone,account,password from people where id = ?',[$id]);
        if(count($person) === 0){
            return response()->json(["message"=>"你所編輯的民眾資料id為 {$id} 找不到"],404);
        }
        $data =$request->all();
        $person = $person[0];
        DB::table("people")->where('id',$id)->update([
                                    'name'=>array_key_exists("name",$data)? $data["name"]:$person->name,
                                    'email'=>array_key_exists("account",$data)? $data["account"]:$person->account,
                                    'phone'=>array_key_exists("phone",$data)? $data["phone"]:$person->phone,
                                    'password'=>array_key_exists("password",$data)? Crypt::encryptString($data["password"]):$person->password,
                                    'modified_date'=>now()]);
        return response()->json(["message"=>"民眾資料編輯成功！"],200);
    }
    //待修改
    public function destroy(string $id)
    {
        if(intval($id) === 0){
            return response()->json(["message"=>"你所尋找的民眾資料id必須為數字，請重新輸入！"],404);
        }
        DB::table("people")->where("id",$id)->delete();
        return response()->json(["message"=>"民眾資料刪除成功！"],202);
    }
}