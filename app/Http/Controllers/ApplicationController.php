<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
class ApplicationController extends Controller
{
    public function index()
    {
        $application_datas = DB::select('select id,user_id,attendee_id,careitem_id,relation,is_commissioned,company_id,alternate_company_id from applications where user_id = ?',[auth()->user()->id]);
        if(count($application_datas) == 0){
            return response()->json(["message"=>"你所尋找的需求表資料找不到"],404);
            
        }
        foreach ($application_datas as $key => $value){
            $attendee_data = DB::select("select name,birth_date,address,phone,language,mobility,CDR from attendees where id = ?",[$value->attendee_id])[0];
            $value->name =$attendee_data->name;
            $value->birth_date =$attendee_data->birth_date;
            $value->address =$attendee_data->address;
            $value->phone =$attendee_data->phone;
            $value->language =$attendee_data->language;
            $value->mobility =$attendee_data->mobility;
            $value->CDR =$attendee_data->CDR;
            $careitem_data = DB::select("select daily_care,is_meal_pre,is_accom_pre,
            is_safecare_pre,is_workcare_pre,is_activity_pre,is_medicine_pre,other,service_time,start_date,start_time,use_time,times from care_items where id = ?",[$value->careitem_id])[0];
            $value->daily_care =$careitem_data->daily_care;
            $value->is_meal_pre =$careitem_data->is_meal_pre;
            $value->is_accom_pre =$careitem_data->is_accom_pre;
            $value->is_safecare_pre =$careitem_data->is_safecare_pre;
            $value->is_workcare_pre =$careitem_data->is_workcare_pre;
            $value->is_activity_pre =$careitem_data->is_activity_pre;
            $value->is_medicine_pre =$careitem_data->is_medicine_pre;
            $value->other =$careitem_data->other;
            $value->service_time =$careitem_data->service_time;
            $value->start_date =$careitem_data->start_date;
            $value->start_time =$careitem_data->start_time;
            $value->use_time =$careitem_data->use_time;
            $value->times =$careitem_data->times;
        }
         return response()->json($application_datas,200);
    }
    public function store(Request $request)
    { 
        $messages = ["required"=>":attribute 是必填項目"]; 
        if(auth()->user()->role !=0){
           return response("你使用的身分非民眾，不得建立需求表資料！",401);
        }
        $data = $request->all();
        $validator = Validator::make($data,[
            "relation"=>"required",
            "company_id"=>"required|integer",
            "name"=>"required",
            "birth_date"=>"required",
            "address"=>"required",
            "phone"=>"required",
            "service_time"=>"required",
            "start_date"=>"required",
            "start_time"=>"required",
            "use_time"=>"required",
            "CDR"=>"numeric",
            "mobility"=>Rule::in(['可行走','使用輔具行走','坐輪椅移位','臥床']),
            "times"=>Rule::in([1,2,3,4,5,6,7]),
            "is_meal_pre"=>"boolean",
            "is_accom_pre"=>"boolean",
            "is_safecare_pre"=>"boolean",
            "is_workcare_pre"=>"boolean",
            "is_medicine_pre"=>"boolean",
            "is_activity_pre"=>"boolean",
            "is_commissioned"=>"boolean"
        ],$messages);
        if($validator->fails()){
            return response($validator->errors(),400);
        }
        //從attendees表中新增一筆被照顧者記錄
        DB::table("attendees")->insert(['name'=>$data["name"],
                                        'birth_date'=>$data["birth_date"],
                                        'address'=>$data["address"],
                                        'phone'=>$data["phone"],
                                        'language'=>array_key_exists("language",$data)?$data["language"]:"國語",
                                        'mobility'=>array_key_exists("mobility",$data)?$data["mobility"]:"可行走",
                                        'CDR'=>array_key_exists("CDR",$data)?$data["CDR"]:'0',
                                        'create_date'=>now(),'modified_date'=>now()]);
        $attendee_id = DB::select('select id from attendees order by create_date desc limit 1')[0]->id;
        //從care_items表中新增一筆服務需求+預約記錄
        DB::table("care_items")->insert(['service_time'=>$data["service_time"],
                                         'start_date'=>$data["start_date"],
                                         'start_time'=>$data["start_time"],
                                         'use_time'=>$data["use_time"],
                                         'daily_care'=>array_key_exists("daily_care",$data)?$data["daily_care"]:null,
                                        'is_meal_pre'=>array_key_exists("is_meal_pre",$data)?$data["is_meal_pre"]:false,
                                         'is_accom_pre'=>array_key_exists("is_accom_pre",$data)?$data["is_accom_pre"]:false,
                                        'is_safecare_pre'=>array_key_exists("is_safecare_pre",$data)?$data["is_safecare_pre"]:false,
                                        'is_workcare_pre'=>array_key_exists("is_workcare_pre",$data)?$data["is_workcare_pre"]:false,
                                        'is_medicine_pre'=>array_key_exists("is_medicine_pre",$data)?$data["is_medicine_pre"]:false,
                                        'is_activity_pre'=>array_key_exists("is_activity_pre",$data)?$data["is_activity_pre"]:false,
                                        'other'=>array_key_exists("other",$data)?$data["other"]:false,
                                        'create_date'=>now(),'modified_date'=>now()]);
         $careitem_id = DB::select('select id from care_items order by create_date desc limit 1')[0]->id;
         //從applications表中新增一筆需求表記錄
         DB::table("applications")->insert(['user_id'=>auth()->user()->id,
                                         'attendee_id'=>$attendee_id,
                                         'careitem_id'=>$careitem_id,
                                         'relation'=>$data["relation"],
                                         'is_commissioned'=>array_key_exists("is_commissioned",$data)?$data["is_commissioned"]:false,
                                         'company_id'=>$data["company_id"],
                                         'alternate_company_id'=>array_key_exists("alternate_company_id",$data)?$data["alternate_company_id"]:null,
                                         'create_date'=>now(),'modified_date'=>now()]);
            return response()->json(["message" => "需求表資料新增成功！",],201);
        }
    public function update(Request $request, string $id)
    {
        $messages = ["required"=>":attribute 是必填項目"]; 
        if(auth()->user()->role !=0){
           return response("你使用的身分非民眾，不得建立需求表資料！",401);
        }
        $data = $request->all();
        $validator = Validator::make($data,[
            "company_id"=>"integer",
            "CDR"=>"numeric",
            "mobility"=>Rule::in(['可行走','使用輔具行走','坐輪椅移位','臥床']),
            "times"=>Rule::in([1,2,3,4,5,6,7]),
            "is_meal_pre"=>"boolean",
            "is_accom_pre"=>"boolean",
            "is_safecare_pre"=>"boolean",
            "is_workcare_pre"=>"boolean",
            "is_medicine_pre"=>"boolean",
            "is_activity_pre"=>"boolean",
            "is_commissioned"=>"boolean"
        ],$messages);
        if($validator->fails()){
            return response($validator->errors(),400);
        }
        //檢查是否有該筆需求表紀錄
        $application = DB::select('select attendee_id,careitem_id,relation,is_commissioned,company_id,alternate_company_id,company_id from applications where id=?',[$id]);
        if(count($application) == 0){
            return response()->json(["message"=>"你所編輯的民眾資料id為 {$id} 找不到"],404);
        }
        $application = $application[0];
        //更新applications表資料
        DB::table("applications")->where('id',$id)->update([
            'relation'=>array_key_exists("relation",$data)? $data["relation"]:$application->relation,
            'is_commissioned'=>array_key_exists("is_commissioned",$data)? $data["is_commissioned"]:$application->is_commissioned,
            'company_id'=>array_key_exists("company_id",$data)? $data["company_id"]:$application->company_id,
            'alternate_company_id'=>array_key_exists("alternate_company_id",$data)? $data["alternate_company_id"]:$application->alternate_company_id,
            'modified_date'=>now()]);
        //更新attendees表資料
        $attendees = DB::select('select name,birth_date,address,phone,language,mobility,CDR from attendees where id=?',[$application->attendee_id]);
        if(count($attendees) == 0){
            return response()->json(["message"=>"你所編輯的民眾資料id為 {$id} 找不到"],404);
        }
        $attendees = $attendees[0];
        DB::table("attendees")->where('id',$application->attendee_id)->update([
            'name'=>array_key_exists("name",$data)? $data["name"]:$attendees->name,
            'birth_date'=>array_key_exists("birth_date",$data)? $data["birth_date"]:$attendees->name,
            'address'=>array_key_exists("address",$data)? $data["address"]:$attendees->address,
            'phone'=>array_key_exists("phone",$data)? $data["phone"]:$attendees->phone,
            'language'=>array_key_exists("language",$data)? $data["language"]:$attendees->language,
            'mobility'=>array_key_exists("mobility",$data)? $data["mobility"]:$attendees->mobility,
            'CDR'=>array_key_exists("CDR",$data)? $data["CDR"]:$attendees->CDR,
            'modified_date'=>now()]);
        //更新care_items表資料
        $care_items = DB::select('select daily_care,is_meal_pre,is_accom_pre,is_safecare_pre,is_workcare_pre,is_medicine_pre,is_activity_pre,
                                other,service_time,start_date,start_time,use_time,times from care_items where id=?',[$application->careitem_id]);
        if(count($care_items) == 0){
            return response()->json(["message"=>"你所編輯的民眾資料id為 {$id} 找不到"],404);
        }
        $care_items = $care_items[0];
        DB::table("care_items")->where('id',$application->careitem_id)->update([
            'daily_care'=>array_key_exists("daily_care",$data)? $data["daily_care"]:$care_items->daily_care,
            'is_meal_pre'=>array_key_exists("is_meal_pre",$data)? $data["is_meal_pre"]:$care_items->is_meal_pre,
            'is_accom_pre'=>array_key_exists("is_accom_pre",$data)? $data["is_accom_pre"]:$care_items->is_accom_pre,
            'is_safecare_pre'=>array_key_exists("is_safecare_pre",$data)? $data["is_safecare_pre"]:$care_items->is_safecare_pre,
            'is_workcare_pre'=>array_key_exists("is_workcare_pre",$data)? $data["is_workcare_pre"]:$care_items->is_workcare_pre,
            'is_medicine_pre'=>array_key_exists("is_medicine_pre",$data)? $data["is_medicine_pre"]:$care_items->is_medicine_pre,
            'is_activity_pre'=>array_key_exists("is_activity_pre",$data)? $data["is_activity_pre"]:$care_items->is_activity_pre,
            'other'=>array_key_exists("other",$data)? $data["other"]:$care_items->other,
            'service_time'=>array_key_exists("service_time",$data)? $data["service_time"]:$care_items->service_time,
            'start_date'=>array_key_exists("start_date",$data)? $data["start_date"]:$care_items->start_date,
            'start_time'=>array_key_exists("start_time",$data)? $data["start_time"]:$care_items->start_time,
            'use_time'=>array_key_exists("use_time",$data)? $data["use_time"]:$care_items->use_time,
            'times'=>array_key_exists("times",$data)? $data["times"]:$care_items->times,
            'modified_date'=>now()]);
        return response()->json(["message"=>"需求表資料編輯成功！"],200);
    }
    public function show(string $id)
    {
        $application_datas = DB::select('select id,user_id,attendee_id,careitem_id,relation,is_commissioned,company_id,alternate_company_id from applications where id=?',[$id]);
        if(count($application_datas) == 0){
            return response()->json(["message"=>"你所尋找的需求表資料找不到"],404);     
        }
        $application_data = $application_datas[0];
        $attendee_data = DB::select("select name,birth_date,address,phone,language,mobility,CDR from attendees where id = ?",[$application_data->attendee_id])[0];
        $application_data->name =$attendee_data->name;
        $application_data->birth_date =$attendee_data->birth_date;
        $application_data->address =$attendee_data->address;
        $application_data->phone =$attendee_data->phone;
        $application_data->language =$attendee_data->language;
        $application_data->mobility =$attendee_data->mobility;
        $application_data->CDR =$attendee_data->CDR;
        $careitem_data = DB::select("select daily_care,is_meal_pre,is_accom_pre,
        is_safecare_pre,is_workcare_pre,is_activity_pre,is_medicine_pre,other,service_time,start_date,start_time,use_time,times from care_items where id = ?",[$application_data->careitem_id])[0];
        $application_data->daily_care =$careitem_data->daily_care;
        $application_data->is_meal_pre =$careitem_data->is_meal_pre;
        $application_data->is_accom_pre =$careitem_data->is_accom_pre;
        $application_data->is_safecare_pre =$careitem_data->is_safecare_pre;
        $application_data->is_workcare_pre =$careitem_data->is_workcare_pre;
        $application_data->is_activity_pre =$careitem_data->is_activity_pre;
        $application_data->is_medicine_pre =$careitem_data->is_medicine_pre;
        $application_data->other =$careitem_data->other;
        $application_data->service_time =$careitem_data->service_time;
        $application_data->start_date =$careitem_data->start_date;
        $application_data->start_time =$careitem_data->start_time;
        $application_data->use_time =$careitem_data->use_time;
        $application_data->times =$careitem_data->times;
         return response()->json($application_data,200);
    }
    public function destroy(string $id)
    {
        $application_datas = DB::select('select attendee_id,careitem_id from applications where id=?',[$id]);
        if(count($application_datas) == 0){
            return response()->json(["message"=>"你所尋找的需求表資料找不到或是已刪除"],404);     
        }
        $application_data = $application_datas[0];
        DB::table("attendees")->where("id",$application_data->attendee_id)->delete();
        DB::table("care_items")->where("id",$application_data->careitem_id)->delete();
        DB::table("applications")->where("id",$id)->delete();
        return response()->json(["message"=>"需求表資料刪除成功！"],202);
    }
}
