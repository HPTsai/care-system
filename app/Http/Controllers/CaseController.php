<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CaseController extends Controller
{
    public function index()
    {
        $case_datas = DB::select('select id,application_id,status,selected_company_id,service_id,user_id from cases where user_id = ?',[auth()->user()->id]);
        if(count($case_datas) == 0){
            return response()->json(["message"=>"你所尋找的服務案件資料找不到"],404);
            
        }
         return response()->json($case_datas,200);
        
    }
    public function store(Request $request)
    { 
        $messages = ["required"=>":attribute 是必填項目"]; 
        if(auth()->user()->role !=0){
           return response("你使用的身分非民眾，不得建立服務案件資料！",401);
        }
        $data = $request->all();
        $validator = Validator::make($data,[
            "application_id"=>"required|integer",
            "status"=>"integer",
            "selected_company_id"=>"integer",
            "service_id"=>"integer",
            "user_id"=>"required|integer",
        ],$messages);
        if($validator->fails()){
            return response($validator->errors(),400);
        }
        $application_datas = DB::select('select id from applications where id=?',[$data["application_id"]]);
        if(count($application_datas) == 0){
            return response()->json(["message"=>"你所尋找的需求表資料找不到，請先填寫需求表再新增服務案件！"],404);     
        }
        DB::table("cases")->insert(['application_id'=>$data["application_id"],
                                    'user_id'=>$data["user_id"],
                                    'status'=>array_key_exists("status",$data)?$data["status"]:1,
                                    'selected_company_id'=>array_key_exists("selected_company_id",$data)?$data["selected_company_id"]:null,
                                    'service_id'=>array_key_exists("service_id",$data)?$data["service_id"]:null,
                                    'create_date'=>now(),'modified_date'=>now()]);
       
        return response()->json(["message" => "服務案件資料新增成功！",],201);
    }
    public function update(Request $request, string $id)
    {
        $messages = ["required"=>":attribute 是必填項目"]; 
        if(auth()->user()->role !=0){
           return response("你使用的身分非民眾，不得建立服務案件資料！",401);
        }
        $data = $request->all();
        $validator = Validator::make($data,[
            "status"=>"integer",
            "selected_company_id"=>"integer",
            "service_id"=>"integer",
        ],$messages);
        if($validator->fails()){
            return response($validator->errors(),400);
        }
        $case = DB::select('select status,selected_company_id,service_id from cases where id=?',[$id]);
        if(count($case) == 0){
            return response()->json(["message"=>"你所編輯的服務案件資料id為 {$id} 找不到"],404);
        }
        $case = $case[0];
        DB::table("cases")->where('id',$id)->update([
            'status'=>array_key_exists("status",$data)? $data["status"]:$case->status,
            'selected_company_id'=>array_key_exists("selected_company_id",$data)? $data["selected_company_id"]:$case->selected_company_id,
            'service_id'=>array_key_exists("service_id",$data)? $data["service_id"]:$case->service_id,
            'modified_date'=>now()]);
        return response()->json(["message"=>"服務案件資料編輯成功！"],200);
    }
    public function show(string $id)
    {
        $case_datas = DB::select('select id,application_id,status,selected_company_id,service_id,user_id from cases where id = ?',[$id]);
        if(count($case_datas) == 0){
            return response()->json(["message"=>"你所尋找的服務案件資料找不到"],404);
            
        }
         return response()->json($case_datas,200);
    }
    public function destroy(string $id)
    {  
        DB::table("cases")->where("id",$id)->delete();
        return response()->json(["message"=>"服務案件資料刪除成功！"],202);
    }
}
