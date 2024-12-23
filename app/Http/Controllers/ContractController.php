<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ContractController extends Controller
{
    public function store(Request $request,string $id)
    {
        $messages = ["required"=>":attribute 是必填項目"];
        $data = $request->all();
        $validator_contract = Validator::make($data,[
            "is_verified_carer"=>"boolean",
            "is_verified_patient"=>'boolean',
            "content"=>"required"],$messages);
       if($validator_contract->fails()){
           return response($validator_contract->errors(),400);
       }
       $application_from_db = DB::select('select id,carer_id,contract_id from applications where id=?',[$id]);
       //檢查是否有該筆需求表紀錄
       if(count($application_from_db)== 0){
            return response()->json(["message"=>"你申請的需求表資料不存在，請重新輸入！"],404);
        }
        $application_from_db=$application_from_db[0];
        if($application_from_db->contract_id!=null){
            return response()->json(["message"=>"你申請的合約資料已建立，請重新輸入！"],409);
        }
        $is_verified_carer=array_key_exists('is_verified_carer', $data)?$data["is_verified_carer"]:false;
        $is_verified_patient=array_key_exists('is_verified_patient', $data)?$data["is_verified_patient"]:false;
        $verified_carer_date=$is_verified_carer? now()->format('Y-m-d'):null;
        $verified_patient_date=$is_verified_patient? now()->format('Y-m-d'):null;
        $contract_id=DB::table("contracts")->insertGetId([
              'is_verified_carer'=>$is_verified_carer,
              'is_verified_patient'=>$is_verified_patient,
              'verified_carer_date'=>$verified_carer_date,
              'verified_patient_date'=>$verified_patient_date,
              'content'=>$data["content"],
              'create_date'=>now(),'modified_date'=>now()]);
        DB::table("applications")->where('id',$id)->update([
                'contract_id'=>$contract_id,
                'modified_date'=>now()]);
        return response()->json(["message"=>"合約建立成功！"],201);
    }
    public function update(Request $request, string $id)
    {
        $messages = ["required"=>":attribute 是必填項目"];
        $data = $request->all();
        $validator_contract = Validator::make($data,[
            "is_verified_carer"=>"boolean",
            "is_verified_patient"=>'boolean'],$messages);
       if($validator_contract->fails()){
           return response($validator_contract->errors(),400);
       }
       $application_from_db = DB::select('select id,carer_id,contract_id from applications where id=?',[$id]);
       //檢查是否有該筆需求表紀錄
       if(count($application_from_db)== 0){
            return response()->json(["message"=>"你申請的需求表資料不存在，請重新輸入！"],404);
        }
        $application_from_db=$application_from_db[0];
        if($application_from_db->contract_id==null){
            return response()->json(["message"=>"你申請的合約資料尚未建立，請重新輸入！"],405);
        }
        $contract_from_db = DB::select('select id,is_verified_carer,is_verified_patient,verified_carer_date,verified_patient_date,content from contracts where id=?',[$application_from_db->contract_id])[0];
        $is_verified_carer=array_key_exists('is_verified_carer', $data)?$data["is_verified_carer"]:$contract_from_db->is_verified_carer;
        $is_verified_patient=array_key_exists('is_verified_patient', $data)?$data["is_verified_patient"]:$contract_from_db->is_verified_patient;
        $verified_carer_date=$is_verified_carer? now()->format('Y-m-d'):null;
        $verified_patient_date=$is_verified_patient? now()->format('Y-m-d'):null;
        $contract_id=DB::table("contracts")->where("id",$application_from_db->contract_id)->update([
              'is_verified_carer'=>$is_verified_carer,
              'is_verified_patient'=>$is_verified_patient,
              'verified_carer_date'=>$verified_carer_date,
              'verified_patient_date'=>$verified_patient_date,
              'content'=>array_key_exists('content', $data)?$data["content"]:$contract_from_db->content,
              'modified_date'=>now()]);
        DB::table("applications")->where('id',$id)->update([
                'modified_date'=>now()]);
        return response()->json(["message"=>"合約編輯成功！"],200);
    }
    public function show(string $id)
    {
        $contract_data=[];
        $application_from_db = DB::select('select id,contract_id from applications where id=?',[$id]);
        if(count($application_from_db) == 0){
            return response()->json([],200);
        }
        $application_from_db=$application_from_db[0];
        if($application_from_db->contract_id==null){
            return response()->json([],200);
        }
        $contract_data_from_db = DB::select('select * from contracts where id=?',[$application_from_db->contract_id])[0];
        $contract_data_from_db->application_id=$id;
        return response()->json($contract_data_from_db,200);
    }
    public function destroy(string $id)
    {
        $application_from_db = DB::select('select contract_id from applications where id=?',[$id]);
        if(count($application_from_db) == 0){
            return response()->json([],200);
        }
        $contract_id=$application_from_db[0]->contract_id;
        DB::table("contracts")->where("id",$contract_id)->delete();
        DB::table("applications")->where('id',$id)->update([
            'contract_id'=>null,
            'modified_date'=>now()]);
        return response()->json([
            "message" => "合約刪除成功！"
        ],200);
    }
}
