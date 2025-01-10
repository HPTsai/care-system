<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ApiController extends Controller
{
    public function register(Request $request){
        $messages = ["required"=>":attribute 是必填項目","phone.regex"=>"電話格式錯誤，必須是09XXXXXXXX格式(手機)","account.regex"=>"電話格式錯誤，必須是09XXXXXXXX格式(手機)"];
        //驗證user資料
        $user = $request->all();
        $phone= array_key_exists('phone', $user)?$user["phone"]:null;
        $validator_user = Validator::make($user,[
            "phone"=>'regex:/^09\d{8}$/',
            "password"=>'required',
            "from"=>"required|numeric|in:0,1,2",
            "role"=>"required|numeric|in:0,1,2"],$messages);
        if($validator_user->fails()){
            return response($validator_user->errors(),400);
        }
        if($user["from"] == 0){
            $validator_user_account = Validator::make($user,[
                "account"=>['required','regex:/^09\d{8}$/'],],$messages);
            if($validator_user_account->fails()){
                return response($validator_user_account->errors(),400);
            }
            $user["phone"] = $user["account"];
        }else{
            $validator_user_account = Validator::make($user,[
                "account"=>'required'],$messages);
            if($validator_user_account->fails()){
                return response($validator_user_account->errors(),400);
            }
        }
        $user_from_db = DB::select('select id from users where account=?',[$user["account"]]);
         if(count($user_from_db) > 0){
                return response()->json(["message"=>"你所使用的電話號碼(手機)/帳號已被註冊過，請重新輸入！"],409);
         }
        //根據角色新增不同身分的帳號(0為民眾端、1為試辦單位端(廠商)、2為督導端(不得直接申請，請聯絡管理員))
        $validator_people = Validator::make($user,[
            "name"=>"required",
            "email"=>"email",
            "phone"=>'regex:/^09\d{8}$/',
        ],$messages);
        if($validator_people->fails()){
            return response($validator_people->errors(),400);
        }
        DB::table("people")->insert(['name'=>$user["name"],
                                    'email'=>array_key_exists('email', $user)?$user["email"]:null,
                                    'account'=>$user["account"],
                                    'phone'=>array_key_exists('phone', $user)?$user["phone"]:null,
                                    'secondphone'=>array_key_exists('secondphone', $user)?$user["secondphone"]:null,
                                    'create_date'=>now(),'modified_date'=>now()]);
        User::create([  "phone" => array_key_exists('phone', $user)?$user["phone"]:null,
                        'account'=>$user["account"],
                        "password" => bcrypt($user["password"]),
                        "from" => $user["from"],
                        "role"=>$user["role"]]);                            
        return response()->json(["message"=>"民眾資料已建立！"],201);    

    }
    public function login(Request $request){
        $messages = ["required"=>":attribute 是必填項目"];
        //驗證user資料
        $user = $request->all();
        $validator_user = Validator::make($user,[
            "password" =>"required",
            "account"=>"required"
           ],$messages);
        if($validator_user->fails()){
            return response($validator_user->errors(),400);
        }
        //取得user物件
        $user_from_db = User::whereRaw('account = ?', [$user["account"]])->first();
        if(!empty($user_from_db)){
            $person_data_from_db = DB::select('select id from people where account = ?',[$user["account"]]);
            if(count($person_data_from_db) == 0){
                return response()->json(["message" => "使用者不存在！"],401);
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
                    "message" => "使用者登入成功！",
                    "accessToken" => $accessToken,
                    "refreshToken"=>$refreshToken
                ],200)->cookie('accessToken', $accessToken, 10)->cookie('refreshToken', $refreshToken, 43200);
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
    public function profile(){
        $data = DB::select('select id,name,email,account,phone,secondphone from people where account = ?',[auth()->user()->account]);
        if(count($data) == 0){
            return response()->json([],200);
        }else{
           $data= $data[0];
           $data->user_id = auth()->user()->id;
           $data->role = "民眾";
           $data->is_verified = (bool)auth()->user()->is_verified;
           return response()->json($data,200);
        }
    }
    public function update(Request $request){
        $messages = ["required"=>":attribute 是必填項目"];
        $user = $request->all();
        $validator_user = Validator::make($user,[
            "email"=>"email",
            "phone"=>"regex:/^09\d{8}$/"
        ],$messages);
        if($validator_user->fails()){
            return response($validator_user->errors(),400);
        }
        $person = DB::select('select * from people where account = ?',[auth()->user()->account])[0];
        //更新user表資料
        DB::table("users")->where('account', auth()->user()->account)->update([
            'password'=>array_key_exists('password', $user)? bcrypt($user["password"]):auth()->user()->password,
            'phone'=>array_key_exists('phone', $user)? $user["phone"]:$person->phone,
            'updated_at'=>now()]);
        DB::table("people")->where('account', auth()->user()->account)->update([
                'name'=>array_key_exists('name', $user)? $user["name"]:$person->name,
                'email'=>array_key_exists('email', $user)? $user["email"]:$person->email,
                'phone'=>array_key_exists('phone', $user)? $user["phone"]:$person->phone,
                'secondphone'=>array_key_exists('secondphone', $user)?$user["secondphone"]:$person->secondphone,
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
        return response()->json(["message" => "使用者編輯成功！","accessToken" => $accessToken,"refreshToken"=>$refreshToken],200)->cookie('accessToken', $accessToken, 10)->cookie('refreshToken', $refreshToken, 43200);
    }
    public function logout(){
        if(auth()->user()->role!=0){
            return response()->json([
                "message" => "你的身份非民眾！無法登出",
            ],401);
            }
        DB::table("oauth_refresh_tokens")->where('access_token_id',auth()->user()->token()->id)->delete();
        auth()->user()->token()->delete();
        return response()->json(
        ["message" => "使用者登出成功！"]
        ,200);
    }
    public function destory(){
        if(auth()->user()->role!=0){
            return response()->json([
                "message" => "你目前的使用身分非民眾，不得刪除資料！"
            ],403);
        }
        DB::table("people")->where('account',auth()->user()->account)->delete();
        DB::table("users")->where('account',auth()->user()->account)->delete();
        DB::table("oauth_refresh_tokens")->where('access_token_id',auth()->user()->token()->id)->delete();
        auth()->user()->token()->delete();
        return response()->json([
            "message" => "使用者刪除成功！"
        ],200);
    }

    public function setVerified(Request $request)
    {
        $messages = ["required"=>":attribute 是必填項目"];
        //驗證user資料
        $user = $request->all();
        $validator_user = Validator::make($user,[
            "account" => "required",
            "password" => "required",
           ],$messages);
        if($validator_user->fails()){
            return response($validator_user->errors(),400);

        }
        //取得user物件
        $user_from_db = User::whereRaw('account = ?',[$user["account"]])->first();
        if(!empty($user_from_db)){ 
            if($user_from_db->role!=0){
            return response()->json([
                "message" => "你的身份非民眾！無法完成電話驗證",
            ],401);
            }
            $company_data_from_db = DB::select('select id from people where account = ?',[$user_from_db["account"]]);
            if(count($company_data_from_db) == 0){
                return response()->json(["message" => "民眾資料不存在！"],401);
            }
            //如果user物件存在，檢查password是否正確
            if(Hash::check($user["password"], $user_from_db->password)){
                DB::table("users")->where('account',$user["account"])->update([
                    'is_verified'=>true,
                    'updated_at'=>now()]);
                return response()->json([
                    "message" => "民眾電話驗證成功！",
                ],200);
            }else{
                return response()->json([
                    "message" => "民眾密碼錯誤！無法完成電話驗證",
                ],401);
            }
        }else{
            return response()->json([
                "message" => "民眾資料不存在！",
            ],401);
        }
    }
}
