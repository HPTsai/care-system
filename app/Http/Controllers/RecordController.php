<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RecordController extends Controller
{
    public function store(Request $request,string $id)
    {
        $messages = ["required"=>":attribute 是必填項目"];
        $data = $request->all();
        $validator_record = Validator::make($data,[
            "fillin_date"=>"required|date",
            "weekday"=>'required|numeric|in:1,2,3,4,5,6,7',
            "service_time_start"=>"required",
            "service_time_end"=>"required",
        ],$messages);
       if($validator_record->fails()){
           return response($validator_record->errors(),400);
       }
       $signin_from_db = DB::select('select id,record_id from signins where id=?',[$id]);
       //檢查是否有該筆打卡表紀錄
       if(count($signin_from_db)== 0){
            return response()->json(["message"=>"打卡資料不存在，請重新輸入！"],404);
        }
        $signin_from_db=$signin_from_db[0];
        if($signin_from_db->record_id!=null){
            return response()->json(["message"=>"你的個案服務紀錄資料已建立，請重新輸入！"],409);
        }
        $record_id=DB::table("records")->insertGetId([
            'fillin_date'=>$data["fillin_date"],
            'weekday'=>$data["weekday"],
            'service_time_start'=>$data["service_time_start"],
            'service_time_end'=>$data["service_time_end"],
            'serivice_eating'=>array_key_exists('serivice_eating', $data)?$data["serivice_eating"]:null,
            'serivice_bathing'=>array_key_exists('serivice_bathing', $data)?$data["serivice_bathing"]:null,
            'service_dressing'=>array_key_exists('service_dressing', $data)?$data["service_dressing"]:null,
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
            'create_date'=>now(),'modified_date'=>now()]);
        DB::table("signins")->where('id',$id)->update([
                'record_id'=>$record_id,
                'modified_date'=>now()]);
      return response()->json(["message"=>"個案服務紀錄資料建立成功！"],201);
    }
    public function update(Request $request, string $id)
    {
        $messages = ["required"=>":attribute 是必填項目"];
        $data = $request->all();
        $validator_record = Validator::make($data,[
            "fillin_date"=>"date",
            "weekday"=>'numeric|in:1,2,3,4,5,6,7',
        ],$messages);
       if($validator_record->fails()){
           return response($validator_record->errors(),400);
       }
       $signin_from_db = DB::select('select id,record_id from signins where id=?',[$id]);
       //檢查是否有該筆需求表紀錄
       if(count($signin_from_db)== 0){
            return response()->json(["message"=>"打卡資料不存在，請重新輸入！"],404);
        }
        $signin_from_db=$signin_from_db[0];
        if($signin_from_db->record_id==null){
            return response()->json(["message"=>"你的個案服務紀錄資料尚未建立，請重新輸入！"],409);
        }
        $record_from_db=DB::select('select * from records where id=?',[$signin_from_db->record_id])[0];
        DB::table("records")->where('id',$signin_from_db->record_id)->update([
            'fillin_date'=>array_key_exists('fillin_date', $data)?$data["fillin_date"]:$record_from_db->fillin_date,
            'weekday'=>array_key_exists('weekday', $data)?$data["weekday"]:$record_from_db->weekday,
            'service_time_start'=>array_key_exists('service_time_start', $data)?$data["service_time_start"]:$record_from_db->service_time_start,
            'service_time_end'=>array_key_exists('service_time_end', $data)?$data["service_time_end"]:$record_from_db->service_time_end,
            'serivice_eating'=>array_key_exists('serivice_eating', $data)?$data["serivice_eating"]:$record_from_db->serivice_eating,
            'serivice_bathing'=>array_key_exists('serivice_bathing', $data)?$data["serivice_bathing"]:$record_from_db->serivice_bathing,
            'service_dressing'=>array_key_exists('service_dressing', $data)?$data["service_dressing"]:$record_from_db->service_dressing,
            'service_toileting'=>array_key_exists('service_toileting', $data)?$data["service_toileting"]:$record_from_db->service_toileting,
            'service_hygiene'=>array_key_exists('service_hygiene', $data)?$data["service_hygiene"]:$record_from_db->service_hygiene,
            'service_shifting'=>array_key_exists('service_shifting', $data)?$data["service_shifting"]:$record_from_db->service_shifting,
            'service_walking'=>array_key_exists('service_walking', $data)?$data["service_walking"]:$record_from_db->service_walking,
            'service_stair'=>array_key_exists('service_stair', $data)?$data["service_stair"]:$record_from_db->service_stair,
            'service_outing'=>array_key_exists('service_outing', $data)?$data["service_outing"]:$record_from_db->service_outing,
            'service_treatment'=>array_key_exists('service_treatment', $data)?$data["service_treatment"]:$record_from_db->service_treatment,
            'service_companionship'=>array_key_exists('service_companionship', $data)?$data["service_companionship"]:$record_from_db->service_companionship,
            'other_services'=>array_key_exists('other_services', $data)?$data["other_services"]:$record_from_db->other_services,
            'user_signature'=>array_key_exists('user_signature', $data)?$data["user_signature"]:$record_from_db->user_signature,
            'special_matters'=>array_key_exists('special_matters', $data)?$data["special_matters"]:$record_from_db->special_matters,
            'service_hour'=>array_key_exists('service_hour', $data)?$data["service_hour"]:$record_from_db->service_hour,
            'carer_signature'=>array_key_exists('carer_signature', $data)?$data["carer_signature"]:$record_from_db->carer_signature,
            'admin_signature'=>array_key_exists('admin_signature', $data)?$data["admin_signature"]:$record_from_db->admin_signature,
            'create_date'=>now(),'modified_date'=>now()]);
        DB::table("signins")->where('id',$id)->update([
                'modified_date'=>now()]);
      return response()->json(["message"=>"個案服務紀錄資料編輯成功！"],200);
    }
    public function show(string $id)
    {
        $signin_from_db = DB::select('select id,record_id from signins where id=?',[$id]);
        if(count($signin_from_db)== 0){
            return response()->json(["message"=>"打卡資料不存在，請重新輸入！"],404);
        }
        $signin_from_db=$signin_from_db[0];
        if($signin_from_db->record_id==null){
            return response()->json(["message"=>"你的個案服務紀錄資料尚未建立，請重新輸入！"],409);
        }
        $record_from_db=DB::select('select id,fillin_date,weekday,service_time_start,service_time_end,serivice_eating,serivice_bathing,service_dressing,
        service_toileting,service_hygiene,service_shifting,service_walking,service_stair,service_outing,service_treatment,service_companionship,other_services,user_signature,
        special_matters,service_hour,carer_signature,admin_signature from records where id=?',[$signin_from_db->record_id])[0];
        return response()->json( $record_from_db,200);
    }
    public function destroy(string $id)
    {
        $signin_from_db = DB::select('select id,record_id from signins where id=?',[$id]);
        if(count($signin_from_db)== 0){
            return response()->json(["message"=>"打卡資料不存在，請重新輸入！"],404);
        }
        $signin_from_db=$signin_from_db[0];
        if($signin_from_db->record_id==null){
            return response()->json([],200);
        }
        DB::table("records")->where("id",$signin_from_db->record_id)->delete();
        DB::table("signins")->where('id',$id)->update([
            'record_id'=>null,
            'modified_date'=>now()]);
        return response()->json([
            "message" => "個案服務紀錄刪除成功！"
        ],200);
    }
}
