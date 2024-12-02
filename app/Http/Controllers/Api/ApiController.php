<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\PersonController;
use Illuminate\Support\Facades\DB;


class ApiController extends Controller
{
    public function register(Request $request){
        // Validation
        $request->validate([
            "name" => "required|string",
            "account" => "required|string|unique:users",
            "password" => "required|confirmed",
            "role" => "required",
            "phone" => "required"
        ]);
        // Create User
        $bcrypted_password = bcrypt($request->password);
        User::create([
            "name" => $request->name,
            "account" => $request->account,
            "password" => $bcrypted_password,
            "phone"=>$request->phone,
            "role"=>$request->role
        ]);
        return response()->json(["message"=>"使用者資料已註冊成功！！"],201);
    }
    public function login(Request $request){
        $request->validate([
            "account" => "required|string",
            "password" => "required"
        ]);
        // User object
        $user = User::where("account", $request->account)->first();
        if(!empty($user)){

            // User exists
            if(Hash::check($request->password, $user->password)){

                // Password matched
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
                "message" => "使用者email不存在！",
            ],401);
        }
    }
    public function profile(){
        $token_Data = auth()->user();
        return response($token_Data,200); 

    }
    public function update(Request $request){
        
    }
    public function logout(){
        $token = auth()->user()->token();
        $token->revoke();
        return response()->json([
            "message" => "使用者已登出成功！"
        ],200);
    }
}
