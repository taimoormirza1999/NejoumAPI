<?php

namespace App\Services;

use App\Libraries\Constants;
use App\Libraries\Helpers;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    public static function getShippingEstimatedCost($args){

        $customer_id        = $args['customer_id'];
        $auction            = $args['auction'];
        $vehicle_type       = $args['vehicle_type'];
        $auctionLocationID  = $args['auctionLocation'];
        $country            = $args['country'];
        $portID             = $args['port'];
        $today = date('Y-m-d');
        $query = DB::table('customer_list')
        ->selectRaw('customer_list.clearance_list_id as `clearanceList`, customer_list.loading_list_id as `loadingList`,
        IF('.$vehicle_type.'=2,customer_list.bike_list_id,customer_list.shipping_list_id) as `shippingList`,
        customer_list.transportation_list_id as `transportationList`,
        customer_list.towing_list_id as `towingList`
        ')
        ->addSelect(['towing_cost' => function($query) use ($auctionLocationID,$today){
            $query->select('towing.price')
            ->from('towing')
            ->join('towing_contract', function($join) use ($today) {
                $join->on('towing_contract.towing_contract_id', '=', 'towing.towing_contract_id');
                $join->on('towing_contract.start_date', '<=', DB::raw("'$today'"));
                $join->on('towing_contract.end_date', '>=', DB::raw("'$today'"));
            })
            ->join('auction_location', 'auction_location.city_id', '=', 'towing.city_id')
            ->whereRaw('towing.towing_list_id = towingList')
            ->where('auction_location_id', '=', $auctionLocationID)
            ->limit(1);
        }])
        ->addSelect(['clearance_cost' => function($query) use ($today){
            $query->select('clearance_list_price.clearance_list_price')
            ->from('clearance_list_price')
            ->join('clearance_contract', function($join) use ($today) {
                $join->on('clearance_contract.clearance_contract_id', '=', 'clearance_list_price.clearance_contract_id');
                $join->on('clearance_contract.start_date', '<=', DB::raw("'$today'"));
                $join->on('clearance_contract.end_date', '>=', DB::raw("'$today'"));
            })
            ->whereRaw('clearance_list_price.clearance_list_id = clearanceList')
            ->limit(1);
        }])
        ->addSelect(['loading_cost' => function($query) use ($vehicle_type, $auctionLocationID, $today){
            $query->selectRaw('IF('.$vehicle_type.'=2,loading_list_price.bike_price,loading_list_price.car_price)')
            ->from('loading_list_price')
            ->Join('auction_location', 'auction_location.region_id', '=', 'loading_list_price.region_id')
            ->join('loading_contract', function($join) use ($today) {
                $join->on('loading_contract.loading_contract_id', '=', 'loading_list_price.loading_contract_id');
                $join->on('loading_contract.start_date', '<=', DB::raw("'$today'"));
                $join->on('loading_contract.end_date', '>=', DB::raw("'$today'"));
            })
            ->whereRaw('loading_list_price.loading_list_id = loadingList')
            ->where('auction_location_id', '=', $auctionLocationID)
            ->where('loading_list_price.is_deleted', '=', 0)
            ->limit(1);
        }])
        ->addSelect(['shipping_cost' => function($query) use ($auctionLocationID, $portID, $today){
            $query->select('shipping.price')
            ->from('shipping')
            ->join('port as prt', 'prt.port_id', '=', 'shipping.port_id')
            ->join('auction_location', 'auction_location.region_id', '=', 'prt.region_id')
            ->join('shipping_contract', function($join) use ($today) {
                $join->on('shipping_contract.shipping_contract_id', '=', 'shipping.shipping_contract_id');
                $join->on('shipping_contract.start_date', '<=', DB::raw("'$today'"));
                $join->on('shipping_contract.end_date', '>=', DB::raw("'$today'"));
            })
            ->whereRaw('shipping.shipping_list_id = shippingList')
            ->where('auction_location_id', '=', $auctionLocationID)
            ->where('shipping.port_id_distination', '=', $portID)
            ->limit(1);
        }])
        ->addSelect(['transportation_cost' => function($query) use ($portID,$today){
            $query->select('transportation_list_prices.car_sale_price')
            ->from('transportation_list_prices')
            ->join('transportation_contract', function($join) use ($today) {
                $join->on('transportation_contract.transportation_contract_id', '=', 'transportation_list_prices.transportation_contract_id');
                $join->on('transportation_contract.start_date', '<=', DB::raw("'$today'"));
                $join->on('transportation_contract.end_date', '>=', DB::raw("'$today'"));
            })
            ->whereRaw('transportation_list_prices.transportation_list_id = transportationList')
            ->where('transportation_list_prices.port_id', '=', $portID)
            ->where('transportation_list_prices.is_deleted', '=', 0)
            ->limit(1);
        }])
        ->join('customer_contract', function($join) use ($today) {
            $join->on('customer_contract.customer_contract_id', '=', 'customer_list.customer_contract_id');
            $join->where('customer_contract.status', '=', '1');
            $join->on('customer_contract.start_date', '<=', DB::raw("'$today'"))
            ->where(function ($q) use ($today){
                $q->whereNull('customer_contract.end_date')->orWhere('customer_contract.end_date', '>=', $today);
              });
        })
        ->where('customer_list.customer_id', $customer_id)
        ->get();
        return $query->first();

    }

    public static function getCustomerDeposits($args){
        $query = DB::table('deposit')
        ->select('*')
        ->where('deleted', '0')
        ->where('customer_id', $args['customer_id'])
        ->orderBy('balance', 'desc');
        
        return $query->get()->toArray();
    }

    public static function getCustomerDeposit($args){
        $query = DB::table('deposit')
        ->select('*')
        ->where('deleted', '0')
        ->where('id', $args['deposit_id']);
        
        return $query->first();
    }
    
    static function getDepositDetailShipping($id) {
        return DB::table('deposit')
            ->select('car.lotnumber', 'car.vin', 'car_model.name AS carModelName', 'car_make.name AS carMakerName', 'car.year', 'final_payment_invoices.invoice_numer as invoice_number', 'final_payment_invoices_details.amount_due',
                'final_payment_invoices_details.amount_paid', 'final_payment_invoices_details.remaining_amount', 'final_payment_invoices.total_amount as total', 'final_payment_invoices.create_date as date')
            ->join('final_payment_invoices', 'final_payment_invoices.deposit', '=', 'deposit.id')
            ->join('final_payment_invoices_details', 'final_payment_invoices_details.final_payment_invoices_id', '=', 'final_payment_invoices.final_payment_invoices_id')
            ->join('journal', 'journal.id', '=', 'final_payment_invoices.receipt_journal')
            ->leftJoin('car', 'final_payment_invoices_details.car_id', '=', 'car.id')
            ->leftJoin('car_model', 'car.id_car_model', '=', 'car_model.id_car_model')
            ->leftJoin('car_make', 'car.id_car_make', '=', 'car_make.id_car_make')
            ->where('deposit.id', $id)
            ->get();
    }

    static function getDepositDetailReceipt($id) {
        return DB::table('deposit')
            ->select('journal.debit', 'journal.description', 'journal.description_ar', 'journal.date')
            ->join('journal', 'journal.deposit', '=', 'deposit.id')
            ->join('accounttransaction', 'accounttransaction.Journal_id', '=', 'journal.id')
            ->where('deposit.id', $id)
            ->where('journal.deleted', 0)
            ->groupBy('journal.id')
            ->get();
    }

    static function getDepositDetailTransfer($id) {
        return DB::table('deposit')
            ->select('car.lotnumber', 'car.vin', 'car_model.name AS carModelName', 'car_make.name AS carMakerName', 'bill.Inv_no as invoice_number', 'bill_details.car_cost_aed as amount_due',
                'bill_details.amount_pay as amount_paid', 'bill_details.remaining_amount', 'bill.total_amount as total', 'bill.create_date as date')
            ->join('bill', 'bill.deposit', '=', 'deposit.id')
            ->join('bill_details', 'bill_details.bill_id', '=', 'bill.ID')
            ->leftJoin('car', 'bill_details.car_id', '=', 'car.id')
            ->leftJoin('car_model', 'car.id_car_model', '=', 'car_model.id_car_model')
            ->leftJoin('car_make', 'car.id_car_make', '=', 'car_make.id_car_make')
            ->where('deposit.id', $id)
            ->get();
    }

    public static function shippingCompanyPrices($args = []){

        $activeContractquery = DB::table('shipping_companies_contracts')
        ->selectRaw("MAX(shipping_companies_contracts.contract_id) shipping_contract_id")
        ->where('shipping_companies_contracts.status', '1')
        ->whereRaw('shipping_companies_contracts.shipping_company_id = shipping_companies.company_id')
        ->limit(1);
        $activeContractquery = Helpers::getRawSql($activeContractquery);

        $query = DB::table('shipping_companies')
        ->selectRaw("pol_region.region_id, pol_region.region_name, pod.port_id destination, pod.port_name, shipping_companies.name, shipping_companies_contracts.shipping_company_id,
        shipping_companies_lists_price.price_for_40, shipping_companies_lists_price.price_for_45, shipping_companies_lists_price.price_for_spot")
        ->join('shipping_companies_contracts', function($join) use($activeContractquery) {
            $join->on('shipping_companies_contracts.contract_id', '=', DB::raw("($activeContractquery)"));
        })
        ->join('shipping_companies_lists_price', 'shipping_companies_lists_price.contract_id', '=', 'shipping_companies_contracts.contract_id')
        ->join('port as pol', 'pol.port_id', '=', 'shipping_companies_lists_price.port_id')
        ->join('port as pod', 'pod.port_id', '=', 'shipping_companies_lists_price.destnation_port')
        ->join('region as pol_region', 'pol_region.region_id', '=', 'pol.region_id')
        ->where('shipping_companies.status', '1')
        ->groupBy(['shipping_companies.company_id', 'pol_region.region_id', 'pod.port_id']);

        if($args['region_id']){
            $query->where('pol_region.region_id', '=', $args['region_id']);
        }

        if($args['pod']){
            $query->where('shipping_companies_lists_price.destnation_port', '=', $args['pod']);
        }

        if($args['shipping_company_id']){
            $query->where('shipping_companies.company_id', '=', $args['shipping_company_id']);
        }
        
        return $query->get()->toArray();
    }

    public static function totalContainersByRegion($args = []){
        $query = DB::table('container')
        ->selectRaw('shipping_order.shipping_company_id, shipping_companies.name, warehouse.region_id, region.region_name, car.destination, port.port_id, port.port_name')
        ->selectRaw("COUNT(DISTINCT(IF(container.is_spot,null, container.container_id))) as total_normal_containers")
        ->selectRaw("COUNT(DISTINCT(IF(container.is_spot,container.container_id, null))) as total_spot_containers")

        ->selectRaw("COUNT(DISTINCT(IF(container.is_spot=0, IF(container.size='40',container.container_id, null), null))) as total_normal_containers_40")
        ->selectRaw("COUNT(DISTINCT(IF(container.is_spot=1, IF(container.size='40',container.container_id, null), null))) as total_spot_containers_40")
        ->selectRaw("COUNT(DISTINCT(IF(container.is_spot=0, IF(container.size='45',container.container_id, null), null))) as total_normal_containers_45")
        ->selectRaw("COUNT(DISTINCT(IF(container.is_spot=1, IF(container.size='45',container.container_id, null), null))) as total_spot_containers_45")

        ->join('container_car', 'container_car.container_id', '=', 'container.container_id')
        ->join('car', 'car.id', '=', 'container_car.car_id')
        ->join('booking', 'booking.booking_id', '=', 'container.booking_id')
        ->join('shipping_status', 'shipping_status.booking_id', '=', 'container.booking_id')
        ->join('shipping_order', 'shipping_order.shipping_order_id', '=', 'booking.order_id')
        ->join('shipping_companies', 'shipping_order.shipping_company_id', '=', 'shipping_companies.company_id')
        ->join('warehouse_contract', 'warehouse_contract.warehouse_contract_id', '=', 'container.warehouse_contract_id')
        ->join('warehouse', 'warehouse.warehouse_id', '=', 'warehouse_contract.warehouse_id')
        ->join('port', 'port.port_id', '=', 'car.destination')
        ->join('region', 'region.region_id', '=', 'warehouse.region_id')
        ->where('shipping_status.shipping_status', '1')
        ->groupBy(['car.destination', 'warehouse.region_id', 'shipping_companies.company_id']);

        if($args['date_from']){
            $query->whereDate('shipping_status.shipping_date', '>=', $args['date_from']);
        }

        if($args['date_to']){
            $query->whereDate('shipping_status.shipping_date', '<=', $args['date_to']);
        }

        if($args['region_id']){
            $query->where('warehouse.region_id', '=', $args['region_id']);
        }

        if($args['pod']){
            $query->where('car.destination', '=', $args['pod']);
        }

        if($args['shipping_company_id']){
            $query->where('shipping_companies.company_id', '=', $args['shipping_company_id']);
        }
        
        return $query->get()->toArray();
    }

    public static function loadingCompanyPrices($args = []){

        $activeContractquery = DB::table('warehouse_contract')
        ->selectRaw("MAX(warehouse_contract.warehouse_contract_id) warehouse_contract_id")
        ->where('warehouse_contract.status', '1')
        ->whereRaw('warehouse_contract.warehouse_id = warehouse.warehouse_id')
        ->limit(1);
        $activeContractquery = Helpers::getRawSql($activeContractquery);

        $query = DB::table('warehouse')
        ->selectRAW("region.region_id, region.region_name")
        ->selectRaw("(sum(warehouse_list_price.four_car) / count(warehouse_contract.warehouse_id)) average_price")
        ->join('warehouse_contract', function($join) use($activeContractquery) {
            $join->on('warehouse_contract.warehouse_contract_id', '=', DB::raw("($activeContractquery)"));
        })
        ->join('warehouse_list_price', 'warehouse_list_price.warehouse_contract_id', '=', 'warehouse_contract.warehouse_contract_id')
        ->join('region', 'region.region_id', '=', 'warehouse.region_id')
        ->where('warehouse.status', '1')
        ->groupBy('region.region_id');

        if($args['region_id']){
            $query->where('warehouse.region_id', '=', $args['region_id']);
        }

        if($args['warehouse_id']){
            $query->where('warehouse.warehouse', '=', $args['warehouse_id']);
        }
        
        return $query->get()->toArray();
    }

    public static function getRemainingCarTransfer($AccountID, $car_id){
        $query = DB::table('accounttransaction')
        ->select(DB::raw("SUM(Debit) totalDebit, SUM(Credit) totalCredit"))
        ->where('deleted', '0')
        ->where('AccountID', $AccountID)
        ->where('car_id', $car_id)
        ->where('car_step', 1);
        
        return $query->first();
    }
    
    public static function getGeneratedPriceLists($args){
        if(empty($args['service_id'])) return [];

        $query = DB::table('price_lists')
        ->selectRaw("price_lists.*, users.full_name as creator_name, approved_users.full_name as approved_user_name")
        ->leftJoin('users', 'price_lists.create_by', '=', 'users.user_id')
        ->leftJoin('users as approved_users', 'price_lists.approved_by', '=', 'approved_users.user_id')
        ->where('deleted', '0');

        if($args['service_id']){
            $query->where('price_lists.service_id', '=', $args['service_id']);
        }

        if($args['service_id'] == Constants::NAJ_SERVICES['SHIPPING']){
            $query->selectRaw("shipping_contract.shipping_contract_id list_contract_id");
            $query->leftJoin('shipping_contract', 'shipping_contract.price_list_id','=', 'price_lists.id');
        }
        else if($args['service_id'] == Constants::NAJ_SERVICES['LOADING']){
            $query->selectRaw("loading_contract.loading_contract_id list_contract_id");
            $query->leftJoin('loading_contract', 'loading_contract.price_list_id','=', 'price_lists.id');
        }

        if($args['price_list_id']){
            $query->where('price_lists.id', '=', $args['price_list_id']);
        }

        return $query->get()->toArray();
    }

    public static function getPriceListsDetail($args){

        $query = DB::table('price_list_detail')
        ->selectRaw("price_list_detail.*");

        if($args['price_list_id']){
            $query->where('price_list_detail.price_list_id', '=', $args['price_list_id']);
        }

        if($args['sale_list_id']){
            $query->where('price_list_detail.sale_list_id', '=', $args['sale_list_id']);
        }
        
        return $query->get()->toArray();
    }
    
    public static function editGeneratedPriceList($args){
        if(empty($args['price_list_id'])) return [];

        $query = DB::table('price_lists')
        ->selectRaw("price_lists.*, price_list_detail.*, users.full_name as creator_name")
        ->join('price_list_detail', 'price_list_detail.price_list_id', '=', 'price_lists.id')
        ->leftJoin('users', 'price_lists.create_by', '=', 'users.user_id')
        ->where('price_lists.id', $args['price_list_id']);

        return $query->get()->toArray();
    }
    
    public static function getShippingBrokerCommission($args){

        $query = DB::table('shipping_broker')
        ->selectRaw("shipping_broker.*, shipping_broker_commission.amount")
        ->join('shipping_broker_commission', 'shipping_broker_commission.id', '=', 'shipping_broker.shipping_commission');

        if($args['shipping_broker']){
            $query->where('shipping_broker.id', '=', $args['shipping_broker']);
        }

        return $query->first();
    }
    
    public static function getListPrices($args){
        if(empty($args)) return [];

        switch($args['service_id']){
            case Constants::NAJ_SERVICES['SHIPPING']:

                $query = DB::Table('shipping');
                $query->selectRaw('shipping.*, shipping_list.title list_name, pol.port_name pol_name, pod.port_name pod_name, pol_region.region_name pol_region_name,
                shipping_contract.contract_title');
                $query->leftJoin('port as pol', 'pol.port_id', '=', 'shipping.port_id');
                $query->leftJoin('port as pod', 'pod.port_id', '=', 'shipping.port_id_distination');
                $query->leftJoin('region as pol_region', 'pol_region.region_id', '=', 'pol.region_id');
                $query->leftJoin('shipping_list', 'shipping_list.shipping_list_id', '=', 'shipping.shipping_list_id');
                $query->leftJoin('shipping_contract', 'shipping_contract.shipping_contract_id', '=', 'shipping.shipping_contract_id');

                if(!empty($args['list_contract_id'])){
                    $query->where('shipping.shipping_contract_id', $args['list_contract_id']);
                }
                if(!empty($args['list_id'])){
                    $query->where('shipping.shipping_list_id', $args['list_id']);
                }

                break;
            case Constants::NAJ_SERVICES['LOADING']:

                $query = DB::Table('loading_list_price');
                $query->selectRaw('loading_list_price.*, region.region_name');
                $query->leftJoin('region', 'region.region_id', '=', 'loading_list_price.region_id');
                $query->groupBy('loading_list_price.region_id');

                if(!empty($args['list_contract_id'])){
                    $query->where('loading_list_price.loading_contract_id', $args['list_contract_id']);
                }
                if(!empty($args['list_id'])){
                    $query->where('loading_list_price.loading_list_id', $args['list_id']);
                }

                break;

            case Constants::NAJ_SERVICES['TOWING']:

                $query = DB::Table('towing');
                $query->selectRaw('towing.*, cities.name city_name, region.region_name');
                $query->leftJoin('region_cities', 'region_cities.city_id', '=', 'towing.city_id');
                $query->leftJoin('region', 'region.region_id', '=', 'region_cities.region_id');
                $query->leftJoin('cities', 'cities.id', '=', 'region_cities.city_id');
                $query->groupBy('towing.city_id');

                if(!empty($args['list_contract_id'])){
                    $query->where('towing.towing_contract_id', $args['list_contract_id']);
                }
                if(!empty($args['list_id'])){
                    $query->where('towing.towing_list_id', $args['list_id']);
                }

                break;

            case Constants::NAJ_SERVICES['CLEARANCE']:

                $query = DB::Table('clearance_list_price');
                $query->selectRaw('clearance_list_price.*');

                if(!empty($args['list_contract_id'])){
                    $query->where('clearance_list_price.clearance_contract_id', $args['list_contract_id']);
                }
                if(!empty($args['list_id'])){
                    $query->where('clearance_list_price.clearance_list_id', $args['list_id']);
                }

                break;

            case Constants::NAJ_SERVICES['TRANSPORTATION']:

                $query = DB::Table('transportation_list_prices');
                $query->selectRaw('transportation_list_prices.*');
                $query->groupBy('transportation_list_prices.port_id');

                if(!empty($args['list_contract_id'])){
                    $query->where('transportation_list_prices.transportation_contract_id', $args['list_contract_id']);
                }
                if(!empty($args['list_id'])){
                    $query->where('transportation_list_prices.transportation_list_id', $args['list_id']);
                }

                break;

            default :
                return [];
                break;
        }

        return $query->get()->toArray();
    }

    public static function getBusinessServicesCustomers($customerId){
        $today = date('Y-m-d');
        $query = DB::table('business_services_exclude')
        ->select('business_services_exclude.service_id')
        ->join('customer_contract', function($join) use ($today) {
            $join->on('customer_contract.customer_contract_id', '=', 'business_services_exclude.customer_contract_id');
            $join->where('customer_contract.status', '=', '1');
            $join->on('customer_contract.start_date', '<=', DB::raw("'$today'"))
            ->where(function ($q) use ($today){
                $q->whereNull('customer_contract.end_date')->orWhere('customer_contract.end_date', '>=', $today);
              });
        })
        ->where('business_services_exclude.customer_id','=', $customerId)
        ->get();
        $result = $query->toArray();
        return array_column($result,'service_id');
        
        
      }
}