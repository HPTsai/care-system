<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Carbon\Carbon;

class AuthController extends Controller
{
    
    public function checktoken()
    {
        $access_id =auth()->user()->token()->id;
        $oauth_access_tokens_data = DB::select('select expires_at from oauth_access_tokens where id = ? ',[$access_id]); 
        if(count($oauth_access_tokens_data)==0){
            return response()->json(["message"=>"未經授權驗證的token，請重新登入！"],401);
        }
        $oauth_refresh_tokens_data = DB::select('select expires_at from oauth_refresh_tokens where access_token_id = ? ',[$access_id]);
        $expireAt=Carbon::parse($oauth_access_tokens_data[0]->expires_at);
        $refreshToken_expireAt=Carbon::parse($oauth_refresh_tokens_data[0]->expires_at);
        if($expireAt->isBefore(now())){
            auth()->user()->token()->revoke();
            if($refreshToken_expireAt->isBefore(now())){
                DB::table("oauth_access_tokens")->where('id',auth()->user()->token()->id)->delete();
                DB::table("oauth_refresh_tokens")->where('access_token_id',auth()->user()->token()->id)->delete();
            }           
            return response()->json(["message"=>"你的token已過期，請重新登入！"],401);
        }else{
            return response()->json(["message"=>"token驗證成功！"],200);
        }
    }
    public function getNewToken(Request $request)
    {
        $messages = ["required"=>":attribute 是必填項目"];
        //驗證user資料
        $refreshToken_data = $request->all();
        $validator_refreshToken_data = Validator::make($refreshToken_data,[
            "refreshToken" => "required",
           ],$messages);
        if($validator_refreshToken_data->fails()){
            return response($validator_refreshToken_data->errors(),400);
        }
        $oauth_refresh_token_data = DB::select('select access_token_id,expires_at from oauth_refresh_tokens where id = ? ',[$refreshToken_data["refreshToken"]]);
        if(count($oauth_refresh_token_data)==0){
            return response()->json(["message"=>"未經授權驗證的refreshToken，請重新登入！"],401);
        }
        $refreshToken_expireAt=Carbon::parse($oauth_refresh_token_data[0]->expires_at);
        $access_token_id=$oauth_refresh_token_data[0]->access_token_id;
        if($refreshToken_expireAt->isBefore(now())){
            DB::table("oauth_refresh_tokens")->where('id',$refreshToken_data["refreshToken"])->delete();
            DB::table("oauth_access_tokens")->where('id',$access_token_id)->delete();
            return response()->json(["message"=>"未經授權驗證的refreshToken，請重新登入！"],401);
        }
        $oauth_access_token_data = DB::select('select user_id from oauth_access_tokens where id = ? ',[$access_token_id]);
        if(count($oauth_access_token_data)==0){
            DB::table("oauth_refresh_tokens")->where('id',$refreshToken_data["refreshToken"])->delete();
            return response()->json(["message"=>"未經授權驗證的refreshToken，請重新登入！"],401);
        }
        $user_id = $oauth_access_token_data[0]->user_id;
        $user_data = DB::select('select account from users where id = ? ',[$user_id]);
        if(count($user_data)==0){
            return response()->json(["message"=>"未經授權驗證的refreshToken，請重新登入！"],401);
        }
        $user_account = $user_data[0]->account;
        DB::table("oauth_access_tokens")->where('id',$access_token_id)->delete();
        $user = User::whereRaw('account = ?', [$user_account])->first();
        $tokenResult = $user->createToken("mytoken");
        $accessToken = $tokenResult->accessToken;
        // 更新資料到 oauth_refresh_tokens 表
        DB::table('oauth_refresh_tokens')->where('id', $refreshToken_data["refreshToken"])->update([
        'access_token_id' => $tokenResult->token->id]);
        return response()->json(["accessToken"=>$accessToken],200)->cookie('accessToken', $accessToken, 10);
    }
}
