<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
class ApplicationController extends Controller
{
    public function index()
    {
        $db_application_datas = DB::select('select * from applications where user_id = ?',[auth()->user()->id]);
        if(count($db_application_datas) == 0){
            return response()->json([],200);
            
        }
        $application_datas = [];
        foreach ($db_application_datas as $key => $value){
            $application_data = [];
            $application_data["id"] =$value->id; 
            $patient_data = DB::select("select name,gender,birth_date,address_city,address_district,address_detail,phone,languages,indigenous_type,mobility,diagnosed,level,CDR from patients where id = ?",[$value->patient_id])[0];
            $patient_data->diagnosed = (bool)$patient_data->diagnosed;
            $application_data["patient"] = $patient_data;
            $application=[];
            $application["user_name"]=$value->user_name;
            $application["user_phone"]=$value->user_phone;
            $application["relation"]=$value->relation;
            $application["status"]=$value->status;
            $application["from"]=$value->from;
            $application["service_times"]=$value->service_times;
            $application["qualification_id"]=$value->qualification_id;
            $application["contract_id"]=$value->contract_id;
            $application["carer_id"]=$value->carer_id;
            $application["selected_vendor_id"]=$value->selected_vendor_id;
            $application["service_id"]=$value->service_id;
            $application["create_date"]=Carbon::parse($value->create_date)->format('Y-m-d');
            $application_data["application"] = $application;
            $careitem_data = DB::select("select daily_care,safety,outdoor,
            medication,other,duration,start_date,start_time,frequency,period,frequency_note,period_note,healthcertificate_answer,healthcertificate_files from care_items where id = ?",[$value->careitem_id])[0];
            $careitem_data->safety=(bool)$careitem_data->safety;
            $careitem_data->outdoor=(bool)$careitem_data->outdoor;
            $careitem_data->medication=(bool)$careitem_data->medication;
            $careitem_data->safety=(bool)$careitem_data->safety;
            $application_data["care_item"] = $careitem_data;
            array_push($application_datas,$application_data);
        }
         return response()->json($application_datas,200);
    }
    public function store(Request $request)
    {
        $messages = ["required"=>":attribute 是必填項目"]; 
        if(auth()->user()->role !=0){
          return response(["message" =>"你使用的身分非民眾，不得建立需求表資料！"],401);
        }
        $data = $request->all();
        if(!(array_key_exists("patient",$data)) ||!(array_key_exists("application",$data))||
        !(array_key_exists("care_item",$data))
        ){
            return response(["message" => "需求表資料缺少patient、application、care_item項目，請重新輸入！"],422);
        }
        $patient = $data["patient"];
        $application =$data["application"];
        $care_item =$data["care_item"];
        //驗證資料
        $validator_patient = Validator::make($patient,[
             "name"=>"required",
             "gender"=>'required|in:男,女',
             "birth_date"=>"required|date",
             "phone"=>['required', 'regex:/^09[0-9]{8}$/'],
             "mobility"=>Rule::in(['可行走','使用輔具行走','坐輪椅移位','臥床']),
             "diagnosed"=>"boolean",
             "CDR"=>"numeric|in:0,0.5,1,2,3",
             "address_city"=>"required",
             "address_district"=>"required",
             "address_detail"=>"required"],$messages);
        if($validator_patient->fails()){
            return response($validator_patient->errors(),400);
        }
        $validator_application = Validator::make($application,[
            "user_name"=>"required",
            "relation"=>"required",
            "selected_vendor_id"=>"numeric",
            "status"=>"required|numeric|in:1,2,3,4,5,6",
            "from"=>"required|numeric|in:1,2",
            "user_phone"=>"required",
            "carer_id"=>"numeric",
            "service_times"=>"numeric|min:0"
            ],$messages);
       if($validator_application->fails()){
           return response($validator_application->errors(),400);
       }
       $validator_care_item = Validator::make($care_item,[
        "safety"=>"boolean",
        "outdoor"=>"boolean",
        "mediciation"=>"boolean",
        "duration"=>"required|numeric",
        "start_date"=>"required|date",
        "start_time"=>"required|date_format:H:i:s",
        "frequency"=>"required",
        "period"=>"required",
        ],$messages);
        if($validator_care_item->fails()){
            return response($validator_care_item->errors(),400);
        }
        if(array_key_exists("carer_id",$application)){
            $db_carer_data = DB::select('select id from carers where id = ?',[$application["carer_id"]]);
            if(count($db_carer_data) == 0){
                return response()->json(["message"=>"你所尋找的陪伴員資料找不到或是已刪除"],404); 
            }
        }
        //從patients表中新增一筆被照顧者記錄
        $patient_id=DB::table("patients")->insertGetId(['name'=>$patient["name"],
                                        'gender'=>$patient["gender"],
                                        'birth_date'=>$patient["birth_date"],
                                        'phone'=>$patient["phone"],                                                               
                                        'languages'=>array_key_exists("languages",$patient)?$patient["languages"]:"國語",
                                        'indigenous_type'=>array_key_exists("indigenous_type",$patient)?$patient["indigenous_type"]:null,
                                        'mobility'=>array_key_exists("mobility",$patient)?$patient["mobility"]:"可行走",
                                        'diagnosed'=>array_key_exists("diagnosed",$patient)?$patient["diagnosed"]:false,
                                        'level'=>array_key_exists("level",$patient)?$patient["level"]:null,
                                        'CDR'=>array_key_exists("CDR",$patient)?$patient["CDR"]:'0',
                                        'address_city'=>$patient["address_city"],
                                        'address_district'=>$patient["address_district"],
                                        'address_detail'=>$patient["address_detail"],
                                        'create_date'=>now(),'modified_date'=>now()]);
        //從care_items表中新增一筆需求項目+記錄
        $careitem_id=DB::table("care_items")->insertGetId(['daily_care'=>array_key_exists("daily_care",$care_item)?$care_item["daily_care"]:null,
                                        'safety'=>array_key_exists("safety",$care_item)?$care_item["safety"]:false,
                                        'outdoor'=>array_key_exists("outdoor",$care_item)?$care_item["outdoor"]:false,
                                        'medication'=>array_key_exists("medication",$care_item)?$care_item["medication"]:false,
                                        'other'=>array_key_exists("other",$care_item)?$care_item["other"]:null,
                                        'duration'=>$care_item["duration"],
                                        'start_date'=>$care_item["start_date"],
                                        'start_time'=>$care_item["start_time"],
                                        'frequency'=>$care_item["frequency"],
                                        'period'=>$care_item["period"],
                                        'frequency_note'=>array_key_exists("frequency_note",$care_item)?$care_item["frequency_note"]:null,
                                        'period_note'=>array_key_exists("period_note",$care_item)?$care_item["period_note"]:null,
                                        'healthcertificate_answer'=>array_key_exists("healthcertificate_answer",$care_item)?$care_item["healthcertificate_answer"]:null,
                                        'healthcertificate_files'=>array_key_exists("healthcertificate_files",$care_item)?$care_item["healthcertificate_files"]:null,                   
                                        'create_date'=>now(),'modified_date'=>now()]);
        //從applications表中新增一筆需求表記錄
        $application_id=DB::table("applications")->insertGetId(['user_id'=>auth()->user()->id,
                                        'user_name'=>$application["user_name"],
                                        'status'=>$application["status"],
                                        'from'=>$application["from"],
                                        'service_times'=>array_key_exists("service_times",$application)?$application["service_times"]:0,
                                        'patient_id'=>$patient_id,
                                        'careitem_id'=>$careitem_id,
                                        'carer_id'=>array_key_exists("carer_id",$application)?$application["carer_id"]:null,
                                        'relation'=>$application["relation"],
                                        'user_phone'=>$application["user_phone"],              
                                        'selected_vendor_id'=>$application["selected_vendor_id"],
                                        'create_date'=>now(),'modified_date'=>now()]);
            return response()->json(["message" => "需求表資料新增成功！","id"=>$application_id],201);
    }
    public function update(Request $request, string $id)
    {
        $messages = ["required"=>":attribute 是必填項目"]; 
        $data = $request->all();
        if(!(array_key_exists("patient",$data)) ||!(array_key_exists("application",$data))||
        !(array_key_exists("care_item",$data))
        ){
            return response("需求表資料缺少patient、application、care_item項目，請重新輸入！",422);
        }
        $patient = $data["patient"];
        $application =$data["application"];
        $care_item =$data["care_item"];
        //驗證資料
        $validator_patient = Validator::make($patient,[
             "gender"=>'in:男,女',
             "birth_date"=>"date",
             "phone"=>'regex:/^09[0-9]{8}$/',
             "mobility"=>Rule::in(['可行走','使用輔具行走','坐輪椅移位','臥床']),
             "diagnosed"=>"boolean",
             "CDR"=>"numeric|in:0,0.5,1,2,3",],$messages);
        if($validator_patient->fails()){
            return response($validator_patient->errors(),400);
        }
        $validator_application = Validator::make($application,[
            "selected_vendor_id"=>"numeric",
            "status"=>"numeric|in:1,2,3,4,5,6",
            "from"=>"numeric|in:1,2",
            "service_times"=>"numeric|min:0",
            "carer_id"=>"numeric"
            ],$messages);
       if($validator_application->fails()){
           return response($validator_application->errors(),400);
       }
       $validator_care_item = Validator::make($care_item,[
        "safety"=>"boolean",
        "outdoor"=>"boolean",
        "mediciation"=>"boolean",
        ],$messages);
        if($validator_care_item->fails()){
            return response($validator_care_item->errors(),400);
        }
        //檢查是否有該筆需求表紀錄
        $db_application = DB::select('select user_id,user_name,user_phone,relation,status,`from`,service_times,patient_id,careitem_id,qualification_id,contract_id,carer_id,selected_vendor_id,service_id from applications where id=?',[$id]);
        if(count($db_application) == 0){
            return response()->json(["message"=>"你所編輯的申請表資料id為 {$id} 找不到"],404);
        }
        $db_application = $db_application[0];
        //檢查是否有該筆陪伴員紀錄
        if(array_key_exists("carer_id",$application)){
            $db_carer_data = DB::select('select id from carers where id = ?',[$application["carer_id"]]);
            if(count($db_carer_data) == 0){
                return response()->json(["message"=>"你所尋找的陪伴員資料找不到或是已刪除"],404); 
            }
        }
        //更新applications表資料
        DB::table("applications")->where('id',$id)->update([
            'relation'=>array_key_exists("relation",$application)? $application["relation"]:$db_application->relation,
            'user_name'=>array_key_exists("user_name",$application)? $application["user_name"]:$db_application->user_name,
            'status'=>array_key_exists("status",$application)? $application["status"]:$db_application->status,
            'from'=>array_key_exists("from",$application)? $application["from"]:$db_application->from,
            'service_times'=>array_key_exists("service_times",$application)?$application["service_times"]:$db_application->service_times,
            'user_phone'=>array_key_exists("user_phone",$application)? $application["user_phone"]:$db_application->user_phone,
            'selected_vendor_id'=>array_key_exists("selected_vendor_id",$application)? $application["selected_vendor_id"]:$db_application->selected_vendor_id,
            'carer_id'=>array_key_exists("carer_id",$application)? $application["carer_id"]:$db_application->carer_id,
            'modified_date'=>now()]);
        //更新patients表資料
        $db_patient = DB::select('select name,gender,birth_date,phone,languages,indigenous_type,mobility,
                        diagnosed,level,CDR,address_city,address_district,address_detail from patients where id=?',[$db_application->patient_id]);
        if(count($db_patient) == 0){
            return response()->json(["message"=>"你所編輯的申請表資料id為 {$id} 找不到"],404);
        }
        $db_patient = $db_patient[0];
        DB::table("patients")->where('id',$db_application->patient_id)->update([
            'name'=>array_key_exists("name",$patient)? $patient["name"]:$db_patient->name,
            'gender'=>array_key_exists("gender",$patient)? $patient["gender"]:$db_patient->gender,
            'birth_date'=>array_key_exists("birth_date",$patient)? $patient["birth_date"]:$db_patient->birth_date,
            'phone'=>array_key_exists("phone",$patient)? $patient["phone"]:$db_patient->phone,
            'languages'=>array_key_exists("languages",$patient)? $patient["languages"]:$db_patient->languages,
            'indigenous_type'=>array_key_exists("indigenous_type",$patient)? $patient["indigenous_type"]:$db_patient->indigenous_type,
            'mobility'=>array_key_exists("mobility",$patient)? $patient["mobility"]:$db_patient->mobility,
            'diagnosed'=>array_key_exists("diagnosed",$patient)? $patient["diagnosed"]:$db_patient->diagnosed,
            'level'=>array_key_exists("level",$patient)? $patient["level"]:$db_patient->level,
            'CDR'=>array_key_exists("CDR",$patient)? $patient["CDR"]:$db_patient->CDR,
            'address_city'=>array_key_exists("address_city",$patient)? $patient["address_city"]:$db_patient->address_city,
            'address_district'=>array_key_exists("address_district",$patient)? $patient["address_district"]:$db_patient->address_district,
            'address_detail'=>array_key_exists("address_detail",$patient)? $patient["address_detail"]:$db_patient->address_detail,
            'modified_date'=>now()]);
        //更新care_items表資料
        $db_care_items = DB::select('select daily_care,safety,outdoor,medication,other,duration,start_date,
                                start_time,frequency,period,frequency_note,period_note,healthcertificate_answer,healthcertificate_files from care_items where id=?',[$db_application->careitem_id]);
        if(count($db_care_items) == 0){
            return response()->json(["message"=>"你所編輯的申請表資料id為 {$id} 找不到"],404);
        }
        $db_care_items = $db_care_items[0];
        DB::table("care_items")->where('id',$db_application->careitem_id)->update([
            'daily_care'=>array_key_exists("daily_care",$care_item)? $care_item["daily_care"]:$db_care_items->daily_care,
            'safety'=>array_key_exists("safety",$care_item)? $care_item["safety"]:$db_care_items->safety,
            'outdoor'=>array_key_exists("outdoor",$care_item)? $care_item["outdoor"]:$db_care_items->outdoor,
            'medication'=>array_key_exists("medication",$care_item)? $care_item["medication"]:$db_care_items->medication,
            'other'=>array_key_exists("other",$care_item)? $care_item["other"]:$db_care_items->other,
            'duration'=>array_key_exists("duration",$care_item)? $care_item["duration"]:$db_care_items->duration,
            'start_date'=>array_key_exists("start_date",$care_item)? $care_item["start_date"]:$db_care_items->start_date,
            'start_time'=>array_key_exists("start_time",$care_item)? $care_item["start_time"]:$db_care_items->start_time,
            'frequency'=>array_key_exists("frequency",$care_item)? $care_item["frequency"]:$db_care_items->frequency,
            'period'=>array_key_exists("period",$care_item)? $care_item["period"]:$db_care_items->period,
            'frequency_note'=>array_key_exists("frequency_note",$care_item)? $care_item["frequency_note"]:$db_care_items->frequency_note,
            'period_note'=>array_key_exists("period_note",$care_item)? $care_item["period_note"]:$db_care_items->period_note,
            'healthcertificate_answer'=>array_key_exists("healthcertificate_answer",$care_item)? $care_item["healthcertificate_answer"]:$db_care_items->healthcertificate_answer,
            'healthcertificate_files'=>array_key_exists("healthcertificate_files",$care_item)? $care_item["healthcertificate_files"]:$db_care_items->healthcertificate_files,
            'modified_date'=>now()]);
        return response()->json(["message"=>"需求表資料編輯成功！"],200);
    }
    public function show(string $id)
    {
        $db_application_datas = DB::select('select * from applications where user_id = ? and id=?',[auth()->user()->id,$id]);
        if(count($db_application_datas) == 0){
            return response()->json([],200);
            
        }
        $db_application_datas = $db_application_datas[0];
        $application_data = [];
        $application_data["id"] =$db_application_datas->id;
        $patient_data = DB::select("select name,gender,birth_date,address_city,address_district,address_detail,phone,languages,indigenous_type,mobility,diagnosed,level,CDR from patients where id = ?",[$db_application_datas->patient_id])[0];
        $patient_data->diagnosed = (bool)$patient_data->diagnosed;
        $application_data["patient"] = $patient_data;
        $application=[];
        $application["user_name"]=$db_application_datas->user_name;
        $application["user_phone"]=$db_application_datas->user_phone;
        $application["relation"]=$db_application_datas->relation;
        $application["status"]=$db_application_datas->status;
        $application["from"]=$db_application_datas->from;
        $application["service_times"]=$db_application_datas->service_times;
        $application["qualification_id"]=$db_application_datas->qualification_id;
        $application["contract_id"]=$db_application_datas->contract_id;
        $application["carer_id"]=$db_application_datas->carer_id;
        $application["selected_vendor_id"]=$db_application_datas->selected_vendor_id;
        $application["service_id"]=$db_application_datas->service_id;
        $application["create_date"]=Carbon::parse($db_application_datas->create_date)->format('Y-m-d');
        $application_data["application"] = $application;
        $careitem_data = DB::select("select daily_care,safety,outdoor,
            medication,other,duration,start_date,start_time,frequency,period,frequency_note,period_note,healthcertificate_answer,healthcertificate_files from care_items where id = ?",[$db_application_datas->careitem_id])[0];
        $careitem_data->safety=(bool)$careitem_data->safety;
        $careitem_data->outdoor=(bool)$careitem_data->outdoor;
        $careitem_data->medication=(bool)$careitem_data->medication;
        $careitem_data->safety=(bool)$careitem_data->safety;
        $application_data["care_item"] = $careitem_data;
         return response()->json($application_data,200);
    }
    public function destroy(string $id)
    {
        $db_application = DB::select('select patient_id,careitem_id,qualification_id,contract_id,carer_id,user_id from applications where id=?',[$id]);
        if(count($db_application) == 0){
            return response()->json(["message"=>"你所尋找的需求表資料找不到或是已刪除"],404);     
        }
        $db_application = $db_application[0];
        //檢查是否該申請表的user_id是否為本人
        if($db_application->user_id != auth()->user()->id){
            return response()->json(["message"=>"你欲刪除的申請表資料id為 {$id} 並非本人申請，請重新輸入！"],403);
        }
        DB::table("patients")->where("id",$db_application->patient_id)->delete();
        DB::table("care_items")->where("id",$db_application->careitem_id)->delete();
        DB::table("applications")->where("id",$id)->delete();
        return response()->json(["message"=>"需求表資料刪除成功！"],202);
    }
    public function findApplicationsByCompany_id(string $id)
    {
        if(auth()->user()->role ==0){
            return response(["message"=>"你使用的身分為民眾，不得查詢所有申請表資料！"],403);
        }
        $db_application_datas = DB::select('select * from applications where selected_vendor_id = ?',[$id]);
        if(count($db_application_datas) == 0){
            return response()->json(["message"=>"你所尋找的需求表資料找不到"],404);
            
        }
        $application_datas = [];
       
        foreach ($db_application_datas as $key => $value){
            $application_data = [];
            $application_data["id"] =$value->id;
            $patient_id=$value->patient_id;
            $patient_data= DB::select("select name,gender,birth_date,address_city,address_district,address_detail,phone,languages,indigenous_type,mobility,diagnosed,level,CDR from patients where id = ?",[$patient_id]);
            if(count($patient_data) ==0){
                continue;
            }
            $patient_data = $patient_data[0];
            $patient_data->diagnosed = (bool)$patient_data->diagnosed;
            $application_data["patient"] = $patient_data;
            $application=[];
            $application["user_name"]=$value->user_name;
            $application["user_phone"]=$value->user_phone;
            $application["relation"]=$value->relation;
            $application["status"]=$value->status;
            $application["from"]=$value->from;
            $application["service_times"]=$value->service_times;
            $application["qualification_id"]=$value->qualification_id;
            $application["contract_id"]=$value->contract_id;
            $application["carer_id"]=$value->carer_id;
            $application["selected_vendor_id"]=$value->selected_vendor_id;
            $application["service_id"]=$value->service_id;
            $application["create_date"]=Carbon::parse($value->create_date)->format('Y-m-d');
            $application_data["application"] = $application;
            $careitem_data = DB::select("select daily_care,safety,outdoor,
            medication,other,duration,start_date,start_time,frequency,period,frequency_note,period_note,healthcertificate_answer,healthcertificate_files from care_items where id = ?",[$value->careitem_id])[0];
            $careitem_data->safety=(bool)$careitem_data->safety;
            $careitem_data->outdoor=(bool)$careitem_data->outdoor;
            $careitem_data->medication=(bool)$careitem_data->medication;
            $careitem_data->safety=(bool)$careitem_data->safety;
            $application_data["care_item"] = $careitem_data;
            array_push($application_datas,$application_data);
        }
         return response()->json($application_datas,200);
    }
    public function findApplicationByCompany_idAndId(string $company_id,string $id)
    {
        if(auth()->user()->role ==0){
            return response(["message"=>"你使用的身分為民眾，不得查詢所有申請表資料！"],403);
        }
        $db_application_data = DB::select('select * from applications where selected_vendor_id = ? and id=?',[$company_id,$id]);
        if(count($db_application_data) == 0){
            return response()->json(["message"=>"你所尋找的需求表資料找不到"],404);
            
        }
        $db_application_data = $db_application_data[0];
        $application_data = [];
        $application_data["id"] =$db_application_data->id;
        $patient_data = DB::select("select name,gender,birth_date,address_city,address_district,address_detail,phone,languages,indigenous_type,mobility,diagnosed,level,CDR from patients where id = ?",[$db_application_data->patient_id])[0];
        $patient_data->diagnosed = (bool)$patient_data->diagnosed;
        $application_data["patient"] = $patient_data;
        $application=[];
        $application["user_name"]=$db_application_data->user_name;
        $application["user_phone"]=$db_application_data->user_phone;
        $application["relation"]=$db_application_data->relation;
        $application["status"]=$db_application_data->status;
        $application["from"]=$db_application_data->from;
        $application["service_times"]=$db_application_data->service_times;
        $application["qualification_id"]=$db_application_data->qualification_id;
        $application["contract_id"]=$db_application_data->contract_id;
        $application["carer_id"]=$db_application_data->carer_id;
        $application["selected_vendor_id"]=$db_application_data->selected_vendor_id;
        $application["service_id"]=$db_application_data->service_id;
        $application["create_date"]=Carbon::parse($value->create_date)->format('Y-m-d');
        $application_data["application"] = $application;
        $careitem_data = DB::select("select daily_care,safety,outdoor,
        medication,other,duration,start_date,start_time,frequency,period,frequency_note,period_note,healthcertificate_answer,healthcertificate_files from care_items where id = ?",[$db_application_data->careitem_id])[0];
        $careitem_data->safety=(bool)$careitem_data->safety;
        $careitem_data->outdoor=(bool)$careitem_data->outdoor;
        $careitem_data->medication=(bool)$careitem_data->medication;
        $careitem_data->safety=(bool)$careitem_data->safety;
        $application_data["care_item"] = $careitem_data;
        return response()->json($application_data,200);
    }
    public function createServiceId(string $id){
        if(auth()->user()->role !=1){
            return response(["message"=>"你使用的身分非試辦單位，不得新增服務序號！"],403);
        }
        $company_id =DB::select('select id from companies where account = ? or phone=?',[auth()->user()->account,auth()->user()->phone])[0]->id;
        $db_application_data = DB::select('select id from applications where selected_vendor_id = ? and id=?',[$company_id,$id]);
        if(count($db_application_data) == 0){
            return response()->json(["message"=>"你所尋找的需求表資料找不到，無法新增服務序號！"],404);
        }
        $letters = strtoupper(Str::random(3));
        $firstPart = str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT); 
        $secondPart = str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
        $service_id=$letters.'-'.$firstPart .'--'.$secondPart;
        DB::table("applications")->where('id',$id)->update([
            'service_id'=>$service_id,
            'status'=>'2',
            'modified_date'=>now()]);
        return response()->json(["message"=>"新增服務序號成功！","service_id"=>$service_id],200);
    }
}
