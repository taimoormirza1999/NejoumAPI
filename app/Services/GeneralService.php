<?php

namespace App\Services;

use App\Models\CarAccounting;
use App\Libraries\Helpers;
use App\Libraries\Constants;
use App\Models\Store;
use App\Http\Controllers\Car\ImgController;
use Illuminate\Support\Facades\DB;
use DateTime;
use Illuminate\Http\Request;
class GeneralService
{
    const CUSTOMER_SERVICE_ROLE = 15;

    public static function getAuctionLocations($args)
    {
        $customer_id = $args['auction'];
        $query = DB::Table('customer_list')
        ->where([
            ['customer_list.customer_id', $customer_id]
        ])->select('*');
        return $query;
    }

    public static function addContactUsMessage($data){
        return DB::Table('contact_message')->insertGetId($data);
    }
    public static function addMarketingMessage($data){
        return DB::connection('mysql_marketing')->Table('contact_form')->insertGetId($data);
    }
    public static function getVehicleType(){
        $query = DB::Table('vehicle_type')
        ->where([
            ['is_deleted', 0]
        ])->select('*');
        return $query->get()->toArray();
    }

    public static function getAuction(){
        $query = DB::Table('auction')
        ->where([
            ['status', 1]
        ])->select('*');
        return $query->get()->toArray();
    }

    public static function getAuctionLocation($args){
        $auction_id = $args['auction_id'];
        $query = DB::Table('auction_location')
        ->where([
            ['auction_id', $auction_id],
            ['status', 1]
        ])->select('*');
        return $query->get()->toArray();
    }

    public static function getCountries(){
        $query = DB::Table('countries')->select('*');
        return $query->get()->toArray();
    }

    public static function getRegions($params=[]){
        $query = DB::Table('region')->selectRaw('region.region_id, region.region_name, countries.shortname country_shortname')
        ->join('countries', 'countries.id', '=', 'region.country_id')
        ->where('status', '1');

        if(!empty($params['country_id'])){
            $query->whereIn('country_id', $params['country_id']);
        }
        return $query->get()->toArray();
    }

    public static function getStates($params=[]){
        $query = DB::Table('region_cities')->selectRaw('states.id state_id, states.name state_name, states.state_code')
        ->join('states', 'states.id', '=', 'region_cities.state_id')
        ->where('region_cities.status', '1')
        ->groupBy('states.id');

        if(!empty($params['region_id'])){
            $query->where('region_cities.region_id', $params['region_id']);
        }
        return $query->get()->toArray();
    }

    public static function getCities($params=[]){
        $query = DB::Table('region_cities')->selectRaw('cities.id city_id, cities.name city_name')
        ->join('cities', 'cities.id', '=', 'region_cities.city_id')
        ->where('region_cities.status', '1')
        ->groupBy('cities.id');


        if(!empty($params['state_id'])){
            $query->where('region_cities.state_id', $params['state_id']);
        }
        return $query->get()->toArray();
    }

    public static function getBankAccounts($params=[]){
        $query = DB::Table('account_home')->selectRaw('ID, AccountName')
        ->where('Status', '1');

        if(!empty($params['ID'])){
            $query->whereIn('ID', $params['ID']);
        }
        return $query->get()->toArray();
    }

    public static function getOperationNotesArray($args){
        if(empty($args['type'])) return [];

        $query = DB::table('operations_notes')
        ->selectRaw("operations_notes.*, users.full_name as creator_name")
        ->join('users', 'users.user_id', '=', 'operations_notes.created_by')
        ->where('operations_notes.type', $args['type'])
        ->where('operations_notes.car_id', $args['car_id'])
        ->where('operations_notes.deleted', 0)
        ->orderBy('operations_notes.sort_order');

        if(!empty($args['note_id'])){
            $query->where('operations_notes.id', $args['note_id']);
        };

        return $query->get()->toArray();
    }

    public static function getRegionAuctionLocations($args){

        $query = DB::table('auction_location')
        ->selectRaw("region.region_id, region.region_name, region.short_name region_code, states.id state_id, states.name state, states.state_code, cities.id city_id, cities.name city, auction_location.auction_location_name")
        ->join('region', 'region.region_id', '=', 'auction_location.region_id')
        ->join('states', 'states.id', '=', 'auction_location.state_id')
        ->join('cities', 'cities.id', '=', 'auction_location.city_id')
        ->where('auction_location.status', '1')
        ->orderBy('region.region_id')
        ->orderBy('states.name')
        ->groupBy('auction_location.city_id');

        if($args['country_id']){
            $query->where('auction_location.country_id', $args['country_id']);
        }

        return $query->get()->toArray();
    }

    public static function getColors()
    {
        $query = DB::Table('color')->select('*');
        return $query->get()->toArray();
    }

    public static function getVehicleTypes()
    {
        $query = DB::Table('vehicle_type')->selectRaw('vehicle_type.id_vehicle_type, vehicle_type.name')
        ->where(['vehicle_type.is_deleted' => 0]);
        return $query->get()->toArray();
    }

    public static function getMakerAll()
    {
        $query = DB::Table('car_make')->selectRaw('car_make.id_car_make, car_make.name')
        ->orderBy('car_make.name')
        ->where(['car_make.is_deleted' => 0]);
        return $query->get()->toArray();
    }

    public static function getModelAll($args)
    {
        $query = DB::Table('car_model')->selectRaw('car_model.id_car_model, car_model.name')
        ->orderBy('car_model.name')
        ->where(['car_model.id_car_make' => $args['maker_id']])
        ->where(['car_model.is_deleted' => 0]);
        return $query->get()->toArray();
    }

    public static function getTowingCases($args){
        $query = DB::table('towing_cases')
        ->selectRaw('CONCAT("' . Constants::NEJOUM_CDN . 'uploads/' . '", car.photo) as photo , car.towingstatus as ableToresponse, towing_cases.message, towing_cases.message_ar, towing_cases.subject, towing_cases.subject_ar,
        towing_cases.response_time, towing_cases.response_time_unit, towing_case_map.car_id as car_id, towing_case_map.id, towing_case_map.response,
        CAST(towing_cases.create_date AS DATE) as created_date, towing_case_map.reject_message,
        IF(towing_case_map.attachment != "0", CONCAT("' . Constants::NEJOUM_CDN . '' . '", towing_case_map.attachment), NULL) as attachment,
        car.lotnumber, car.vin, car.year, car.purchasedate, auction.title AS auction_title, car_make.name AS carMakerName , car_model.name AS carModelName')
        ->leftJoin('towing_case_map', 'towing_case_map.case_id', '=', 'towing_cases.id')
        ->leftJoin('car', 'car.id', '=', 'towing_case_map.car_id')
        ->leftJoin('car_make', 'car_make.id_car_make', '=', 'car.id_car_make')
        ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
        ->leftJoin('auction', 'auction.id', '=', 'car.auction_id')
        ->where('car.customer_id', $args)
        ->groupBy('towing_case_map.car_id')
        ->orderby('towing_case_map.create_date');
        return $query->get()->toArray();
    }

    // Authorized Receiver
    public static function getAllAuthorizedReceiver($customer_id){
        $request = DB::Table('authorized_receiver')
            ->selectRaw('*, CONCAT("' . Constants::NEJOUM_CDN . '", authorized_receiver.file) as file')
            ->where('authorized_receiver.deleted', '0')
            ->where('authorized_receiver.customer_id',$customer_id)
            ->get()->toArray();
        return $request;
    }

    public static function deleteAuthorizedReceiver($id){
        DB::beginTransaction();
        try {
            $s3 = Helpers::getS3Client();
            $bucket = env('AWS_S3_BUCKET_NAME');
            //$request = DB::table('authorized_receiver')->where(['id' => $id])->first()->file;
            $query1 = DB::table('authorized_receiver')->where(['id'=> $id])->update(['deleted' => 1]);
            /**if($request){
                $result = $s3->deleteObject(array(
                    'Bucket' => $bucket,
                    'Key'    => $request
                ));
            }**/
            DB::commit();
            if($query1 > 0){
                return 1;
            }else {
                return 0;
            }
        } catch (\Exception $e) {
            DB::rollback();
        }
        return 0;
    }

    public static function activateAuthorizedReceiver($id){
        try {
            DB::beginTransaction();
            $query1 = DB::table('authorized_receiver')->where(['id'=> $id])->update(['active' => 'active']);
            DB::commit();
            if($query1 > 0){
                return 1;
            }else {
                return 0;
            }

        } catch (\Exception $e) {
            DB::rollback();
        }
        return 0;
    }

    public static function deactivateAuthorizedReceiver($id){
        try {
            DB::beginTransaction();
            $query1 = DB::table('authorized_receiver')->where(['id'=> $id])->update(['active' => 'inactive']);
            DB::commit();
            if($query1 > 0){
                return 1;
            }else {
                return 0;
            }
        } catch (\Exception $e) {
            DB::rollback();
        }
        return 0;
    }

    public static function getAuthorizedReceiverDetails($payment_id){
        $request = DB::Table('authorized_receiver')
            ->select(DB::raw("car.id, car.lotnumber, car.vin, car.year, car.photo, car.purchasedate, auction.title AS auction_title, car_make.name AS carMakerName , car_model.name AS carModelName"))
            ->leftJoin('car', 'online_payment_cars.car_id','=','car.id')
            ->leftJoin('car_make', 'car_make.id_car_make', '=', 'car.id_car_make')
            ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
            ->leftJoin('auction', 'auction.id', '=', 'car.auction_id')
            ->where('online_payment_cars.online_payment_id', $payment_id)
            ->get()->toArray();
        return $request;
    }

    public static function saveAuthorizedReceiver($data, $customer_id) {
        DB::beginTransaction();
        try {
            $carsArray = array();
            if($data){
                $query = DB::table('authorized_receiver')->insertGetId($data);
            }
            DB::commit();
            $CUSTOMER_SERVICES_MANAGER = Helpers::get_users_by_role(Constants::ROLES['CUSTOMER_SERVICES_MANAGER']);
            $CUSTOMER_SERVICE = Helpers::get_users_by_role(Constants::ROLES['CUSTOMER_SERVICE']);
            $op = Helpers::get_users_by_role(Constants::ROLES['SALES_STORE_CUSTOMS_VAT_CARS_OFFICER_ACCOUTANT']);
            $IT = Helpers::get_users_by_department(Constants::DEPARTMENTS['IT']);
            $users = array_merge( $CUSTOMER_SERVICES_MANAGER, $IT, $op, $CUSTOMER_SERVICE);
            $users = array_column($users, 'id');
            Helpers::send_notification_service(array(
                'sender_id' => 1,
                'recipients_ids' =>$users,
                'subject' => 'New Authorized Receiver from customer',
                'subject_ar' => 'شخص مخول من عميل',
                'body' =>  "New Authorized Receiver added by Customer, ". "Please click on the notification to review it.",
                'body_ar' =>  "يوجد شخص مخول تمت إضافته من العميل ". "يرجى الضغط على الاشعارات للمراجعة",
                'priority' => Constants::NOTIFICATION_PRIORITY_HIGH,
                'url' => "customer/profile/".$customer_id,
                'type' => Constants::NOTIFICATION_ALERT_TYPE,
            ));
            return 1;
        }
        catch (\Exception $e) {
            DB::rollback();
            // something went wrong
        }
        return 0;
    }

    public static function getnonDelivered($customer_id){
        $request = DB::Table('car')
            ->select(DB::raw("car.id, car.vin as name"))
            ->leftJoin('container_car', 'container_car.car_id','=','car.id')
            ->leftJoin('container', 'container.container_id','=','container_car.container_id')
            ->leftJoin('receive_car', 'receive_car.car_id', '=', 'car.id')
            ->where('car.arrived_port', '1')
            ->where('car.arrive_store', '1')
            ->where('car.deleted', '0')
            ->where('car.deliver_customer', '0')
            ->where('receive_car.deliver_status', '0')
            ->where('container.clearance_by_customer', '0')
            ->where('car.customer_id', $customer_id)
            ->groupBy('car.id')
            ->get()->toArray();
        return $request;
    }

    public static function getnonVcc($customer_id){
        $today = date('Y-m-d');
        $lastSixMonthsDate = date('Y-m-d', strtotime('-6 months', strtotime($today)));

        $request = DB::Table('vcc_status')
            ->select(DB::raw("car.id, car.vin as name"))
            ->leftJoin('car', 'car.id','=','vcc_status.car_id')
            ->leftJoin('container_car', 'container_car.car_id','=','car.id')
            ->leftJoin('container', 'container.container_id','=','container_car.container_id')
            ->leftJoin('booking', 'booking.booking_id','=','container_car.booking_id')
            ->leftJoin('booking_bl_container', 'booking_bl_container.booking_bl_container_id','=','container.booking_bl_container_id')
            ->where('vcc_status.receive_vcc_status', '1')
            ->where('vcc_status.receive_vcc_from_customer_status', '0')
            ->where('vcc_status.deliver_vcc_to_customer_status', '0')
            ->where('car.purchasedate','>=', '2021-01-01')
            ->where('booking_bl_container.arrival_date','>=', $lastSixMonthsDate)
            ->where('car.customer_id', $customer_id)
            ->groupBy('vcc_status.car_id')
            ->get()->toArray();
        return $request;
    }

    public static function getAllvinsFromid($cars){
        $cleanedString = str_replace(array('"'), '', $cars);
        $cars = "57692,59073";
        $array = explode(',', $cleanedString);
        $request = DB::table('car')
        ->select(DB::raw('car.vin'))
        ->whereIn('id', $array)
        ->get()
        ->pluck('vin') // This will extract only the 'vin' values from the result
        ->toArray();
        return $request;
    }
//    private function getTowingCasesMapById($id)
//    {
//        $array['table'] = 'towing_case_map';
//        $array['leftjoin'] = array(
//            'towing_cases' => 'towing_cases.id = towing_case_map.case_id',
//            'car' => 'car.id = towing_case_map.car_id',
//            'customer' => 'customer.customer_id = car.customer_id'
//        );
//        $array['select'] =
//            'towing_case_map.id ,
//         towing_case_map.create_date as create_date,
//         towing_case_map.specific_data,
//         towing_case_map.response_date,
//         towing_case_map.response,
//         towing_case_map.read,
//         towing_case_map.read_time,
//         towing_case_map.reject_message,
//         towing_cases.subject,
//         towing_cases.subject_ar,
//         towing_cases.message,
//         towing_cases.message_ar,
//         towing_cases.response_time,
//         towing_cases.response_time_unit,
//         car.lotnumber,
//         car.vin,
//         customer.full_name  ,
//         customer.customer_id  ,
//         customer.full_name_ar';
//        $array['where'] = array('towing_case_map.id' => $id);
//
//        $data = $this->Custom_model->select($array)[0];
//
//        return $data;
//
//
//    }
    public static function getTowingCase($id){
        $query = DB::table('towing_case_map')
        ->selectRaw('towing_case_map.id ,
        towing_case_map.create_date as create_date,
        towing_case_map.specific_data,
        towing_case_map.response_date,
        towing_case_map.response,
        towing_case_map.read,
        towing_case_map.read_time,
        towing_case_map.reject_message,
        towing_cases.subject,
        towing_cases.subject_ar,
        towing_cases.message,
        towing_cases.message_ar,
        towing_cases.response_time,
        towing_cases.response_time_unit,
        car.lotnumber,
        car.vin,
        customer.full_name  ,
        customer.phone  ,
        customer.customer_id  ,
        customer.full_name_ar')
        ->leftJoin('towing_cases', 'towing_cases.id', '=', 'towing_case_map.case_id')
        ->leftJoin('car', 'car.id', '=', 'towing_case_map.car_id')
        ->leftJoin('customer', 'customer.customer_id', '=', 'car.customer_id')
        ->where('towing_case_map.id', $id)
            // get row
        ->get()->first();
        return $query;

    }

    public static  function getCustomerServiceUsers(): array
    {
        $query = DB::table('users')
        ->selectRaw('users.user_id as id, users.full_name,  users.phone')
        ->where('users.role_id', self::CUSTOMER_SERVICE_ROLE)
        ->where('users.status', 1)
        ->get()->toArray();
        return $query;
    }


    public static function getBuyerAcc($customer_id){
        $request = DB::Table('customer_auction')
            ->select(DB::raw("master.master_id as mID, master.master_number as mNumber, buyer.buyer_id as bID, buyer_types.name as type_name, buyer_types.name_ar as type_name_ar, customer_auction.customer_auction_id as id,
            buyer.buyer_number as name, buyer.username, buyer.password, auction.title, auction.logo"))
            ->leftJoin('buyer', 'buyer.buyer_id', '=', 'customer_auction.buyer_id')
            ->leftJoin('master', 'master.master_id', '=', 'buyer.master_id')
            ->leftJoin('buyer_types', 'buyer_types.id', '=', 'master.master_type')
            ->leftJoin('auction', 'auction.id', '=', 'customer_auction.auction_id')
            ->where('customer_auction.customer_id', $customer_id)
            ->where('auction.status', '1')
            ->get()->toArray();
        return $request;
    }

    public static function getSpecialRequest($customer_id){
        $request = DB::Table('loading_special_request')
            ->select(DB::raw("special_request_cars.id, loading_special_request.name_en as name_en, car.photo as car_image, loading_special_request.name_ar as name_ar, loading_special_request.amount_revenue as amount,
            loading_special_request.photo, loading_special_request.description, special_request_cars.confirmed, special_request_cars.create_date as date, car.lotnumber,
            car.vin, car_make.name AS carMakerName , car_model.name AS carModelName"))
            ->leftJoin('special_request_cars', 'special_request_cars.loading_special_request_id', '=', 'loading_special_request.id')
            ->leftJoin('car', 'car.id', '=', 'special_request_cars.car_id')
            ->leftJoin('car_make', 'car_make.id_car_make', '=', 'car.id_car_make')
            ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
            ->where('car.customer_id', $customer_id)
            ->where('special_request_cars.deleted', '0')
            ->groupBy('special_request_cars.car_id')
            ->get()->toArray();
        return $request;
    }

    public static function getAllSpecialRequest(){
        $request = DB::Table('loading_special_request')
            ->select(DB::raw("loading_special_request.id, loading_special_request.name_en as name_en, loading_special_request.name_ar as name_ar, loading_special_request.amount_revenue as amount,
            loading_special_request.photo, loading_special_request.description"))
            ->get()->toArray();
        return $request;
    }

    public static function addLoadingRequest($data){
        DB::beginTransaction();
        try {

            $inputs = [];
            foreach ($data as $key => $value) {
                $inputs[$key] = $value;
            }
            if(count($inputs) > 0){
                $query = DB::table('special_request_cars')->insertGetId($inputs);
            }
            DB::commit();
            $CUSTOMER_SERVICES_MANAGER = Helpers::get_users_by_role(Constants::ROLES['CUSTOMER_SERVICES_MANAGER']);
            $CUSTOMER_SERVICE = Helpers::get_users_by_role(Constants::ROLES['CUSTOMER_SERVICE']);
            $IT = Helpers::get_users_by_department(Constants::DEPARTMENTS['IT']);
            $users = array_merge( $CUSTOMER_SERVICES_MANAGER, $IT, $CUSTOMER_SERVICE);
            $users = array_column($users, 'id');
            Helpers::send_notification_service(array(
                'sender_id' => 1,
                'recipients_ids' => $users,
                'subject' => 'New shipping services',
                'subject_ar' => 'طلب خدمة شحن من عميل',
                'body' =>  "New shipping services added by Customer, ". "Please click on the notification to review it.",
                'body_ar' =>  "يوجد خدمة شحن تمت إضافتها من العميل ". "يرجى الضغط على الاشعارات للمراجعة",
                'priority' => Constants::NOTIFICATION_PRIORITY_HIGH,
                'url' => "Customer/specialRequestCars",
                'type' => Constants::NOTIFICATION_ALERT_TYPE,
            ));
            return 1;
        }
        catch (\Exception $e) {
            DB::rollback();
            // something went wrong
        }
        return 0;
    }

    public static function getServiceData($id){
        $query = DB::table('loading_special_request')
        ->selectRaw('loading_special_request.*')
        ->where('loading_special_request.id', $id)
            // get row
        ->get()->first();
        return $query;
    }

    public static function deleteLoadingRequest($id){
        DB::beginTransaction();
        try {
            $query1 = DB::table('special_request_cars')->where(['id'=> $id])->update(['deleted' => 1]);
            DB::commit();
            if($query1 > 0){
                return 1;
            }else {
                return 0;
            }
        } catch (\Exception $e) {
            DB::rollback();
        }
        return 0;
    }

    public static function getExchangeCompanies(){
        $request = DB::Table('exchange_company')
            ->select(DB::raw("*"))
            ->get()->toArray();
        return $request;
    }

    public function getLotNumbersDamage($customer_id)
    {
        $today = date('Y-m-d');
        $lastMonthsDate = date('Y-m-d', strtotime('-2 months', strtotime($today)));
        $result = DB::table('car')
            ->leftJoin('damage_car_requests', function($join) {
                $join->on('damage_car_requests.car_id', '=', 'car.id')
                    ->where('damage_car_requests.deleted', '=', 0);
            })
            ->leftJoin('shipping_order_car', 'car.id', '=', 'shipping_order_car.car_id')
            ->leftJoin('container_car', 'car.id', '=', 'container_car.car_id')
            ->leftJoin('container', 'container_car.container_id', '=', 'container.container_id')
            ->leftJoin('booking_bl_container', 'container.booking_bl_container_id', '=', 'booking_bl_container.booking_bl_container_id')
            ->select('car.lotnumber', 'car.vin', 'car.id', DB::raw('damage_car_requests.id as req_exist'))
            ->where('car.arrived_port', 1)
            ->where('car.deleted', 0)
            ->where(function($query) use ($customer_id) {
                $query->where('car.customer_id', $customer_id)
                    ->where(function($subQuery) {
                        $subQuery->where('car.destination', 6)
                                ->orWhere('car.destination', 60)
                                ->orWhere('car.destination', 38)
                                ->orWhere('car.destination', 46);
                    });
            })
            ->whereNull('damage_car_requests.id')
            ->where('booking_bl_container.arrival_date','>=', $lastMonthsDate)
            ->orderBy('damage_car_requests.id', 'desc')
            ->groupBy('car.id')
            ->get()
            ->toArray();
        return $result;
    }


    public function getLotNumbersDamageInfo($car_id)
    {
        $result = DB::table('car')
            ->leftJoin('damage_car_requests', function($join) {
                $join->on('damage_car_requests.car_id', '=', 'car.id')
                    ->where('damage_car_requests.deleted', '=', 0);
            })
            ->select('car.lotnumber', 'car.vin', 'car.id', DB::raw('damage_car_requests.id as req_exist'))
            ->where('car.arrived_port', 1)
            ->where('car.deleted', 0)
            ->where('car.id',$car_id)
            ->orderBy('damage_car_requests.id', 'desc')
            ->groupBy('car.id')
            ->get()
            ->toArray();
        return $result;
    }

    public function getCarInfo($car_id)
    {
        $result = DB::table('car')
        ->selectRaw('CONCAT("' . Constants::NEJOUM_CDN . 'uploads/' . '", car.photo) as photo , car.id,
        car.lotnumber, car.vin, car.year, car.purchasedate, car_make.name AS carMakerName , car_model.name AS carModelName')
        ->leftJoin('car_make', 'car_make.id_car_make', '=', 'car.id_car_make')
        ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
        ->where('car.deleted', 0)
        ->where('car.id',$car_id)
        ->groupBy('car.id')
        ->get()
        ->toArray();
        return $result;
    }

    public static function saveDamageRequest($data) {
        $imgController = app('App\Http\Controllers\Car\ImgController');
        $request = new Request();
        $request->merge([
            'car_id'        => $data['car_id'],
            'container_no'  => '',
            'type'          => 'damagewarehouse',
            'system'        => 0
        ]);
        
        $warehouse_images = $imgController->getImages($request);
        $selectedDamagePartIds = json_decode($data['selectedDamagePartIds'], true);

        $response = $imgController->getImages($request);
        if ($response->getStatusCode() == 200) {
            $warehouse_images = json_decode($response->getContent(), true);
            $filteredImagesW = [];
            $warehouse_images = ($warehouse_images) ? $warehouse_images['images'] : [];

            // Decode JSON string into an array
            $w_images = json_decode($data['w_images'], true);
            

            // Check if $s_images is indeed an array
            if (is_array($w_images)) {
                foreach ($warehouse_images as $value) {
                    if (in_array(intval($value['id']), $w_images)) {
                        // If the ID matches, add to the filtered list in the desired format
                        $filteredImagesW[] = [
                            'photo_name' => $value['image'],
                            'type' => 5, // replace $type with the actual type
                            'damaged_car_id' => $data['car_id'],
                            'visible' => 1,
                            'arrived_car_photo_id' => $value['id'],
                            'car_id' => $data['car_id'],
                            'create_by' => 0,
                        ];
                    }
                }
            } else {
                // Handle the case where $data['s_images'] is not a valid array
            }

            // Now $filteredImagesS contains the filtered and formatted data
        } else {
            // Handle error scenario
        }

        $request->merge([
            'car_id'        => $data['car_id'],
            'container_no'  => '',
            'type'          => 'damagestore',
            'system'        => 0
        ]);

        $response = $imgController->getImages($request);

        if ($response->getStatusCode() == 200) {
            $store_images = json_decode($response->getContent(), true);
            $filteredImagesS = [];
            $store_images = ($store_images) ? $store_images['images'] : [];

            // Decode JSON string into an array
            $s_images = json_decode($data['s_images'], true);

            // Check if $s_images is indeed an array
            if (is_array($s_images) && $store_images) {
                foreach ($store_images as $value) {
                    if (in_array(intval($value['id']), $s_images)) {
                        // If the ID matches, add to the filtered list in the desired format
                        $filteredImagesS[] = [
                            'photo_name' => $value['image'],
                            'type' => 6, // replace $type with the actual type
                            'damaged_car_id' => $data['car_id'],
                            'visible' => 1,
                            'arrived_car_photo_id' => $value['id'],
                            'car_id' => $data['car_id'],
                            'create_by' => 0,
                        ];
                    }
                }
            } else {
                // Handle the case where $data['s_images'] is not a valid array
            }

            // Now $filteredImagesS contains the filtered and formatted data
        } else {
            // Handle error scenario
        }

        DB::beginTransaction();
        $query = 0;
        $lot = 0;
        try {
            $dataInserted = array();
            $dataInserted['car_id']         = $data['car_id'];
            $dataInserted['notes']          = $data['notes'];
            $dataInserted['responsible']    = 2;
            $dataInserted['created_by']     = 1;

            if($dataInserted && $dataInserted['car_id'] > 0){
                $query = DB::table('damage_car_requests')->insertGetId($dataInserted);
                $request_number = 'DMG' . str_pad($query, 7, "0", STR_PAD_LEFT);
                $query11 = DB::table('damage_car_requests')->where(['id'=> $query])->update(['request_number' => $request_number]);

                if (is_array($selectedDamagePartIds) && $selectedDamagePartIds) {
                    foreach ($selectedDamagePartIds as $value) {
                        $DamageParts[] = [
                            'request_id' => $query,
                            'damage_parts_id' => $value
                        ];
                    }
                }

                $lot = DB::table('car')
                    ->selectRaw('lotnumber')
                    ->where('car.id', $data['car_id'])
                    ->get()->first()->lotnumber;
            }else{
                return 0;
            }

            if(!empty($filteredImagesS)){
                $query1 = DB::table('damaged_car_photo')->insert($filteredImagesS);
            }
            if(!empty($filteredImagesW)){
                $query2 = DB::table('damaged_car_photo')->insert($filteredImagesW);
            }

            if(!empty($DamageParts)){
                $query3 = DB::table('damage_parts_in_request')->insert($DamageParts);
            }

            DB::commit();
            $CUSTOMER_SERVICES_MANAGER = Helpers::get_users_by_role(Constants::ROLES['CUSTOMER_SERVICES_MANAGER']);
            $CUSTOMER_SERVICE = Helpers::get_users_by_role(Constants::ROLES['CUSTOMER_SERVICE']);
            $IT = Helpers::get_users_by_department(Constants::DEPARTMENTS['IT']);
            $users = array_merge( $CUSTOMER_SERVICES_MANAGER, $IT, $CUSTOMER_SERVICE);
            $users = array_column($users, 'id');
            Helpers::send_notification_service(array(
                'sender_id' => 1,
                'recipients_ids' =>$users,
                'subject' => 'New Damage Request',
                'subject_ar' => 'طلب ضرر من عميل',
                'body' =>  "New Damage request from customer, ". "Please click on the notification to review it.",
                'body_ar' =>  "يوجد طلب ضرر من عميل من التطبيق  ". "يرجى الضغط على الاشعارات للمراجعة",
                'priority' => Constants::NOTIFICATION_PRIORITY_HIGH,
                'url' => "DamageCarRequest/index?search=".urlencode($lot)."&from=&to=&submit=Submit",
                'type' => Constants::NOTIFICATION_ALERT_TYPE,
            ));
            return $query;
        }
        catch (\Exception $e) {
            DB::rollback();
        }
        return 0;
    }

    public static function getQueryDamageRequest($args){
        $customer_id = $args['customer_id'];
        $search      = $args['search'];

        return DB::table('damage_car_requests')
        ->leftJoin('car', 'car.id', '=', 'damage_car_requests.car_id')
        ->leftJoin('car_make', 'car_make.id_car_make', '=', 'car.id_car_make')
        ->leftJoin('accounttransaction', function($join) { $join->on('accounttransaction.car_id', '=', 'damage_car_requests.car_id')
            ->where('accounttransaction.car_step', '=', 1999); })
        ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
        ->where('car.deleted', 0)
        ->where('car.customer_id',$customer_id)
        ->where('damage_car_requests.deleted',0)
        ->when ($search , function ($query) use($search){
            $query->where(function($query)  use($search){
                $query->where('car.vin', $search)
                    ->orWhere('car.lotnumber', $search);
            });
        });
    }


    public static function getAllDamageRequest($args){

        $limit          = !empty($args['limit']) ? $args['limit'] : 500;
        $page           = !empty($args['page']) ? $args['page'] : 0;
        $customer_id    = $args['customer_id'];

        if (empty($customer_id)) {
            return [];
        }

        $select = 'CONCAT("' . Constants::NEJOUM_CDN . 'uploads/' . '", car.photo) as photo, car.id,
        car.lotnumber, car.vin, car.year, car.purchasedate, car_make.name AS carMakerName, car_model.name AS carModelName, damage_car_requests.request_number,
        accounttransaction.Debit as customer_price, damage_car_requests.customer_status, damage_car_requests.notes, damage_car_requests.currency, damage_car_requests.customer_date,
        damage_car_requests.created_date';

        $query = self::getQueryDamageRequest($args);
        $limit != 'all' && $query->skip($page * $limit)->take($limit);
        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }

    public static function getDamageRequestCount($args){
        $query = self::getQueryDamageRequest($args);
        $query->select(DB::raw('COUNT(damage_car_requests.id) as totalRecords'));
        return $query->first()->totalRecords;
    }

    static function getComplaintTypes(){
        $types = DB::Table('complaint_types')
            ->select(DB::raw("*"))
            ->where('complaint_types.deleted', '0')
            ->where('complaint_types.type_for', 'Complaint')
            ->get()->toArray();
        return $types;
    }
    static function getComplaintMessageId($title, $message, $lot_vin, $customer_id){
        $complaintMessageId = DB::table('complaint_message')
        ->select('complaint_message_id')
        ->where('message', $message)
        ->where('lot_vin', $lot_vin)
        ->where('customer_id', $customer_id)
        ->where('title', $title)
        ->orderBy('complaint_message_id', 'DESC')
        ->first();

    return $complaintMessageId;
    }

    static function saveFeedback($data){
        return DB::table('customers_feedback')->insert($data);
    }

    static function getValidFeedbackData($token){
        $create_at = date('Y-m-d H:i:s', strtotime('-2 hours'));
        return DB::table('customers_feedback_token')
        ->leftJoin('customers_feedback', 'customers_feedback.feedback_token_id', 'customers_feedback_token.id')
        ->whereNull('customers_feedback.id')
        ->where(['token' => $token])
        ->where('create_at', '>=', $create_at)
        ->get()->first();
    }
    public function getAllAppTraficServices()
    {
        $result = DB::table('app_traffic_service')
        ->selectRaw('name as service_name, name_ar as service_name_ar,price as service_price,id')
        ->where('status', 'running')
        ->get()
        ->toArray();
        return $result;
    }

    public function getAllAppTraficRegions()
    {
        $gccCountries = [229, 191, 178, 117, 17, 165];

        $regions = DB::table('states')
            ->select('states.id', DB::raw("CONCAT(states.name, ' (', countries.shortname, ')') as name"))
            ->join('countries', 'countries.id', '=', 'states.country_id')
            ->whereIn('country_id', $gccCountries)
            ->get()
            ->toArray();

        return response()->json($regions);
    }
    public function getCustomerServiceRequest($customer_id,$licence_number)
    {

        $query = DB::table('app_traffic_request')
        ->select(
            'app_traffic_request.id as request_id',
            'app_traffic_request.customer_service_status as request_status',
            'app_traffic_request.service_type as service_id',
            'app_traffic_request.phone',
            'app_traffic_request.licence_number',
            'app_traffic_request.customer_service_notes as notes',
            'app_traffic_request.region',
            'app_traffic_request.create_date',
            'app_traffic_request.customer_service_date',
            'app_traffic_service.name as service_name',
            'app_traffic_service.price as service_price',
            'app_traffic_request_payments.status as payment_status'
        )
        ->join('app_traffic_service', 'app_traffic_request.service_type', '=', 'app_traffic_service.id')
        ->join('app_traffic_request_payments', 'app_traffic_request.id', '=', 'app_traffic_request_payments.request_id')
        ->orderBy('app_traffic_request.create_date', 'desc');

        if ($licence_number) {
            $query->where('app_traffic_request.licence_number', $licence_number);
        }else if ($customer_id) {
            $query->where('app_traffic_request.customer_id', $customer_id);
        }

        $result = $query->get();
        return $result;
    }


    public static function getDamageParts(){
        $query = DB::Table('damage_parts')->select('*');
        return $query->get()->toArray();
    }

}
