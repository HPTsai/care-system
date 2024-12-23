<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class QualificationController extends Controller
{
    public function store(Request $request,string $id)
    {
        $messages = ["required"=>":attribute 是必填項目"];
        $data = $request->all();
        $validator_qualification = Validator::make($data,[
            "certicate_disability"=>"boolean",
            "certicate_illness"=>'boolean',
            "certicate_record"=>'boolean',
            "certicate_hiring"=>'boolean',
            "certicate_need"=>'boolean',
            "CMS"=>"numeric|in:2,3,4,5,6,7,8"],$messages);
       if($validator_qualification->fails()){
           return response($validator_qualification->errors(),400);
       }
       if(auth()->user()->role !=0){
            return response(["message" =>"你使用的身分非民眾，不得建立申請資格資料！"],401);
        }
       $application_from_db = DB::select('select id,qualification_id from applications where id=? and user_id=?',[$id,auth()->user()->id]);
       //檢查是否有該筆需求表紀錄
       if(count($application_from_db)== 0){
            return response()->json(["message"=>"你申請的需求表資料不存在或非本人申請，請重新輸入！"],404);
        }
        $application_from_db=$application_from_db[0];
        if($application_from_db->qualification_id!=null){
            return response()->json(["message"=>"你的申請資格資料已建立，請重新輸入！"],409);
        }
        $certicate_disability=array_key_exists('certicate_disability', $data)?$data["certicate_disability"]:false;
        $certicate_illness=array_key_exists('certicate_illness', $data)?$data["certicate_illness"]:false;
        $certicate_record=array_key_exists('certicate_record', $data)?$data["certicate_record"]:false;
        $illness_status=array_key_exists('illness_status', $data)?$data["illness_status"]:null;
        $certicate_hiring=array_key_exists('certicate_hiring', $data)?$data["certicate_hiring"]:false;
        $hiring_status=array_key_exists('hiring_status', $data)?$data["hiring_status"]:null;
        $certicate_need=array_key_exists('certicate_need', $data)?$data["certicate_need"]:false;
        $CMS=array_key_exists('CMS', $data)?$data["CMS"]:null;
        $qualification_id=DB::table("qualifications")->insertGetId([
              'certicate_disability'=>$certicate_disability,
              'certicate_illness'=>$certicate_illness,
              'certicate_record'=>$certicate_record,
              'illness_status'=>$illness_status,
              'certicate_hiring'=>$certicate_hiring,
              'hiring_status'=>$hiring_status,
              'certicate_need'=>$certicate_need,
              'CMS'=>$CMS,
              'create_date'=>now(),'modified_date'=>now()]);
        DB::table("applications")->where('id',$id)->update([
                'qualification_id'=>$qualification_id,
                'modified_date'=>now()]);
        return response()->json(["message"=>"申請資格資料建立成功！"],201);
    }
    public function update(Request $request, string $id)
    {
        $messages = ["required"=>":attribute 是必填項目"];
        $data = $request->all();
        $validator_qualification = Validator::make($data,[
            "certicate_disability"=>"boolean",
            "certicate_illness"=>'boolean',
            "certicate_record"=>'boolean',
            "certicate_hiring"=>'boolean',
            "certicate_need"=>'boolean',
            "CMS"=>"numeric|in:2,3,4,5,6,7,8"],$messages);
       if($validator_qualification->fails()){
           return response($validator_qualification->errors(),400);
       }
       if(auth()->user()->role !=0){
            return response(["message" =>"你使用的身分非民眾，不得建立申請資格資料！"],401);
        }
        $application_from_db = DB::select('select id,qualification_id from applications where id=? and user_id=?',[$id,auth()->user()->id]);
       //檢查是否有該筆需求表紀錄
       if(count($application_from_db)== 0){
            return response()->json(["message"=>"你申請的需求表資料不存在或非本人申請，請重新輸入！"],404);
        }
        $application_from_db=$application_from_db[0];
        if($application_from_db->qualification_id==null){
            return response()->json(["message"=>"你尚未建立申請資格資料，請重新輸入！"],409);
        }
        $qualification_id=$application_from_db->qualification_id;
        $qualification_from_db = DB::select('select certicate_disability,certicate_illness,certicate_record,illness_status,certicate_hiring,
        hiring_status,certicate_need,CMS from qualifications where id=?',[$qualification_id])[0];
        $certicate_disability=array_key_exists('certicate_disability', $data)?$data["certicate_disability"]:$qualification_from_db->certicate_disability;
        $certicate_illness=array_key_exists('certicate_illness', $data)?$data["certicate_illness"]:$qualification_from_db->certicate_illness;
        $certicate_record=array_key_exists('certicate_record', $data)?$data["certicate_record"]:$qualification_from_db->certicate_record;
        $illness_status=array_key_exists('illness_status', $data)?$data["illness_status"]:$qualification_from_db->illness_status;
        $certicate_hiring=array_key_exists('certicate_hiring', $data)?$data["certicate_hiring"]:$qualification_from_db->certicate_hiring;
        $hiring_status=array_key_exists('hiring_status', $data)?$data["hiring_status"]:$qualification_from_db->hiring_status;
        $certicate_need=array_key_exists('certicate_need', $data)?$data["certicate_need"]:$qualification_from_db->certicate_need;
        $CMS=array_key_exists('CMS', $data)?$data["CMS"]:$qualification_from_db->CMS;
        DB::table("qualifications")->where("id",$qualification_id)->update([
              'certicate_disability'=>$certicate_disability,
              'certicate_illness'=>$certicate_illness,
              'certicate_record'=>$certicate_record,
              'illness_status'=>$illness_status,
              'certicate_hiring'=>$certicate_hiring,
              'hiring_status'=>$hiring_status,
              'certicate_need'=>$certicate_need,
              'CMS'=>$CMS,
              'modified_date'=>now()]);
        DB::table("applications")->where('id',$id)->update([
                'modified_date'=>now()]);
        return response()->json(["message"=>"申請資格編輯成功！"],200);
    }
    public function show(string $id)
    {
        $qualification_data=[];
        $application_from_db = DB::select('select id,qualification_id from applications where id=?',[$id]);
        if(count($application_from_db) == 0){
            return response()->json([],200);
        }
        $application_from_db=$application_from_db[0];
        if($application_from_db->qualification_id==null){
            return response()->json([],200);
        }
        $qualification_data_from_db = DB::select('select * from qualifications where id=?',[$application_from_db->qualification_id])[0];
        $qualification_data_from_db->application_id=$id;
        return response()->json($qualification_data_from_db,200);
    }
    public function destroy(string $id)
    {
        $application_from_db = DB::select('select qualification_id from applications where id=?',[$id]);
        if(count($application_from_db) == 0){
            return response()->json([],200);
        }
        $qualification_id=$application_from_db[0]->qualification_id;
        DB::table("qualifications")->where("id",$qualification_id)->delete();
        DB::table("applications")->where('id',$id)->update([
            'qualification_id'=>null,
            'modified_date'=>now()]);
        return response()->json([
            "message" => "申請資格資料刪除成功！"
        ],200);
    }
}
