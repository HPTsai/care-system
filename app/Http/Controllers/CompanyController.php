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

class CompanyController extends Controller
{
    public function index()
    {
        $company_datas = DB::select('select id,gui,name,introduction,email,phone,secondphone,address_city,address_district,address_detail,logo_url,url,service_times,area,service_area,`rank`,quote_id from companies');
        if(count($company_datas) == 0){
            return response()->json([],200);
        }
         return response()->json($company_datas,200);
    }
    public function findCompanyByGui(string $gui)
    {
        $company_data = DB::select('select id,gui,name,introduction,email,phone,secondphone,address_city,address_district,address_detail,logo_url,url,service_times,area,service_area,`rank`,quote_id from companies where gui=?',[$gui]);
        if(count($company_data) == 0){
            return response()->json([],200);
        }
        $company_data=$company_data[0];
         return response()->json($company_data,200);
    }
    public function findCompanyById(string $id)
    {
        $company_data = DB::select('select id,gui,name,introduction,email,phone,secondphone,address_city,address_district,address_detail,logo_url,url,service_times,area,service_area,`rank`,quote_id from companies where id=?',[$id]);
        if(count($company_data) == 0){
            return response()->json([],200);
        }
        $company_data=$company_data[0];
         return response()->json($company_data,200);
    }
    public function findCompanyByCity(string $city)
    {
        $company_data = DB::select('select id,gui,name,introduction,email,phone,secondphone,address_city,address_district,address_detail,logo_url,url,service_times,area,service_area,`rank`,quote_id from companies where address_city=?',[$city]);
        if(count($company_data) == 0){
            return response()->json([],200);
        }
         return response()->json($company_data,200);
    }
    public function register(Request $request)
    {
        $messages = ["required"=>":attribute 是必填項目","phone.regex"=>"電話格式錯誤，必須是0X-XXXXXXX格式(市話)"];
        //驗證user資料
        $user = $request->all();
        $validator_user = Validator::make($user,[
            "account"=>"required",
            "password"=>"required",
            "phone"=>"required|regex:/^0\d{1,2}-\d{6,8}$/"
        ],$messages);
        if($validator_user->fails()){
            return response($validator_user->errors(),400);
        }
        $user_from_db = DB::select('select id from users where account=?',[$user["account"]]);
         if(count($user_from_db) > 0){
                return response()->json(["message"=>"你所使用的電話號碼(市話)/帳號已被註冊過，請重新輸入！"],409);
         }
        $bcrypted_password = bcrypt($request["password"]);
        $validator_company = Validator::make($user,[
            "name"=>"required",
            "email"=>"required|email",
            "gui"=>"required|min:8|max:8",
            "address_city"=>"required",
            "address_district"=>"required",
            "address_detail"=>"required",
            "service_times"=>"numeric",
            "service_area"=>"required",
            "area"=>"required",
            "rank"=>"numeric|in:1,2,3,4,5",
        ],$messages);
        if($validator_company->fails()){
            return response($validator_company->errors(),400);
        }
        DB::table("companies")->insert(['name'=>$user["name"],
        'gui'=>$user["gui"],
        'introduction'=>array_key_exists('introduction', $user)?$user["introduction"]:null,
        'phone'=>$user["phone"],
        'secondphone'=>array_key_exists('secondphone', $user)?$user["secondphone"]:null,
        'account'=>$user["account"],
        'email'=>$user["email"],
        'area'=>$user["area"],
        'address_city'=>$user["address_city"],
        'address_district'=>$user["address_district"],
        'address_detail'=>$user["address_detail"],
        'service_times'=>array_key_exists('service_times', $user)? $user["service_times"]:0,
        'service_area'=>$user["service_area"],
        'rank'=>array_key_exists('rank', $user)? $user["rank"]:5,
        'logo_url'=>array_key_exists('logo_url', $user)?$user["logo_url"]:null,
        'url'=>array_key_exists('url', $user)? $user["url"]:null,
        'create_date'=>now(),'modified_date'=>now()]);
        User::create([  "from" => '0',
                        "password" => $bcrypted_password,
                        "phone" => $user["phone"],
                        'account'=>$user["account"],
                        "role"=>'1']);
        return response()->json(["message"=>"試辦單位資料已建立！"],201);
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
        if(!empty($user)){
            $company_data_from_db = DB::select('select id from companies where account = ?',[$user_from_db["account"]]);
            if(count($company_data_from_db) == 0){
                return response()->json(["message" => "試辦單位不存在！"],401);
            }
            //如果user物件存在，檢查password是否正確
            if(Hash::check($user["password"], $user_from_db->password)){
                $tokenResult = $user_from_db->createToken("mytoken");
                $accessToken = $tokenResult->accessToken;
                $refreshToken = Str::random(64);
                $expiresAt = Carbon::now()->addDays();
                // 插入資料到 oauth_refresh_tokens 表
                DB::table('oauth_refresh_tokens')->insert([
                'access_token_id' => $tokenResult->token->id, 
                'id' => $refreshToken,
                'expires_at' => $expiresAt,
                'revoked' => false]);
                return response()->json([
                    "message" => "試辦單位登入成功！",
                    "accessToken" => $accessToken,
                    "refreshToken"=>$refreshToken
                ],200)->cookie('accessToken', $accessToken, 10)->cookie('refreshToken', $refreshToken, 43200);
            }else{
                return response()->json([
                    "message" => "試辦單位密碼錯誤！",
                ],401);
            }
        }else{
            return response()->json([
                "message" => "試辦單位不存在！",
            ],401);
        }
    }
    public function profile(){
        $data = DB::select('select id,name,gui,introduction,email,account,phone,secondphone,address_city,address_district,address_detail,logo_url,url,service_times,area,service_area,`rank`,quote_id from companies where account = ? ',[auth()->user()->account]);
        if(count($data) != 0){
            $data= $data[0];
            $data->user_id = auth()->user()->id;
            $data->role = "試辦單位(廠商)";
            $data->is_verified = (bool)auth()->user()->is_verified;
            return response()->json($data,200);
        }else{
            return response()->json([],200);
        }
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
            if($user_from_db->role!=1){
            return response()->json([
                "message" => "你的身份非試辦單位！無法完成電話驗證",
            ],401);
            }
            $company_data_from_db = DB::select('select id from companies where account = ?',[$user_from_db["account"]]);
            if(count($company_data_from_db) == 0){
                return response()->json(["message" => "試辦單位不存在！"],401);
            }
            //如果user物件存在，檢查password是否正確
            if(Hash::check($user["password"], $user_from_db->password)){
                DB::table("users")->where('account',$user["account"])->update([
                    'is_verified'=>true,
                    'updated_at'=>now()]);
                return response()->json([
                    "message" => "試辦單位電話驗證成功！",
                ],200);
            }else{
                return response()->json([
                    "message" => "試辦單位密碼錯誤！無法完成電話驗證",
                ],401);
            }
        }else{
            return response()->json([
                "message" => "試辦單位不存在！",
            ],401);
        }
    }
    public function update(Request $request){
        if(auth()->user()->role!=1){
            return response()->json([
                "message" => "你的身份非試辦單位！無法編輯試辦單位資料",
            ],401);
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
        //更新user表資料
        DB::table("users")->where('account', auth()->user()->account)->update([
            'password'=>array_key_exists('password', $user)? bcrypt($user["password"]):auth()->user()->password,
            'phone'=>array_key_exists('phone', $user)? $user["phone"]:auth()->user()->phone,
            'updated_at'=>now()]);
        $company = DB::select('select * from companies where phone = ? or account=?',[auth()->user()->phone,auth()->user()->account])[0];
        $validator_company = Validator::make($user,[
                "service_times"=>"numeric",
                "rank"=>"numeric|in:1,2,3,4,5",
                "gui"=>'regex:/^[0-9]{8}$/'
            ],$messages);
        if($validator_company->fails()){
                return response($validator_company->errors(),400);
        }
        DB::table("companies")->where('account', auth()->user()->account)->update([
                'gui'=>array_key_exists('gui', $user)? $user["gui"]:$company->gui,
                'name'=>array_key_exists('name', $user)? $user["name"]:$company->name,
                'introduction'=>array_key_exists('introduction', $user)? $user["introduction"]:$company->introduction,
                'email'=>array_key_exists('email', $user)? $user["email"]:$company->email,
                'phone'=>array_key_exists('phone', $user)? $user["phone"]:$company->phone,
                'address_city'=>array_key_exists('address_city', $user)? $user["address_city"]:$company->address_city,
                'address_district'=>array_key_exists('address_district', $user)? $user["address_district"]:$company->address_district,
                'address_detail'=>array_key_exists('address_detail', $user)? $user["address_detail"]:$company->address_detail,
                'logo_url'=>array_key_exists('logo_url', $user)? $user["logo_url"]:$company->logo_url,
                'url'=>array_key_exists('url', $user)? $user["url"]:$company->url,
                'service_times'=>array_key_exists('service_times', $user)? $user["service_times"]:$company->service_times,
                'area'=>array_key_exists('area', $user)? $user["area"]:$company->area,
                'service_area'=>array_key_exists('service_area', $user)? $user["service_area"]:$company->service_area,
                'rank'=>array_key_exists('rank', $user)? $user["rank"]:$company->rank,
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
        return response()->json(["message" => "試辦單位資料編輯成功！","accessToken" => $accessToken,"refreshToken"=>$refreshToken],200)->cookie('accessToken', $accessToken, 10)->cookie('refreshToken', $refreshToken, 43200);
    }
    public function destory(){
        if(auth()->user()->role!=1){
            return response()->json([
                "message" => "你目前的使用身分非試辦單位，不得刪除試辦單位資料！"
            ],403);
        }
        DB::table("companies")->where('account',auth()->user()->account)->delete();
        DB::table("users")->where('account',auth()->user()->account)->delete();
        DB::table("oauth_refresh_tokens")->where('access_token_id',auth()->user()->token()->id)->delete();
        auth()->user()->token()->delete();
        return response()->json([
            "message" => "督導資料刪除成功！"
        ],200);
    }
    public function logout(){
        if(auth()->user()->role!=1){
            return response()->json([
                "message" => "你的身份非試辦單位！無法登出",
            ],401);
        }
        DB::table("oauth_refresh_tokens")->where('access_token_id',auth()->user()->token()->id)->delete();
        auth()->user()->token()->delete();
        return response()->json(
        ["message" => "試辦單位登出成功！"]
        ,200);
    }
}