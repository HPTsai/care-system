<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AdminController extends Controller
{
    //以下為顯示所有民眾端身分資料(限2督導端)
    public function findAllPeople()
    {
       
    }
    //以下根據帳號顯示身分資料
    public function findPersonbyAccount(string $account)
    {
       
    }
    //以下為查詢所有督導端身份資料
    public function index()
    { 
       
    }
    //以下為查詢所有督導端身份資料(個別)
    public function show(string $id)
    {   
       
    }
    //以下根據id顯示民眾身分資料
    public function findPersonbyId(string $id)
    {
      
    }
    public function register(Request $request)
    {
        $messages = ["required"=>":attribute 是必填項目","phone.regex"=>"電話格式錯誤，必須是0X-XXXXXXX格式(市話)"];
        //驗證user資料
        $user = $request->all();
        $validator_user = Validator::make($user,[
            "account"=>"required",
            "password"=>"required",
            "password_confirmation"=>"required",
            "phone"=>"regex:/^0\d{1,2}-\d{6,8}$/"],$messages);
        if($validator_user->fails()){
            return response($validator_user->errors(),400);
        }
        if($user["password"]!=$user["password_confirmation"]){
            return response()->json([
                "message" => "密碼不一致，請重新輸入！",
            ],401);
        }
        $user_from_db = DB::select('select id from users where account=?',[$user["account"]]);
         if(count($user_from_db) > 0){
                return response()->json(["message"=>"你所使用的電話號碼(市話)/帳號已被註冊過，請重新輸入！"],409);
         }
        $bcrypted_password = bcrypt($request["password"]);
        $validator_admin = Validator::make($user,[
            "name"=>"required",
            "email"=>"required|email"
        ],$messages);
        if($validator_admin->fails()){
            return response($validator_admin->errors(),400);
        }
        DB::table("admins")->insert(['name'=>$user["name"],
                                    'email'=>$user["email"],
                                    'phone'=>array_key_exists('phone', $user)?$user["phone"]:null,
                                    'secondphone'=>array_key_exists('secondphone', $user)?$user["secondphone"]:null,
                                     'account'=>$user["account"],
                                     'create_date'=>now(),'modified_date'=>now()]);
        User::create([  'phone'=>array_key_exists('phone', $user)?$user["phone"]:null,
                        'account'=>$user["account"],
                        'is_verified'=>true,
                        "password" => $bcrypted_password,
                        "from" =>'0',
                        "role"=>'2']); 
        return response()->json(["message"=>"督導單位資料已建立！"],201);     
    }
    public function login(Request $request){
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
        $user_from_db = User::whereRaw('account = ?', [$user["account"]])->first();
        if(!empty($user_from_db)){
            $admin_data_from_db = DB::select('select id from admins where account = ?',[$user_from_db["account"]]);
            if(count($admin_data_from_db) == 0){
                return response()->json(["message" => "督導單位不存在！"],401);
            }
            //如果user物件存在，檢查password是否正確
            if(Hash::check($user["password"], $user_from_db->password)){
                $tokenResult = $user_from_db->createToken("mytoken");
                $accessToken = $tokenResult->accessToken;
                $refreshToken = Str::random(64);
                $expiresAt = Carbon::now()->addDays(1);
                // 插入資料到 oauth_refresh_tokens 表
                DB::table('oauth_refresh_tokens')->insert([
                'access_token_id' => $tokenResult->token->id, 
                'id' => $refreshToken,
                'expires_at' => $expiresAt,
                'revoked' => false]);
                return response()->json([
                    "message" => "督導單位登入成功！",
                    "accessToken" => $accessToken,
                    "refreshToken"=>$refreshToken
                ],200)->cookie('accessToken', $accessToken, 10)->cookie('refreshToken', $refreshToken, 43200);
            }else{
                return response()->json([
                    "message" => "督導單位密碼錯誤！",
                ],401);
            }
        }else{
            return response()->json([
                "message" => "督導單位不存在！",
            ],401);
        }
    }
    public function profile(){  
        $data = DB::select('select id,name,email,account,phone,secondphone from admins where account = ? ',[auth()->user()->account]);
        if(count($data) !== 0){
            $data= $data[0];
            $data->user_id = auth()->user()->id;
            $data->role = "督導";
            $data->is_verified = (bool)auth()->user()->is_verified;
            return response()->json($data,200);
        }else{
            return response()->json([],200);
        }
    }
    public function update(Request $request){
        if(auth()->user()->role!=2){
            return response()->json([
                "message" => "你目前的使用身分非督導端，無法編輯督導單位資料！"
            ],403);
        }
        $messages = ["required"=>":attribute 是必填項目","phone.regex"=>"電話格式錯誤，必須是0X-XXXXXXX格式(市話)"];
        $user = $request->all();
        $validator_user = Validator::make($user,[
            "email"=>"email",
            "phone"=>"regex:/^0\d{1,2}-\d{6,8}$/"
        ],$messages);
        if($validator_user->fails()){
            return response($validator_user->errors(),400);
        }
        DB::table("users")->where('account', auth()->user()->account)->update([
            'password'=>array_key_exists('password', $user)? bcrypt($user["password"]):auth()->user()->password,
            'phone'=>array_key_exists('phone', $user)? $user["phone"]:auth()->user()->phone,
            'updated_at'=>now()]);
        $admin = DB::select('select * from admins where account=? ',[auth()->user()->account])[0];
        DB::table("admins")->where('account', auth()->user()->account)->update([
                'name'=>array_key_exists('name', $user)? $user["name"]:$admin->name,
                'email'=>array_key_exists('email', $user)? $user["email"]:$admin->email,
                'phone'=>array_key_exists('phone', $user)? $user["phone"]:$admin->phone,
                'secondphone'=>array_key_exists('secondphone', $user)?$user["secondphone"]:$admin->secondphone,
                'modified_date'=>now()]);
        DB::table("oauth_refresh_tokens")->where('access_token_id',auth()->user()->token()->id)->delete();
        auth()->user()->tokens->each->delete();
        $user = User::whereRaw('account = ?', [auth()->user()->account])->first();
        $tokenResult = $user->createToken("mytoken");
        $accessToken = $tokenResult->accessToken;
        $refreshToken = Str::random(64);
        $expiresAt = Carbon::now()->addDays(1);
        // 插入資料到 oauth_refresh_tokens 表
        DB::table('oauth_refresh_tokens')->insert([
        'access_token_id' => $tokenResult->token->id, 
        'id' => $refreshToken,
        'expires_at' => $expiresAt,
        'revoked' => false]);
        return response()->json(["message" => "督導資料編輯成功！","accessToken" => $accessToken,"refreshToken"=>$refreshToken],200)->cookie('accessToken', $accessToken, 10)->cookie('refreshToken', $refreshToken, 43200);
    }
    public function destory(){
        if(auth()->user()->role!=2){
            return response()->json([
                "message" => "你目前的使用身分非督導端，不得刪除資料！"
            ],403);
        }
        DB::table("admins")->where('account',auth()->user()->account)->delete();
        DB::table("users")->where('account',auth()->user()->account)->delete();
        DB::table("oauth_refresh_tokens")->where('access_token_id',auth()->user()->token()->id)->delete();
        auth()->user()->token()->delete();
        return response()->json([
            "message" => "督導資料刪除成功！"
        ],200);
    }
    public function logout(){
        if(auth()->user()->role!=2){
            return response()->json([
                "message" => "你的身份非督導單位！無法登出",
            ],401);
            }
        DB::table("oauth_refresh_tokens")->where('access_token_id',auth()->user()->token()->id)->delete();
        auth()->user()->token()->delete();
        return response()->json(
        ["message" => "督導單位登出成功！"]
        ,200);
    }
}
