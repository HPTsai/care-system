<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SigninController extends Controller
{
    public function store(Request $request,string $id)
    {
        $application_from_db = DB::select('select id,carer_id,contract_id,selected_vendor_id from applications where id=?',[$id]);
        //檢查是否有該筆需求表紀錄
       if(count($application_from_db)== 0){
        return response()->json(["message"=>"需求表資料不存在，請重新輸入！"],404);
        }
        $application_from_db=$application_from_db[0];
        $messages = ["required"=>":attribute 是必填項目"];
        $data = $request->all();
        $validator_signin = Validator::make($data,[
            "service_address"=>"required"],$messages);
       if($validator_signin->fails()){
           return response($validator_signin->errors(),400);
       }
        if($application_from_db->contract_id ==null){
            return response(["message" =>"你目前的申請案件尚未建立合約，不得建立打卡資料！"],401);
        }
        $signin_id=DB::table("signins")->insertGetId([
            'is_signin'=>false,
            'is_signout'=>false,
            'signin_date'=>null,
            'signout_date'=>null,
            'application_id'=>$id,
            'carer_id'=>$application_from_db->carer_id,
            'company_id'=>$application_from_db->selected_vendor_id,
            'record_id'=>null,
            'service_address'=>$data["service_address"],
            'service_time'=>array_key_exists('service_time', $data)?$data["service_time"]:null,
            'create_date'=>now(),'modified_date'=>now()]);
      return response()->json(["message"=>"打卡資料建立成功！","id"=>$signin_id],201);
    }
    public function update(Request $request, string $id)
    {
        $data=$request->all();
        $signin_from_db = DB::select('select * from signins where id=?',[$id]);
        //檢查是否有該筆需求表紀錄
       if(count($signin_from_db)== 0){
        return response()->json(["message"=>"打卡資料不存在，請重新輸入！"],404);
        }
        $signin_from_db=$signin_from_db[0];
       if(auth()->user()->role ==0){
            return response(["message" =>"你使用的身份為民眾，不得編輯打卡資料！"],401);
        }
        DB::table("signins")->where("id",$id)->update([
              'is_signin'=>false,
              'signin_date'=>null,
              'is_signout'=>false,
              'signout_date'=>null,
              'service_address'=>array_key_exists('service_address', $data)?$data["service_address"]:$signin_from_db->service_address,
              'service_time'=>array_key_exists('service_time', $data)?$data["service_time"]:$signin_from_db->service_time,
              'modified_date'=>now()]);
      return response()->json(["message"=>"打卡資料編輯成功！"],200);
    }
    public function signin(string $id)
    {
        $signin_from_db = DB::select('select * from signins where id=?',[$id]);
        //檢查是否有該筆需求表紀錄
       if(count($signin_from_db)== 0){
        return response()->json(["message"=>"打卡資料不存在，請重新輸入！"],404);
        }
        $signin_from_db=$signin_from_db[0];
       if(auth()->user()->role ==0){
            return response(["message" =>"你使用的身份為民眾，不得編輯打卡資料！"],401);
        }
        $signin_date=now();
        $service_address = $signin_from_db->service_address;
        $service_time = $signin_from_db->service_time;
        $application_from_db = DB::select('select patient_id,carer_id,selected_vendor_id from applications where id=?',[$signin_from_db->application_id])[0];
        $patient_id= $application_from_db->patient_id;
        $carer_id= $application_from_db->carer_id;
        $company_id= $application_from_db->selected_vendor_id;
        $patient_name = DB::select('select name from patients where id=?',[$patient_id])[0]->name;
        $company_name = DB::select('select name from companies where id=?',[$company_id])[0]->name;
        $carer_name = null;
        if($carer_id != null){
            $carer_name =DB::select('select name from carers where id=?',[$carer_id])[0]->name;
        }
        DB::table("signins")->where("id",$id)->update([
              'is_signin'=>true,
              'signin_date'=>$signin_date,
              'modified_date'=>now()]);
      return response()->json(["message"=>"報到成功！",
                                "signin_date"=>$signin_date,
                                "patient_id"=>$patient_id,
                                "patient_name"=>$patient_name,
                                "carer_id"=>$carer_id,
                                "carer_name"=>$carer_name,
                                "company_id"=>$company_id,
                                "company_name"=>$company_name,
                                "service_address"=>$service_address,
                                "service_time"=>$service_time,
                                ],200);
    }
    public function signout(Request $request,string $id)
    {
        $signin_from_db = DB::select('select * from signins where id=?',[$id]);
        //檢查是否有該筆需求表紀錄
       if(count($signin_from_db)== 0){
        return response()->json(["message"=>"打卡資料不存在，請重新輸入！"],404);
        }
        $signin_from_db=$signin_from_db[0];
       if(auth()->user()->role ==0){
            return response(["message" =>"你使用的身份為民眾，不得編輯打卡資料！"],401);
        }
        $messages = ["required"=>":attribute 是必填項目"];
        $data = $request->all();
        $validator_record = Validator::make($data,[
            "fillin_date"=>"date",
            "weekday"=>'numeric|in:1,2,3,4,5,6,7',
        ],$messages);
       if($validator_record->fails()){
           return response($validator_record->errors(),400);
       }
        $record_id=DB::table("records")->insertGetId([
            'fillin_date'=>array_key_exists('fillin_date', $data)?$data["fillin_date"]:null,
            'weekday'=>array_key_exists('weekday', $data)?$data["weekday"]:null,
            'service_time_start'=>array_key_exists('service_time_start', $data)?$data["service_time_start"]:null,
            'service_time_end'=>array_key_exists('service_time_end', $data)?$data["service_time_end"]:null,
            'serivice_eating'=>array_key_exists('serivice_eating', $data)?$data["serivice_eating"]:null,
            'service_dressing'=>array_key_exists('service_dressing', $data)?$data["service_dressing"]:null,
            'serivice_bathing'=>array_key_exists('serivice_bathing', $data)?$data["serivice_bathing"]:null,
            'service_toileting'=>array_key_exists('service_toileting', $data)?$data["service_toileting"]:null,
            'service_hygiene'=>array_key_exists('service_hygiene', $data)?$data["service_hygiene"]:null,
            'service_shifting'=>array_key_exists('service_shifting', $data)?$data["service_shifting"]:null,
            'service_walking'=>array_key_exists('service_walking', $data)?$data["service_walking"]:null,
            'service_stair'=>array_key_exists('service_stair', $data)?$data["service_stair"]:null,
            'service_outing'=>array_key_exists('service_outing', $data)?$data["service_outing"]:null,
            'service_treatment'=>array_key_exists('service_treatment', $data)?$data["service_treatment"]:null,
            'service_companionship'=>array_key_exists('service_companionship', $data)?$data["service_companionship"]:null,
            'other_services'=>array_key_exists('other_services', $data)?$data["other_services"]:null,
            'user_signature'=>array_key_exists('user_signature', $data)?$data["user_signature"]:null,
            'special_matters'=>array_key_exists('special_matters', $data)?$data["special_matters"]:null,
            'service_hour'=>array_key_exists('service_hour', $data)?$data["service_hour"]:null,
            'carer_signature'=>array_key_exists('carer_signature', $data)?$data["carer_signature"]:null,
            'admin_signature'=>array_key_exists('admin_signature', $data)?$data["admin_signature"]:null,
            'create_date'=>now(),
            'modified_date'=>now()]);
        DB::table("signins")->where("id",$id)->update([
              'is_signout'=>true,
              'record_id'=>$record_id,
              'signout_date'=>now(),
              'modified_date'=>now()]);
      return response()->json(["message"=>"簽退成功！","record_id"=>$record_id],200);
    }
    public function show(string $id)
    {
        //檢查是否有該筆打卡紀錄
        $signin_data_db = DB::select('select id,is_signin,is_signout,signin_date,signout_date,application_id,carer_id,company_id,record_id,service_address,service_time from signins where id=?',[$id]);
        if(count($signin_data_db) == 0){
            return response()->json([],200);
        }
        $signin_data_db =$signin_data_db[0];
        $signin_data_db->is_signin=(bool)$signin_data_db->is_signin;
        $signin_data_db->is_signout=(bool)$signin_data_db->is_signout;
         return response()->json($signin_data_db,200);
    }
    public function findSignins(string $id)
    {
        $application_from_db = DB::select('select id from applications where id=?',[$id]);
        //檢查是否有該筆需求表紀錄
       if(count($application_from_db)== 0){
        return response()->json(["message"=>"需求表資料不存在，請重新輸入！"],404);
        }
        $signin_datas_db = DB::select('select id,is_signin,is_signout,signin_date,signout_date,application_id,carer_id,company_id,record_id,service_address,service_time from signins where application_id=?',[$id]);
        if(count($signin_datas_db) == 0){
            return response()->json([],200);
        }
        $signin_datas = [];
        foreach ($signin_datas_db as $key => $value){
            $signin_data = [];
            $signin_data["id"] =$value->id; 
            $signin_data["is_signin"]=(bool)$value->is_signin;
            $signin_data["signin_date"]=$value->signin_date;
            $signin_data["is_signout"]=(bool)$value->is_signout;
            $signin_data["signout_date"]=$value->signout_date;
            $signin_data["application_id"]=$value->application_id;
            $signin_data["carer_id"]=$value->carer_id;
            $signin_data["company_id"]=$value->company_id;
            $signin_data["record_id"]=$value->record_id;
            $signin_data["service_address"]=$value->service_address;
            $signin_data["service_time"]=$value->service_time;
            array_push($signin_datas,$signin_data);
        }
         return response()->json($signin_datas,200);
    }
    public function destroy(string $id)
    {
        DB::table("signins")->where("id",$id)->delete();
        return response()->json([
            "message" => "打卡資料刪除成功！"
        ],200);
    }
}
