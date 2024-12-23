<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    public function index()
    {
        $company_datas = DB::select('select id,gui,name,introduction,email,phone,address_city,address_district,address_detail,logo_url,url,service_times,area,service_area,`rank`,quote_id from companies');
        if(count($company_datas) == 0){
            return response()->json(["message"=>"你所尋找的服務案件資料找不到"],404);
        }
         return response()->json($company_datas,200);
    }
    public function findCompanyByGui(string $gui)
    {
        $company_data = DB::select('select id,gui,name,introduction,email,phone,address_city,address_district,address_detail,logo_url,url,service_times,area,service_area,`rank`,quote_id from companies where gui=?',[$gui]);
        if(count($company_data) == 0){
            return response()->json(["message"=>"你所尋找的服務案件資料找不到"],404);
        }
        $company_data=$company_data[0];
         return response()->json($company_data,200);
    }
    public function findCompanyById(string $id)
    {
        $company_data = DB::select('select id,gui,name,introduction,email,phone,address_city,address_district,address_detail,logo_url,url,service_times,area,service_area,`rank`,quote_id from companies where id=?',[$id]);
        if(count($company_data) == 0){
            return response()->json(["message"=>"你所尋找的服務案件資料找不到"],404);
        }
        $company_data=$company_data[0];
         return response()->json($company_data,200);
    }
    public function findCompanyByCity(string $city)
    {
        $company_data = DB::select('select id,gui,name,introduction,email,phone,address_city,address_district,address_detail,logo_url,url,service_times,area,service_area,`rank`,quote_id from companies where address_city=?',[$city]);
        if(count($company_data) == 0){
            return response()->json(["message"=>"你所尋找的服務案件資料找不到"],404);
        }
         return response()->json($company_data,200);
    }
}