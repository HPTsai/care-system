<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class QuoteController extends Controller
{
    public function store(Request $request,string $id)
    {
        $messages = ["required"=>":attribute 是必填項目"];
        $data = $request->all();
        $validator_quote = Validator::make($data,[
            "day_service_price_u4"=>"required|numeric|min:0",
            "day_service_addprice_o4"=>'required|numeric|min:0',
            "evening_service_addprice"=>"required|numeric|min:0",
            "night_service_addprice"=>"required|numeric|min:0",
            "half_day_price"=>"required|numeric|min:0",
            "all_day_price"=>"required|numeric|min:0",
        ],$messages);
       if($validator_quote->fails()){
           return response($validator_quote->errors(),400);
       }
       $company_from_db = DB::select('select id,quote_id from companies where id=?',[$id]);
       //檢查是否有該筆試辦單位以及報價表紀錄
       if(count($company_from_db)== 0){
            return response()->json(["message"=>"試辦單位不存在，請重新輸入！"],404);
        }
        $company_from_db=$company_from_db[0];
        if($company_from_db->quote_id!=null){
            return response()->json(["message"=>"你申請的報價資料已建立，請重新輸入！"],409);
        }
        $quote_id=DB::table("quotes")->insertGetId([
              'day_service_price_u4'=>$data["day_service_price_u4"],
              'day_service_addprice_o4'=>$data["day_service_addprice_o4"],
              'evening_service_addprice'=>$data["evening_service_addprice"],
              'night_service_addprice'=>$data["night_service_addprice"],
              'half_day_price'=>$data["half_day_price"],
              'all_day_price'=>$data["all_day_price"],
              'create_date'=>now(),'modified_date'=>now()]);
        DB::table("companies")->where('id',$id)->update([
                'quote_id'=>$quote_id,
                'modified_date'=>now()]);
        return response()->json(["message"=>"報價資料建立成功！"],201);
    }
    public function update(Request $request, string $id)
    {
        $messages = ["required"=>":attribute 是必填項目"];
        $data = $request->all();
        $validator_quote = Validator::make($data,[
            "day_service_price_u4"=>"numeric|min:0",
            "day_service_addprice_o4"=>'numeric|min:0',
            "evening_service_addprice"=>"numeric|min:0",
            "night_service_addprice"=>"numeric|min:0",
            "half_day_price"=>"numeric|min:0",
            "all_day_price"=>"numeric|min:0",
        ],$messages);
       if($validator_quote->fails()){
           return response($validator_quote->errors(),400);
       }
       $company_from_db = DB::select('select id,quote_id from companies where id=?',[$id]);
       //檢查是否有該筆試辦單位以及報價表紀錄
       if(count($company_from_db)== 0){
            return response()->json(["message"=>"試辦單位不存在，請重新輸入！"],404);
        }
        $company_from_db=$company_from_db[0];
        if($company_from_db->quote_id==null){
            return response()->json(["message"=>"你申請的報價資料尚未建立，請重新輸入！"],409);
        }
        $quote_from_db = DB::select('select * from quotes where id=?',[$company_from_db->quote_id])[0];
        DB::table("quotes")->where("id",$company_from_db->quote_id)->update([
              'day_service_price_u4'=>array_key_exists('day_service_price_u4', $data)?$data["day_service_price_u4"]:$quote_from_db->day_service_price_u4,
              'day_service_addprice_o4'=>array_key_exists('day_service_addprice_o4', $data)?$data["day_service_addprice_o4"]:$quote_from_db->day_service_addprice_o4,
              'evening_service_addprice'=>array_key_exists('evening_service_addprice', $data)?$data["evening_service_addprice"]:$quote_from_db->evening_service_addprice,
              'night_service_addprice'=>array_key_exists('night_service_addprice', $data)?$data["night_service_addprice"]:$quote_from_db->night_service_addprice,
              'half_day_price'=>array_key_exists('half_day_price', $data)?$data["half_day_price"]:$quote_from_db->half_day_price,
              'all_day_price'=>array_key_exists('all_day_price', $data)?$data["all_day_price"]:$quote_from_db->all_day_price,
              'modified_date'=>now()]);
        return response()->json(["message"=>"報價資料編輯成功！"],200);
    }
    public function show(string $id)
    {
        $company_from_db = DB::select('select id,quote_id from companies where id=?',[$id]);
       //檢查是否有該筆試辦單位以及報價表紀錄
       if(count($company_from_db)== 0){
            return response()->json(["message"=>"試辦單位不存在，請重新輸入！"],404);
        }
        $company_from_db=$company_from_db[0];
        if($company_from_db->quote_id==null){
            return response()->json([],200);
        }
        $quote_from_db = DB::select('select id,day_service_price_u4,day_service_addprice_o4,evening_service_addprice,
        night_service_addprice,half_day_price,all_day_price from quotes where id=?',[$company_from_db->quote_id])[0];
        return response()->json($quote_from_db,200);
    }
    public function destroy(string $id)
    {
        $company_from_db = DB::select('select quote_id from companies where id=?',[$id]);
        if(count($company_from_db) == 0){
            return response()->json([],200);
        }
        $quote_id=$company_from_db[0]->quote_id;
        DB::table("quotes")->where("id",$quote_id)->delete();
        DB::table("companies")->where('id',$id)->update([
            'quote_id'=>null,
            'modified_date'=>now()]);
        return response()->json([
            "message" => "報價資料刪除成功！"
        ],200);
    }
}
