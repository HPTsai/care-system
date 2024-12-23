<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\User;

class CarerController extends Controller
{
    public function index()
    {
        $carers_from_db = DB::select('select id,certificate_id,company_id,name,nationality,phone,email,address,languages from carers');
        if(count($carers_from_db) == 0){
            return response()->json([],200);
        }
        $carers_datas=[];
        foreach ($carers_from_db as $key => $value){
            $user_id = DB::select('select id from users where account=?',[$value->certificate_id])[0]->id;
            $carers_data=[];
            $carers_data["id"]=$value->id;
            $carers_data["user_id"] = $user_id;
            $carers_data["role"] = "陪伴員";
            $carers_data["certificate_id"]=$value->certificate_id;
            $carers_data["account"]=$value->certificate_id;
            $carers_data["company_id"]=$value->company_id;
            $carers_data["name"]=$value->name;
            $carers_data["nationality"]=$value->nationality;
            $carers_data["phone"]=$value->phone;
            $carers_data["email"]=$value->email;
            $carers_data["address"]=$value->address;
            $carers_data["languages"]=$value->languages;
            array_push($carers_datas,$carers_data);
        }
        return  response()->json($carers_datas,200);
    }
    public function store(Request $request)
    {
        $messages = ["required"=>":attribute 是必填項目"]; 
        if(auth()->user()->role !=1){
          return response(["message" =>"你使用的身分非試辦單位/廠商，不得建立陪伴員資料！"],401);
        }
        $data = $request->all();
        //驗證資料
        $messages = ["required"=>":attribute 是必填項目"]; 
        $validator_carer = Validator::make($data,[
            "certificate_id"=>"required",
            "name"=>"required",
            "nationality"=>"required",
            "phone"=>"required",
            "address"=>"required"],$messages);
       if($validator_carer->fails()){
           return response($validator_carer->errors(),400);
       }
       $company_id = DB::select('select id from companies where account = ? or phone=?',[auth()->user()->account,auth()->user()->phone])[0]->id;
       $carer_from_db = DB::select('select id from carers where certificate_id = ?',[$data["certificate_id"]]);
         if(count($carer_from_db) > 0){
                return response()->json(["message"=>"你所申請的陪伴員資料已被註冊過，請重新輸入！"],409);
         }
        DB::table("carers")->insert([
                                    'certificate_id'=>$data["certificate_id"],
                                    'company_id'=>$company_id,
                                    'name'=>$data["name"],
                                    'nationality'=>$data["nationality"],
                                    'phone'=>$data["phone"],
                                    'email'=>array_key_exists('email', $data)?$data["email"]:null,
                                    'address'=>$data["address"],
                                    'languages'=>array_key_exists('languages', $data)?$data["languages"]:'國語',
                                    'create_date'=>now(),'modified_date'=>now()]);
    //隨機產生8碼的密碼(前2碼英文字母、後6碼數字)
    $letters = Str::random(2);
    $numbers = rand(100000, 999999);
    $password = $letters.$numbers;
    $bcrypted_password = bcrypt($password);
    DB::table("users")->insert([    'account'=>$data["certificate_id"],
                                    'password'=>$bcrypted_password,
                                    'from'=>'0',
                                    'is_verified'=>true,
                                    'role'=>'3',
                                    'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(["message"=>"陪伴員資料與帳號已建立！","account"=>$data["certificate_id"],"password"=>$password],201);  
    }
    public function login(Request $request)
    {
        $messages = ["required"=>":attribute 是必填項目"];
        //驗證user資料
        $user = $request->all();
        $validator_user = Validator::make($user,[
            "password" => "required",
            "account"=>"required"
           ],$messages);
        if($validator_user->fails()){
            return response($validator_user->errors(),400);
        }
        //取得user物件
        $user = User::whereRaw('account = ?',[$user["account"]])->first();
        if(!empty($user)){
            //如果user物件存在，檢查password是否正確
            if(Hash::check($request->password, $user->password)){
                $token = $user->createToken("mytoken")->accessToken;
                return response()->json([
                    "message" => "使用者登入成功！",
                    "token" => $token,
                ],200);
            }else{
                return response()->json([
                    "message" => "使用者密碼錯誤！",
                ],401);
            }
        }else{
            return response()->json([
                "message" => "使用者不存在！",
            ],401);
        }
    }
    public function logout()
    {
        auth()->user()->token()->delete();
        return response()->json(
        ["message" => "使用者登出成功！"]
        ,200);
    }
    public function update(Request $request, string $id)
    {
        $data = $request->all();
        //驗證資料
        $messages = ["required"=>":attribute 是必填項目"]; 
        $validator_carer = Validator::make($data,[
            "email"=>"email"],$messages);
       if($validator_carer->fails()){
           return response($validator_carer->errors(),400);
       }
       //確認資料是否存在
       $company_id = DB::select('select id from companies where account = ? or phone=?',[auth()->user()->account,auth()->user()->phone])[0]->id;
       $carer_from_db = DB::select('select name,certificate_id,nationality,phone,email,address,languages from carers where id = ? and company_id=?',[$id,$company_id]);
       if(count($carer_from_db) == 0){
        return response()->json(["message"=>"陪伴員資料不存在或是非本試辦單位員工，無法編輯陪伴員資料！"],404);
        }
        $carer_from_db=$carer_from_db[0];
        $certificate_id =$carer_from_db->certificate_id;
        //更新carers表資料
        DB::table("carers")->where('id',$id)->update([
            'name'=>array_key_exists("name",$data)? $data["name"]:$carer_from_db->name,
            'nationality'=>array_key_exists("nationality",$data)? $data["nationality"]:$carer_from_db->nationality,
            'phone'=>array_key_exists("phone",$data)? $data["phone"]:$carer_from_db->phone,
            'email'=>array_key_exists("email",$data)? $data["email"]:$carer_from_db->email,
            'address'=>array_key_exists("address",$data)? $data["address"]:$carer_from_db->address,
            'languages'=>array_key_exists("languages",$data)? $data["languages"]:$carer_from_db->languages,
            'modified_date'=>now()]);
        //更新users表資料
        $user_from_db=DB::select('select password from users where account=?',[$certificate_id])[0];
        DB::table("users")->where('account',$certificate_id)->update([
            'password'=>array_key_exists("password",$data)? bcrypt($data["password"]):$user_from_db->password,
            'updated_at'=>now()]);
            return response()->json(["message"=>"陪伴員資料編輯成功！"],200);
    }
    public function profile(){
        $carer_from_db = DB::select('select id,certificate_id,company_id,name,nationality,phone,email,address,languages from carers where certificate_id=?',[auth()->user()->account])[0];
        $carer_from_db->user_id = auth()->user()->id;
        $carer_from_db->role="陪伴員";
        $carer_from_db->account=$carer_from_db->certificate_id;
        return  response()->json($carer_from_db,200);
    }
    public function show(string $id)
    {
        $carers_from_db = DB::select('select id,certificate_id,company_id,name,nationality,phone,email,address,languages from carers where id=?',[$id]);
        if(count($carers_from_db) == 0){
            return response()->json([],200);
        }
        $carers_from_db=$carers_from_db[0];
        $user_id = DB::select('select id from users where account=?',[$carers_from_db->certificate_id])[0]->id;
        $carers_from_db->user_id=$user_id;
        $carers_from_db->role="陪伴員";
        $carers_from_db->account=$carers_from_db->certificate_id;
        return  response()->json($carers_from_db,200);
    }
    public function destroy(string $id)
    {
        if(auth()->user()->role!=1){
            return response()->json([
                "message" => "你目前的使用身分非試辦單位端/廠商，不得刪除該陪伴員資料！"
            ],403);
        }
        $carer_from_db = DB::select('select certificate_id from carers where id=?',[$id]);
        if(count($carer_from_db) == 0){
            return response()->json([],200);
        }
        $certificate_id=$carer_from_db[0]->certificate_id;
        DB::table("carers")->where("id",$id)->delete();
        DB::table("users")->where("account",$certificate_id)->delete();
        return response()->json([
            "message" => "陪伴員刪除成功！"
        ],200);
    }
    public function findCarersByCompany_id(string $id)
    {
        $carers_from_db = DB::select('select id,certificate_id,company_id,name,nationality,phone,email,address,languages from carers where company_id=?',[$id]);
        if(count($carers_from_db) == 0){
            return response()->json([],200);
        }
        $carers_datas=[];
        foreach ($carers_from_db as $key => $value){
            $user_id = DB::select('select id from users where account=?',[$value->certificate_id])[0]->id;
            $carers_data=[];
            $carers_data["id"]=$value->id;
            $carers_data["user_id"] = $user_id;
            $carers_data["role"] = "陪伴員";
            $carers_data["certificate_id"]=$value->certificate_id;
            $carers_data["account"]=$value->certificate_id;
            $carers_data["company_id"]=$value->company_id;
            $carers_data["name"]=$value->name;
            $carers_data["nationality"]=$value->nationality;
            $carers_data["phone"]=$value->phone;
            $carers_data["email"]=$value->email;
            $carers_data["address"]=$value->address;
            $carers_data["languages"]=$value->languages;
            array_push($carers_datas,$carers_data);
        }
        return  response()->json($carers_datas,200);
    }
}
