<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class PersonController extends Controller
{
    public function index()
    {
        //print_r(auth()->user()->account);
        if(auth()->user()->role ==2){
            $data = DB::table("company")->get();
        return response($data,200);
        }else{
            return response("非督導端不得存取所有試辦單位/廠商資料！",401);
        }
        
    }
    public function findbyAccount(string $account)
    {
        // $data = DB::select('select name,phone,account,password from people where account = ?',[$account]);
        // if(count($data) !== 0){
        //     $data= $data[0];
        // }
        //  return response()->json(["message"=>"你所尋找的民眾資料id為 {$id} 找不到"],404);
    }
    //以下為新增一筆試辦單位/廠商端身份資料
    public function store(Request $request)
    { 
        print_r(Hash::needsRehash(auth()->user()->password));
        if(auth()->user()->role ==1 || auth()->user()->role == 2){
            $data = $request->all();
            $validator = Validator::make($data,[
                'name'=>'required','phone'=>'required','account'=>'required','password'=>'required','gui'=>"required",
            ]);
            if($validator->fails()){
                return response($validator->errors(),400);
            }
            DB::table("companies")->insert(['name'=>$data["name"],
                                        'account'=>$data["account"],
                                        'phone'=>$data["phone"],
                                        'password'=>Crypt::encryptString($data["password"]),
                                        'create_date'=>now(),'modified_date'=>now()]);
            return response("試辦單位/廠商資料已建立",201);     
        }else{
            return response("你使用的身分非試辦單位或是督導，不得建立試辦單位/廠商資料！",401);
        }
        
    }
    public function show(string $id)
    {
        $data = DB::select('select name,phone,account from people where id = ?',[$id]);
        if(count($data) !== 0){
            $data= $data[0];
            return response(json_encode($data),200); 
        }
         return response()->json(["message"=>"你所尋找的民眾資料id為 {$id} 找不到"],404);
    }
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
    public function destroy(string $id)
    {
        if(intval($id) === 0){
            return response()->json(["message"=>"你所尋找的民眾資料id必須為數字，請重新輸入！"],404);
        }
        DB::table("people")->where("id",$id)->delete();
        return response()->json(["message"=>"民眾資料刪除成功！"],202);
    }
}
