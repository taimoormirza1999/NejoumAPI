<?php

namespace App\Services;

use App\Models\CarAccounting;
use App\Models\Store;
use App\Libraries\Helpers;
use Illuminate\Support\Facades\DB;
use DateTime;
use App\Libraries\Constants;

class CustomerService
{
    public static function getCustomerList($args)
    {
        $customer_id = $args['customer_id'];
        $query = DB::Table('customer_list')
        ->where([
            ['customer_list.customer_id', $customer_id]
        ])->select('*');
        return $query;
    }

    public static function customerGroups($args)
    {
        $query = DB::Table('customer_group')
        ->join('customer', 'customer.customer_group_id', 'customer_group.id')
        ->where('customer.is_deleted', '0')
        ->where('customer.status', '1')
        ->groupBy('customer_group.id')
        ->selectRaw('customer_group.*');
        return $query->get()->toArray();
    }
    
    public static function customerLists($args)
    {
        $today = date('Y-m-d');

        $query = DB::Table('customer')
        ->selectRaw('customer_list.*')
        ->join('customer_contract', function($join) use ($today) {
            $join->on('customer_contract.customer_id', '=', 'customer.customer_id');
            $join->where('customer_contract.status', '=', '1');
            $join->on('customer_contract.start_date', '<=', DB::raw("'$today'"))
            ->where(function ($q) use ($today){
                $q->whereNull('customer_contract.end_date')->orWhere('customer_contract.end_date', '>=', $today);
              });
        })
        ->join('customer_list', 'customer_list.customer_contract_id', 'customer_contract.customer_contract_id')
        ->where('customer.is_deleted', '0')
        ->where('customer.status', '1');

        switch($args['service_id']){
            case Constants::NAJ_SERVICES['TOWING']:
                $query->selectRaw('towing_list.towing_list_id as list_id, towing_list.title as list_name');
                $query->join('towing_list', 'towing_list.towing_list_id', 'customer_list.towing_list_id');
                $query->groupBy('towing_list.towing_list_id');
                break;
            case Constants::NAJ_SERVICES['SHIPPING']:
                $query->selectRaw('shipping_list.shipping_list_id as list_id, shipping_list.title as list_name');
                $query->join('shipping_list', 'shipping_list.shipping_list_id', 'customer_list.shipping_list_id');
                $query->groupBy('shipping_list.shipping_list_id');
                break;
            case Constants::NAJ_SERVICES['LOADING']:
                $query->selectRaw('loading_list.loading_list_id as list_id, loading_list.name as list_name');
                $query->join('loading_list', 'loading_list.loading_list_id', 'customer_list.loading_list_id');
                $query->groupBy('loading_list.loading_list_id');
                break;
            default : 
                $query->groupBy('customer_list.customer_list_id');
                break;
        }

        return $query->get()->toArray();
    }

    public static function activeContractCustomerLists($args)
    {
        switch($args['service_id']){
            case Constants::NAJ_SERVICES['SHIPPING']:

                $query = DB::Table('shipping_contract');
                $query->selectRaw('shipping_contract.price_list_id, max(shipping_contract.shipping_contract_id) list_contract_id, shipping_list.shipping_list_id as list_id, shipping_list.title as list_name');
                $query->join('shipping', 'shipping.shipping_contract_id', 'shipping_contract.shipping_contract_id');
                $query->join('shipping_list', 'shipping_list.shipping_list_id', 'shipping.shipping_list_id');
                $query->where('shipping_contract.status', 1);
                $query->groupBy('shipping.shipping_list_id');

                break;
            case Constants::NAJ_SERVICES['LOADING']:

                $query = DB::Table('loading_contract');
                $query->selectRaw('loading_contract.price_list_id, max(loading_contract.loading_contract_id) list_contract_id, loading_list.loading_list_id as list_id, loading_list.name as list_name');
                $query->join('loading_list_price', 'loading_list_price.loading_contract_id', 'loading_contract.loading_contract_id');
                $query->join('loading_list', 'loading_list.loading_list_id', 'loading_list_price.loading_list_id');
                $query->where('loading_contract.status', 1);
                $query->groupBy('loading_list_price.loading_list_id');

                break;
            default :
                return [];
                break;
        }

        return $query->get()->toArray();
    }

    public static function customerCurrentLists($args)
    {
        if(empty($args['customer_id'])) return [];

        $today = date('Y-m-d');
        $customer_id = $args['customer_id'];

        $query = DB::Table('customer')
        ->selectRaw('customer_list.*')
        ->join('customer_contract', function($join) use ($today) {
            $join->on('customer_contract.customer_id', '=', 'customer.customer_id');
            $join->where('customer_contract.status', '=', '1');
            $join->on('customer_contract.start_date', '<=', DB::raw("'$today'"))
            ->where(function ($q) use ($today){
                $q->whereNull('customer_contract.end_date')->orWhere('customer_contract.end_date', '>=', $today);
              });
        })
        ->join('customer_list', 'customer_list.customer_contract_id', 'customer_contract.customer_contract_id')
        ->where('customer.customer_id', $customer_id);

        return $query->first();
    }

    public static function  saveAgencyDocument($customer_id, $data) {
        $result = false;
        if($data['agency_file']){
            $uploaded_files = ['path' => $data['agency_file']];

            Helpers::replace_general_file('customer', $customer_id, $uploaded_files, ['tag' => 'Agency Document']);
            $result = true;
        }
        return $result;
    }

    public static function  getAgencyDocument($customer_id) {
        $table_id = Helpers::get_table_id('customer');
        return DB::table('general_files')->where(['table_id' => $table_id, 'primary_column' => $customer_id, 'tag' => 'Agency Document'])->first();
    }

    public static function  hasAgencyDocument($customer_id) {
        return !empty(self::getAgencyDocument($customer_id));
    }

    public static function getMonthlyCustomersFeedbackToken($args = []){
        $query = DB::Table('customers_feedback_token')
        ->join('customer', 'customer.customer_id', 'customers_feedback_token.customer_id')
        ->where('customers_feedback_token.feedback_type', 'monthly')
        ->where('customers_feedback_token.create_at', '>=', date('Y-m-01 00:00:00'))
        ->groupBy('customers_feedback_token.id')
        ->selectRaw('customer.customer_id, customer.phone, customers_feedback_token.token');
        return $query->get()->toArray();
    }
}
