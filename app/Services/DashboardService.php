<?php

namespace App\Services;

use App\Libraries\Helpers;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public static function getnotOpenednotif($args){

        $query = DB::Table('app_notification')
                    ->select('*')
                    ->where('opened', '0');

        if (!empty($args['customer_id'])) {
            $query->where('customer_id', $args['customer_id']);
        }

        return $query->get()->toArray();
    }

    public static function dashboardCounts(){

        $query = DB::Table('users')
                    ->select('full_name')
                    ->where('user_id', 1)
                    ->get();

        return $query->toArray();
    }

    public static function siteAdvertisment(){
        $slider_en = '';
        $slider_ar = '';
        $advertisements = DB::Table('site_advertisement')
                            ->select('*')
                            ->where([
                                ['status', 1],
                                ['deleted',0],
                                ['expiry_date', '>=', date('Y-m-d')],
                            ])->whereNull('type_id')
                            ->get()->toArray();
        $siteAdvertisment = response()->json($advertisements);
        return $siteAdvertisment;
    }

    //NEW

    public static function allCarsDetailsCount($args){

        $select = 'car.lotnumber,car.vin,car.year, car.purchasedate,car.auction_location_id,car.photo , car_make.name AS carMakerName , car_model.name AS carModelName, vehicle_type.name AS vehicleName,
                   auction.title AS aTitle, auction_location.auction_location_name,region.region_name ,port.port_name,color.color_name,CAST(bill_details.create_date AS DATE) as paymentDate,towing_status.picked_date,arrived_car.delivered_date,arrived_car.delivered_title,arrived_car.delivered_car_key,arrived_car.car_id,CAST(cars_title_status.create_date AS DATE) as titleDate,cars_title_status.follow_car_title_note,
                   loaded_status.loaded_date,container.container_number,
                   booking.booking_number,booking.eta,booking.etd, shipping_status.shipping_date';

        $carsShippingStatusCount = DB::Table('car')
                                    ->where('deleted', '0')
                                    ->where('cancellation', '0')
                                    ->where('arrived_port', '0')
                                    ->where('customer_id', $args['customer_id'])
                                    ->distinct('car.id')->count('car.id as num');

        $select .= $carsShippingStatusCount;

        $query = DB::Table('car')
                ->leftJoin('car_make', 'car.id_car_make', '=', 'car_make.id_car_make')
                ->leftJoin('buyer', 'car.buyer_id', '=', 'buyer.buyer_id')
                ->leftJoin('auction', 'car.auction_id', '=', 'auction.id')
                ->leftJoin('auction_location', 'car.auction_location_id', '=', 'auction_location.auction_location_id')
                ->leftJoin('region', 'auction_location.region_id', '=', 'region.region_id')
                ->leftJoin('countries', 'countries.id', '=', 'region.country_id')
                ->leftJoin('port', 'car.destination', '=', 'port.port_id')
                ->leftJoin('car_model', 'car.id_car_model', '=', 'car_model.id_car_model')
                ->leftJoin('vehicle_type', 'car.id_vehicle_type', '=', 'vehicle_type.id_vehicle_type')
                ->leftJoin('customer', 'car.customer_id', '=', 'customer.customer_id')
                ->leftJoin('arrived_car', 'arrived_car.car_id', '=', 'car.id')
                ->leftJoin('shipping_order_car', 'car.id', '=', 'shipping_order_car.car_id')
                ->leftJoin('container_car', 'container_car.car_id', '=', 'car.id')
                ->leftJoin('booking', 'booking.booking_id', '=', 'container_car.booking_id')
                ->leftJoin('loaded_status', 'loaded_status.booking_id' , '=' , 'booking.booking_id')
                ->leftJoin('shipping_status', 'booking.booking_id','=','shipping_status.booking_id')
                ->leftJoin('color', 'car.color', '=','color.color_id')
                ->leftJoin('towing_status', 'towing_status.car_id','=','car.id')
                ->leftJoin('bill_details', 'bill_details.car_id','=','car.id')
                ->leftJoin('cars_title_status', 'cars_title_status.car_id','=','car.id')
                ->where('cars_title_status.follow_title', '1')
                ->where('car.deleted', '0')
                ->where('car.cancellation', '0');

        if (!empty($args['customer_id'])) {
            $query->where('customer.customer_id', $args['customer_id']);
        }

        if(!empty($args['length']) && !empty($args['start'])){
            $query->skip($args['start'])->take($args['length'])->get();
        }    
        
        return $query->groupBy('car.id')
              ->get()
              ->count();
    }

    public static function cancelledCount($args){

        $select = 'car.carcost,car.lotnumber,car.vin,car.year, car.purchasedate,car.auction_location_id,car.photo , car_make.name AS carMakerName , car_model.name AS carModelName, vehicle_type.name AS vehicleName, '
        . 'auction.title AS aTitle, auction_location.auction_location_name,port.port_name, customer.full_name AS CustomerName,color.color_name, car.id';
        
        $query = DB::Table('car')
                    ->leftJoin('car_make', 'car.id_car_make', '=', 'car_make.id_car_make')
                    ->leftJoin('auction', 'car.auction_id', '=', 'auction.id')
                    ->leftJoin('auction_location', 'car.auction_location_id', '=', 'auction_location.auction_location_id')
                    ->leftJoin('region', 'auction_location.region_id', '=', 'region.region_id')
                    ->leftJoin('countries', 'countries.id', '=', 'region.country_id')
                    ->leftJoin('port', 'car.destination', '=', 'port.port_id')
                    ->leftJoin('car_model', 'car.id_car_model', '=', 'car_model.id_car_model')
                    ->leftJoin('vehicle_type', 'car.id_vehicle_type', '=', 'vehicle_type.id_vehicle_type')
                    ->leftJoin('customer', 'car.customer_id', '=', 'customer.customer_id')
                    ->leftJoin('color', 'car.color', '=','color.color_id')
                    ->where('car.deleted', '0')
                    ->where('external_car', '0')
                    ->where('car_payment_to_cashier', '0')
                    ->where('cancellation', '1');

        if (!empty($args['customer_id'])) {
            $query->where('customer.customer_id', $args['customer_id']);
        }

        $query->select(DB::raw($select));
        return $query->orderBy("car.create_date", "desc")
              ->get()
              ->count();
    }

    public static function getnotOpenedreminders($args){

        $query = DB::Table('app_reminders')
                    ->select('*')
                    ->where('opened', '0');

        if (!empty($args['customer_id'])) {
            $query->where('customer_id', $args['customer_id']);
        }
        
        return $query->get()->toArray();
    }

    public static function checkDisplyingBLFiles($args){

        $query = DB::Table('customer_file')
                    ->select('customer_file.customer_file_id as id')
                    ->where('customer_file.file_tag', 'BL');

        if (!empty($args['customer_id'])) {
            $query->where('customer_file.customer_id', $args['customer_id']);
        }
        
        return $query->limit(1)->get()->toArray();
    }

    public static function checkDisplyingPricesFiles($args){

        $query = DB::Table('customer_file')
                    ->select('customer_file.customer_file_id as id')
                    ->where('customer_file.file_tag', 'price');

        if (!empty($args['customer_id'])) {
            $query->where('customer_file.customer_id', $args['customer_id']);
        }
        
        return $query->limit(1)->get()->toArray();
    }

    public static function newCarscount($args) {

        $query = DB::Table('car')
                    ->leftJoin('auction_location', 'car.auction_location_id', '=', 'auction_location.auction_location_id')
                    ->leftJoin('region', 'auction_location.region_id', '=', 'region.region_id')
                    ->leftJoin('car_make', 'car.id_car_make', '=', 'car_make.id_car_make')
                    ->leftJoin('auction', 'car.auction_id','=','auction.id')
                    ->leftJoin('countries', 'countries.id','=','region.country_id')
                    ->leftJoin('bill_details', 'bill_details.car_id','=','car.id')
                    ->leftJoin('port', 'car.destination','=','port.port_id')
                    ->leftJoin('car_model', 'car.id_car_model','=','car_model.id_car_model')
                    ->leftJoin('vehicle_type', 'car.id_vehicle_type','=','vehicle_type.id_vehicle_type')
                    ->leftJoin('towing_status', 'towing_status.car_id','=','car.id')
                    ->leftJoin('customer', 'car.customer_id','=','customer.customer_id')
                    ->leftJoin('color', 'car.color','=','color.color_id')
                    ->where('car.status','!=','4')
                    ->where('car.deleted','0')
                    ->where('car.cancellation','0')
                    ->where('car.towingstatus','0')
                    ->where('car.deliver_customer','!=','1');

        if (!empty($args['customer_id'])) {
            $query->where('customer.customer_id', $args['customer_id']);
        }

        return $query->get()->count('* as count_cars');
    }

    public static function towingCarscount($args) {

        $query = DB::Table('car')
                    ->leftJoin('customer', 'car.customer_id','=','customer.customer_id')
                    ->where('car.deleted','0')
                    ->where('towingstatus','1')
                    ->where('arrivedstatus','0');

        if (!empty($args['customer_id'])) {
            $query->where('car.customer_id', $args['customer_id']);
        }

        return $query->get()->count('* as count_towing');
    }

    public static function warehouseCarscount($args) {

        $query = DB::Table('car')
                    ->leftJoin('customer', 'car.customer_id','=','customer.customer_id')
                    ->where('car.deleted','0')
                    ->where('loading_status','0')
                    ->where('arrivedstatus','1')
                    ->where('car.status','!=','4');

        if (!empty($args['customer_id'])) {
            $query->where('car.customer_id', $args['customer_id']);
        }

        return $query->get()->count('* as count_warehouse');
    }
    
    public static function loadingCarscount($args) {

        $query = DB::Table('car')
                    ->leftJoin('customer', 'car.customer_id','=','customer.customer_id')
                    ->where('car.deleted','0')
                    ->where('loading_status','1')
                    ->where('shipping_status','0');

        if (!empty($args['customer_id'])) {
            $query->where('car.customer_id', $args['customer_id']);
        }

        return $query->get()->count('* as count_loading');
    }
    
    public static function shippingCarscount($args) {

        $query = DB::Table('car')
                    ->where('car.deleted','0')
                    ->where('car.arrived_port','0')
                    ->where(function($q) {
                        $q->where('car.shipping_status', '1')
                        ->orWhere('car.loading_status', '1');
                    });

        if (!empty($args['customer_id'])) {
            $query->where('customer_id', $args['customer_id']);
        }

        return $query->distinct('car.id')->count('car.id as count_shipping');
    }
    
    public static function portCarscount($args) {
       
        $query = DB::Table('car')
        ->leftJoin('customer', 'car.customer_id','=','customer.customer_id')
        ->leftJoin('container_car', 'container_car.car_id','=','car.id')
        ->leftJoin('container', 'container.container_id','=','container_car.container_id')
        ->where('car.deleted','0')
        ->where('container.clearance_by_customer','0')
        ->where('arrived_port','1')
        ->where('arrive_store','0')
        ->where('car.deliver_customer','0');

        if (!empty($args['customer_id'])) {
            $query->where('car.customer_id', $args['customer_id']);
        }

        return $query->get()->count('* as count_port');   
    }
    
    public static function storeCarscount($args) {

        $query = DB::Table('car')
                    ->leftJoin('customer', 'car.customer_id','=','customer.customer_id')
                    ->where('car.deleted','0')
                    ->where('arrive_store','1')
                    ->where('deliver_customer','0');

        if (!empty($args['customer_id'])) {
            $query->where('car.customer_id', $args['customer_id']);
        }

        return $query->get()->count('* as count_store');
    }

    public static function GetSumBalance($args){

        $query = DB::Table('customer');

        if (!empty($args['customer_id'])) {
            $query->where('customer_id', $args['customer_id']);
        }
        
        $accountId = $query->first()->account_id;

        return DB::Table('accounttransaction')
                    ->where('AccountID',$accountId)
                    ->where('deleted','0')
                    ->get( array(
                            DB::raw('SUM(Debit) as Debit'),
                            DB::raw('SUM(Credit) as Credit')
                        ));
    }

    public static function calcualteStorageFinesInUAE($args){

        $select = 'car.* ,receive_car.deliver_status';
        $query = DB::Table('car')
                    ->leftJoin('receive_car', 'car.id','=','receive_car.car_id')
                    ->join('final_bill', 'final_bill.car_id','=','car.id')
                    ->select(DB::raw($select))
                    ->where('car.deleted','0');

        if (!empty($args['customer_id'])) {
            $query->where('car.customer_id', $args['customer_id']);
        }

        if (!empty($args['date_to'])) {
            $query->where('DATE(final_bill.create_date)','<',$args['date_to']);
        }

        $result = $query->get()->toArray();

        $calculateStorage = 0;
        $totalFines = 0;

        foreach ($result as $key => $row) {
            if($row->deliver_status == 1 && $row->final_payment_status == 1)
            {
                $calculateStorage = 0;
            }else
            {
                $calculateStorage = \App\Services\CarService::storageFine($row->id);
                $totalFines += $calculateStorage['fine'];
            }
        }

        return $totalFines;
    }

    public static function getGeneralNotification ($args) {

        $query = DB::Table('general_notification')
                    ->select('*')
                    ->where('opened','0')
                    ->where('deleted','0');

        if (!empty($args['customer_id'])) {
            $query->where('customer_id', $args['customer_id']);
        }

        return $query->orderBy("created_date", "desc")->get();
    }

    public static function checkPopupAnnouncement ($id){
        
        $select = 'general_notification.*, site_advertisement.*, general_notification.id as id,
                   DATEDIFF(site_advertisement.create_date ,CURDATE()) as diff,
                   CAST(general_notification.created_date AS DATE) as created_date';

        $query = DB::Table('general_notification')
                    ->leftJoin('site_advertisement', 'site_advertisement.id' ,'=' ,'general_notification.ads_id')
                    ->where('general_notification.deleted', '0')
                    ->where('site_advertisement.expiry_date','>=', date("Y-m-d"))
                    ->where('site_advertisement.status', '1');

        if (!empty($args['customer_id'])) {
            $query->where('general_notification.customer_id', $args['customer_id']);
        }

        $having = 'abs(diff) <= site_advertisement.alert_length or abs(diff) = 0';
        $query->groupBy('general_notification.ads_id')
                ->having(DB::raw($having));  
                
        $query->orderBy('created_date','DESC');
        
        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }

    public static function ActivateAdminAccess ($args) {

        $query = DB::Table('customer')
                    ->select('customer.customer_id')
                    ->where('customer.status', '1')
                    ->where('customer.is_blocked', '0')
                    ->where('customer.is_deleted', '0');

        if (!empty($args['customer_id'])) {
            $query->where('customer.customer_id', $args['customer_id']);
        }

        if (!empty($args['code'])) {
            $query->where('customer.admin_code', 'like', '%' . $args['code'] . '%');
        }

        $resultCount = count($query->limit(1)->get()->toArray());

        if($resultCount > 0){
        	$data = array('logged_in' => '1', 'activated_admin_status' => '0');

            DB::table('user_devices')
                ->where('activated_admin_status', '1')
                ->where('customer_id', $args['customer_id'])
                ->update($data);

            DB::table('user_devices')
                ->where('logged_in', '0')
                ->where('deleted', '0')
                ->where('customer_id', $args['customer_id'])
                ->where('Device_push_regid', 'like', '%' . $args['Device_push_regid'] . '%')
                ->update(array('activated_admin_status' => '1'));

	        return TRUE;
        }else {
        	return FALSE;
        } 
    }

    public static function getProfileData($args) {
        $today = date('Y-m-d');
        $HANI_SAEED = 1839;

        $query = DB::Table('customer')
                    ->select('customer.customer_id', 'customer.membership_id', 'full_name', 'full_name_ar', 'primary_email', 'customer_type', 'phone', 'customer_contract.customer_contract_id', 'customer_list.bulk_shipLoad', 'customer.naj_branch')
                    ->selectRaw("IF(customer_contract.towing_payment_fee > 0, true, false) as allowWarehouseCarsRequests, customer.external_car_contact,
                    IF(customer.naj_branch=1 || customer.customer_id=$HANI_SAEED, 1, 0) allow_arrived_to_port")
                    ->leftJoin('customer_contract', function($join) use ($today) {
                        $join->on('customer_contract.customer_id', '=', 'customer.customer_id');
                        $join->on('customer_contract.status', '=', DB::raw("'1'"));
                        $join->on('customer_contract.start_date', '<=', DB::raw("'$today'"))
                        ->where(function ($q) use ($today){
                            $q->whereNull('customer_contract.end_date')->orWhere('customer_contract.end_date', '>=', $today);
                          });
                    })
                    ->leftJoin('customer_list', 'customer_list.customer_contract_id', 'customer_contract.customer_contract_id')
                    ->where('is_deleted', '0')
                    ->where('is_blocked', '0');

        if (!empty($args['customer_id'])) {
            $query->where('customer.customer_id', $args['customer_id']);
        }
        return $query->get()->toArray();
    }

    public static function dashboardCarsCount($args){
        $customer_id = $args['customer_id'];

        $allCarsCountSql = DB::Table('car')
        ->select(DB::raw('COUNT(DISTINCT(car.id))'))
        ->where('deleted', 0)
        ->where('cancellation', 0)
        ->where('customer_id', $customer_id);

        $allCarsCountSql = Helpers::getRawSql($allCarsCountSql);

        $newCarsUnpaidCountSql = DB::Table('car')
        ->select(DB::raw('COUNT(DISTINCT(car.id))'))
        ->where('deleted', 0)
        ->where('towingstatus', '=', '0')
        ->where('deliver_customer', '<>', '1')
        ->where('car.status', '<>', '4')
        ->where('car_payment_to_cashier', 0)
        ->where('cancellation', 0)
        ->where('customer_id', $customer_id)
        ->where(function($query) {
            $query->where('car.external_car', '=', '0')
            ->orWhere(function($query) {
                $query->where('car.external_car', '2')
                  ->where('car.loading_status', '=', '0');
            })
            ->orWhere(function($query) {
                $query->where('car.external_car', '1');
            });
    });

        $newCarsUnpaidCountSql = Helpers::getRawSql($newCarsUnpaidCountSql);

        $newCarsPaidCountSql = DB::Table('car')
        ->select(DB::raw('COUNT(DISTINCT(car.id))'))
        ->where('deleted', 0)
        ->where('towingstatus', '=', '0')
        ->where('deliver_customer', '<>', '1')
        ->where('car.status', '<>', '4')
        ->where('car_payment_to_cashier', 1)
        ->where('cancellation', 0)
        ->where('customer_id', $customer_id)
        ->where(function($query) {
            $query->where('car.external_car', '=', '0')
            ->orWhere(function($query) {
                $query->where('car.external_car', '2')
                  ->where('car.loading_status', '=', '0');
            })
            ->orWhere(function($query) {
                $query->where('car.external_car', '1');
            });
        });

        $newCarsPaidCountSql = Helpers::getRawSql($newCarsPaidCountSql);

        $newCarsPaidByCustomerCountSql = DB::Table('car')
        ->select(DB::raw('COUNT(DISTINCT(car.id))'))
        ->where('deleted', 0)
        ->where('towingstatus', '=', '0')
        ->where('deliver_customer', '<>', '1')
        ->where('car.status', '<>', '4')
        ->where('car_payment_to_cashier', 3)
        ->where('cancellation', 0)
        ->where('customer_id', $customer_id)
        ->where(function($query) {
            $query->where('car.external_car', '=', '0')
            ->orWhere(function($query) {
                $query->where('car.external_car', '2')
                  ->where('car.loading_status', '=', '0');
            })
            ->orWhere(function($query) {
                $query->where('car.external_car', '1');
            });
        });

        $newCarsPaidByCustomerCountSql = Helpers::getRawSql($newCarsPaidByCustomerCountSql);

        $newCarsCancelledCountSql = DB::Table('car')
        ->select(DB::raw('COUNT(DISTINCT(car.id))'))
        ->where('deleted', 0)
        ->where('external_car', 0)
        ->where('car_payment_to_cashier', 0)
        ->where('cancellation', 1)
        ->where('customer_id', $customer_id);

        $newCarsCancelledCountSql = Helpers::getRawSql($newCarsCancelledCountSql);

        $newCarsPickedCountSql = DB::Table('car')
        ->select(DB::raw('COUNT(DISTINCT(car.id))'))
        ->where('deleted', 0)
        ->where('towingstatus', 1)
        ->where('arrivedstatus', 0)
        ->where('status_of_issue', 0)
        ->where('car.status','!=', '4')
        ->where('customer_id', $customer_id);

        $newCarsPickedCountSql = Helpers::getRawSql($newCarsPickedCountSql);

        $carsOnWarehouseCountSql = DB::Table('car')
        ->leftJoin('car_total_cost', 'car.id','=','car_total_cost.car_id')
        ->select(DB::raw('COUNT(DISTINCT(car.id))'))
        ->where('deleted', 0)
        ->where('arrivedstatus', 1)
        ->where('car.status','!=', '4')
        ->where('loading_status', 0)
        ->where('status_of_issue', 0)
        ->where('customer_id', $customer_id)
        ->whereNull("car_total_cost.car_id");

        $carsOnWarehouseCountSql = Helpers::getRawSql($carsOnWarehouseCountSql);

        $carsLoadingStatusCountSql = DB::Table('car')
        ->leftJoin('car_total_cost', 'car.id','=','car_total_cost.car_id')
        ->select(DB::raw('COUNT(DISTINCT(car.id))'))
        ->where('deleted', 0)
        ->where('loading_status', 1)
        ->where('shipping_status', 0)
        ->where('customer_id', $customer_id)
        ->whereNull("car_total_cost.car_id");

        $carsLoadingStatusCountSql = Helpers::getRawSql($carsLoadingStatusCountSql);

        $carsShippingStatusCountSql = DB::Table('car')
        ->leftJoin('car_total_cost', 'car.id','=','car_total_cost.car_id')
        ->select(DB::raw('COUNT(DISTINCT(car.id))'))
        ->where('deleted', 0)
        ->whereRaw('(loading_status = 1 OR shipping_status = 1)')
        ->where('arrived_port', 0)
        ->where('customer_id', $customer_id)
        ->whereNull("car_total_cost.car_id");

        $carsShippingStatusCountSql = Helpers::getRawSql($carsShippingStatusCountSql);

        $carsArrivedPortCountSql = DB::Table('car')
        ->select(DB::raw('COUNT(DISTINCT(car.id))'))
        ->leftJoin('container_car', 'container_car.car_id', '=', 'car.id')
        ->leftJoin('container', 'container.container_id', '=', 'container_car.container_id')
        ->leftJoin('car_total_cost', 'car.id','=','car_total_cost.car_id')
        ->where('deleted', 0)
        ->where('arrived_port', 1)
        ->where('arrive_store', 0)
        ->where('container.clearance_by_customer','=','0')
        ->where('car.deliver_customer','=', '0')
        ->where('customer_id', $customer_id)
        ->whereNull("car_total_cost.car_id");

        $carsArrivedPortCountSql = Helpers::getRawSql($carsArrivedPortCountSql);

        $carsArrivedStoreCountSql = DB::Table('car')
        ->select(DB::raw('COUNT(DISTINCT(car.id))'))
        ->where('deleted', 0)
        ->where('arrive_store', 1)
        ->where('deliver_customer', 0)
        ->where('customer_id', $customer_id);

        $carsArrivedStoreCountSql = Helpers::getRawSql($carsArrivedStoreCountSql);

        $carsDeliverdPaidCountSql = DB::Table('car')
        ->select(DB::raw('COUNT(DISTINCT(car.id))'))
        ->where('deleted', 0)
        ->where('deliver_customer', 1)
        ->where('final_payment_status', 1)
        ->where('customer_id', $customer_id);

        $carsDeliverdPaidCountSql = Helpers::getRawSql($carsDeliverdPaidCountSql);

        $carsDeliverdUnPaidCountSql = DB::Table('car')
        ->select(DB::raw('COUNT(DISTINCT(car.id))'))
        ->where('deleted', 0)
        ->where('deliver_customer', 1)
        ->where('final_payment_status', 0)
        ->where('customer_id', $customer_id);

        $carsDeliverdUnPaidCountSql = Helpers::getRawSql($carsDeliverdUnPaidCountSql);

        $query = DB::Table('car')
                ->select(DB::raw("
                ($allCarsCountSql) as allCarsCount,
                ($newCarsUnpaidCountSql) as newCarsUnpaidCount,
                ($newCarsPaidCountSql) as newCarsPaidCount,
                ($newCarsPaidByCustomerCountSql) as newCarsPaidByCustomerCount,
                ($newCarsCancelledCountSql) as newCarsCancelledCount,
                ($newCarsPickedCountSql) as newCarsPickedCount,
                ($carsOnWarehouseCountSql) as carsOnWarehouseCount,
                ($carsLoadingStatusCountSql) as carsLoadingStatusCount,
                ($carsShippingStatusCountSql) as carsShippingStatusCount,
                ($carsArrivedPortCountSql) as carsArrivedPortCount,
                ($carsArrivedStoreCountSql) as carsArrivedStoreCount,
                ($carsDeliverdPaidCountSql) as carsDeliverdPaidCount,
                ($carsDeliverdUnPaidCountSql) as carsDeliverdUnPaidCount
                "));
        return ($query->first());
    }
}
