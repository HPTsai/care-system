<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\PersonController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class ApiController extends Controller
{
    public function register(Request $request){
        $messages = ["required"=>":attribute 是必填項目"];
        //驗證user資料
        $user = $request->all();
        $validator_user = Validator::make($user,[
            "account"=>"required",
            "password"=>"required",
            "from"=>"required|numeric|in:0,1,2",
            "role"=>"required|numeric|in:0,1,2"],$messages);
        if($validator_user->fails()){
            return response($validator_user->errors(),400);
        }
        if($user["from"] == 0){
            $user["phone"]=$user["account"];
            $user["account"]=null;
        }else{
            $user["phone"]=null;
        }
        $user_from_db = DB::select('select * from users where phone = ? or account=?',[$user["phone"],$user["account"]]);
         if(count($user_from_db) > 0){
                return response()->json(["message"=>"你所使用的電話號碼/手機/帳號已被註冊過，請重新輸入！"],409);
         }
        $bcrypted_password = bcrypt($request["password"]);
        //根據角色新增不同身分的帳號(0為民眾端、1為試辦單位端(廠商)、2為督導端(不得直接申請，請聯絡管理員))
        if($request["role"] == 0){
            $validator_people = Validator::make($user,[
                "name"=>"required",
                "email"=>"email"
            ],$messages);
            if($validator_people->fails()){
                return response($validator_people->errors(),400);
            }
            DB::table("people")->insert(['name'=>$user["name"],
                                        'email'=>array_key_exists('email', $user)?$user["email"]:null,
                                        'account'=>$user["account"],
                                        'phone'=>$user["phone"],
                                         'create_date'=>now(),'modified_date'=>now()]);
            User::create([  "phone" => $user["phone"],
                            'account'=>$user["account"],
                            "password" => $bcrypted_password,
                            "from" => $user["from"],
                            "role"=>$user["role"]]);                            
            return response()->json(["message"=>"民眾資料已建立！"],201);    
        }elseif($request["role"] == 2){
            $validator_admin = Validator::make($user,[
                "name"=>"required",
                "email"=>"required|email"
            ],$messages);
            if($validator_admin->fails()){
                return response($validator_admin->errors(),400);
            }
            DB::table("admins")->insert(['name'=>$user["name"],
                                        'email'=>array_key_exists('email', $user)?$user["email"]:null,
                                         'phone'=>$user["phone"],
                                         'account'=>$user["account"],
                                         'create_date'=>now(),'modified_date'=>now()]);
            User::create([  "phone" => $user["phone"],
                            'account'=>$user["account"],
                            "password" => $bcrypted_password,
                            "from" => $user["from"],
                            "role"=>$user["role"]]); 
            return response()->json(["message"=>"督導資料已建立！"],201);     
        }else{
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
                "quote_id"=>"required|numeric"
            ],$messages);
            if($validator_company->fails()){
                return response($validator_company->errors(),400);
            }
            DB::table("companies")->insert(['name'=>$user["name"],
            'gui'=>$user["gui"],
            'introduction'=>array_key_exists('introduction', $user)?$user["introduction"]:null,
            'phone'=>$user["phone"],
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
            'quote_id'=>$user["quote_id"],
            'create_date'=>now(),'modified_date'=>now()]);
            User::create([  "from" => $user["from"],
                            "password" => $bcrypted_password,
                            "phone" => $user["phone"],
                            'account'=>$user["account"],
                            "role"=>$user["role"]]);
            return response()->json(["message"=>"試辦單位資料已建立！"],201);
        }
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
        if(!array_key_exists('phone', $user)){
            $user["phone"] = null;
        }
        //取得user物件
        $user = User::whereRaw('account = ? or phone = ?', [$user["account"],$user["account"]])->first();
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
    public function profile(){
        if(auth()->user()->role==0){
            $data=null;
            if(auth()->user()->from==0){
                $data = DB::select('select id,name,email,account,phone from people where phone = ? ',[auth()->user()->phone]);
            }else{
                $data = DB::select('select id,name,email,account,phone from people where account = ? ',[auth()->user()->account]);
            }
            if(count($data) !== 0){
                $data= $data[0];
                $data->user_id = auth()->user()->id;
                $data->role = "民眾";
                $data->is_verified = (bool)auth()->user()->is_verified;
                return response()->json($data,200);
            }else{
                return response()->json(["message"=>"你所尋找的民眾資料找不到"],404);
            }
        }elseif(auth()->user()->role==1){
            $data=null;
            if(auth()->user()->from==0){
                $data = DB::select('select id,name,gui,introduction,email,account,phone,address_city,address_district,address_detail,logo_url,url,service_times,area,service_area,`rank`,quote_id from companies where phone = ? ',[auth()->user()->phone]);
            }else{
                $data = DB::select('select id,name,gui,introduction,email,account,phone,address_city,address_district,address_detail,logo_url,url,service_times,area,service_area,`rank`,quote_id from companies where account = ? ',[auth()->user()->account]);
            }
            if(count($data) !== 0){
                $data= $data[0];
                $data->user_id = auth()->user()->id;
                $data->role = "試辦單位(廠商)";
                $data->is_verified = (bool)auth()->user()->is_verified;
                return response()->json($data,200);
            }else{
                return response()->json(["message"=>"你所尋找的試辦單位(廠商)資料找不到"],404);
            }
        }else{
            $data=null;
            if(auth()->user()->from==0){
                $data = DB::select('select id,name,email,account,phone from admins where phone = ? ',[auth()->user()->phone]);
            }else{
                $data = DB::select('select id,name,email,account,phone from admins where account = ? ',[auth()->user()->account]);
            }
            if(count($data) !== 0){
                $data= $data[0];
                $data->user_id = auth()->user()->id;
                $data->role = "督導";
                $data->is_verified = (bool)auth()->user()->is_verified;
                return response()->json($data,200);
            }else{
                return response()->json(["message"=>"你所尋找的督導端資料找不到"],404);
            }
        }
    }
    public function update(Request $request){
        $messages = ["required"=>":attribute 是必填項目"];
        $phone=auth()->user()->phone;
        $user = $request->all();
        $validator_user = Validator::make($user,[
            "email"=>"email",
        ],$messages);
        if($validator_user->fails()){
            return response($validator_user->errors(),400);
        }
        //更新user表資料
        $bcrypted_password =null;
        if($request->has('password')){
            $bcrypted_password=bcrypt($request["password"]);
        }
         DB::table("users")->where('phone',auth()->user()->phone)->Where('account', auth()->user()->account)->update([
            'password'=>$request->has('password')? $bcrypted_password:auth()->user()->password,
            'updated_at'=>now()]);
        if(auth()->user()->role==0){
            $person = DB::select('select * from people where phone = ? or account=? ',[auth()->user()->phone,auth()->user()->account])[0];
            DB::table("people")->where('phone',auth()->user()->phone)->Where('account', auth()->user()->account)->update([
                'name'=>$request->has('name')? $request["name"]:$person->name,
                'email'=>$request->has('name')? $request["email"]:$person->email,
                'modified_date'=>now()]);
        }elseif(auth()->user()->role==1){
            $user = $request->all();
            $company = DB::select('select * from companies where phone = ? or account=?',[auth()->user()->phone,auth()->user()->account])[0];
            $validator_company = Validator::make($user,[
                "service_times"=>"numeric",
                "rank"=>"numeric|in:1,2,3,4,5",
                "quote_id"=>"numeric",
                "gui"=>'regex:/^[0-9]{8}$/'
            ],$messages);
            if($validator_company->fails()){
                return response($validator_company->errors(),400);
            }
            DB::table("companies")->where('phone',auth()->user()->phone)->Where('account', auth()->user()->account)->update([
                'gui'=>$request->has('gui')? $request["gui"]:$company->gui,
                'name'=>$request->has('name')? $request["name"]:$company->name,
                'introduction'=>$request->has('introduction')? $request["introduction"]:$company->introduction,
                'email'=>$request->has('email')? $request["email"]:$company->email,
                'phone'=>$request->has('phone')? $request["phone"]:$company->phone,
                'address_city'=>$request->has('address_city')? $request["address_city"]:$company->address_city,
                'address_district'=>$request->has('address_district')? $request["address_district"]:$company->address_district,
                'address_detail'=>$request->has('address_detail')? $request["address_detail"]:$company->address_detail,
                'logo_url'=>$request->has('logo_url')? $request["logo_url"]:$company->logo_url,
                'url'=>$request->has('url')? $request["url"]:$company->url,
                'service_times'=>$request->has('service_times')? $request["service_times"]:$company->service_times,
                'area'=>$request->has('area')? $request["area"]:$company->area,
                'service_area'=>$request->has('service_area')? $request["service_area"]:$company->service_area,
                'rank'=>$request->has('rank')? $request["rank"]:$company->rank,
                'quote_id'=>$request->has('quote_id')? $request["quote_id"]:$company->quote_id,
                'modified_date'=>now()]);
        }else{
            $admin = DB::select('select * from admins where phone = ? or account=? ',[auth()->user()->phone,auth()->user()->account])[0];
            DB::table("admins")->where('phone',auth()->user()->phone)->Where('account', auth()->user()->account)->update([
                'name'=>$request->has('name')? $request["name"]:$admin->name,
                'email'=>$request->has('name')? $request["email"]:$admin->email,
                'modified_date'=>now()]);
        }
        $token = auth()->user()->token();
        auth()->user()->tokens->each->delete();
        $user = User::whereRaw('account = ? or phone = ?', [auth()->user()->account,auth()->user()->phone])->first();
        $token = $user->createToken("mytoken")->accessToken;
        return response()->json(["message" => "使用者編輯成功！","token" => $token],200);
    }
    public function logout(){
        auth()->user()->token()->delete();
        return response()->json(
        ["message" => "使用者登出成功！"]
        ,200);
    }
    public function destory(){
        if(auth()->user()->role==2){
            return response()->json([
                "message" => "你目前的使用身分為督導端，不得刪除該使用者資料！"
            ],403);
        }
        $token = auth()->user()->token();
        
        if(auth()->user()->role==0){
            DB::table("people")->where("phone",auth()->user()->phone)->orWhere('account',auth()->user()->account)->delete();
        }elseif(auth()->user()->role==1){
            DB::table("companies")->where("phone",auth()->user()->phone)->orWhere('account',auth()->user()->account)->delete();
        }
        DB::table("users")->where("phone",auth()->user()->phone)->orWhere('account',auth()->user()->account)->delete();
        auth()->user()->token()->delete();
        return response()->json([
            "message" => "使用者刪除成功！"
        ],200);
    }
    public function checktoken()
    {
        $token = auth()->user()->token();
       return Passport::tokenHasExpired($token);
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
        if(!array_key_exists('phone', $user)){
            $user["phone"] = null;
        }
        //取得user物件
        $user_db = User::whereRaw('account = ? or phone = ?', [$user["account"],$user["account"]])->first();
        if(!empty($user_db)){
            //如果user物件存在，檢查password是否正確
            if(Hash::check($request->password, $user_db->password)){
                
                if($user_db->from==0){
                    DB::table("users")->where('phone',$user["account"])->update([
                        'is_verified'=>true,
                        'updated_at'=>now()]);
                }else{
                    DB::table("users")->where('account',$user["account"])->update([
                    'is_verified'=>true,
                    'updated_at'=>now()]);
                }
                return response()->json([
                    "message" => "使用者電話驗證成功！",
                ],200);
            }else{
                return response()->json([
                    "message" => "使用者密碼錯誤！無法完成電話驗證",
                ],401);
            }
        }else{
            return response()->json([
                "message" => "使用者不存在！",
            ],401);
        }
    }
}
