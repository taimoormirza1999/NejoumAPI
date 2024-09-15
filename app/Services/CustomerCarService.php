<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Libraries\Helpers;
use App\Libraries\Constants;
class CustomerCarService
{
    public static function getArrivedCarsPhoto1($car_id){
        return DB::Table('arrived_car_photo')
                ->select(DB::raw('arrived_car_photo.*'))
                ->join('arrived_car', 'arrived_car_photo.arrived_car_id','=','arrived_car.arrived_car_id')
                ->join('car', 'arrived_car.car_id','=','car.id')
                ->where('car.id',$car_id)
                ->get()->toArray();
    }

    public static function getArrivedCarsPhoto2($car_id)
    {
        return DB::Table('arrived_car_photo')
                ->select(DB::raw('arrived_car_photo.*'))
                ->join('car', 'arrived_car_photo.car_id','=','car.id')
                ->where('car.id',$car_id)
                ->get()->toArray();
    }
    public static function getArrivedStoreCarsPhoto($car_id)
    {
        $query = DB::Table('receive_car_photo')
                ->join('car', 'receive_car_photo.car_id','=','car.id')
                ->where('car.id',$car_id);
        $query->select(DB::raw('receive_car_photo.*'));
        return $query->get()->toArray();
    }
    public static function  carModelCustomer($customer_id, $id, $year) {
        $query = DB::Table('car')
        ->leftJoin('car_model', 'car_model.id_car_model', '=','car.id_car_model')
        ->where([
            ['car.deleted', '0'],
            ['car.customer_id',$customer_id],
            ['car.id_car_make',$id]
        ])
        ->when($year , function ($query) use($year){
            return $query->where('car.year', $year);
        })
        ->groupBy('car.id_car_model');
        $query->select(DB::raw('car.id_car_model as id, car_model.name'));
        return $query->get()->toArray();
    }
    public static function  saveNotes($customer_id, $car_id, $notes) {
        $query = DB::table('customer_appnotes')->updateOrInsert(['car_id'=> $car_id],
            ['customer_id' => $customer_id, 'notes' => $notes, 'created_date' => date('Y-m-d')]
        );
        return $query;
    }

    // customer cancelled function is to get all cancelled cars even after payment is done
    public static function getCustomerCancelledCarsCount($args)
    {
        $query = self::getQueryCustomerCancelledCars($args);
        $query->select(DB::raw('COUNT(DISTINCT(car.id)) as totalRecords'));
        return $query->first()->totalRecords;
    }
    public static function getQueryCustomerCancelledCars($args)
    {
        $customer_id = $args['customer_id'];

        $query = DB::Table('car')
            ->leftJoin('car_make', 'car_make.id_car_make', '=', 'car.id_car_make')
            ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
            ->leftJoin('color', 'car.color','=','color.color_id')
            ->leftJoin('vehicle_type', 'vehicle_type.id_vehicle_type', '=', 'car.id_vehicle_type')
            ->leftJoin('auction', 'auction.id', '=', 'car.auction_id')
            ->leftJoin('auction_location as al', 'al.auction_location_id', '=', 'car.auction_location_id')
            ->leftJoin('region', 'al.region_id', '=', 'region.region_id')
            ->leftJoin('port', 'port.port_id', '=', 'car.destination')
            ->leftJoin('car_note', 'car_note.car_id', '=', 'car.id')
            ->leftJoin('customer', 'customer.customer_id', '=', 'car.customer_id')
            ->leftJoin('customer_group', 'customer_group.id', '=', 'customer.customer_group_id')
            ->leftJoin('auction_location_fines as alf', 'alf.auction_location_id','=','car.auction_location_id')
            ->leftJoin('auction_fines as af', 'af.id', '=' ,'alf.auction_fines_id')
            ->where('car.customer_id', $customer_id)
            ->where('car.cancellation', '1')
            ->where('car.car_payment_to_cashier', '0')
            ->where('car.external_car', '0')
            ->where('car.deleted', '0')
            ->orderBy('car.create_date', 'desc');

        if (!empty($args['lotnumber'])) {
            $query->where('car.lotnumber', $args['lotnumber']);
        }

        if (!empty($args['region'])) {
            $query->where('region.region_id', $args['region']);
        }

        return $query;
    }
    public static function getCustomerCancelledCars($args)
    {

        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;
        $order = !empty($args['order']) ? $args['order'] : '';
        $customer_id = $args['customer_id'];
        if (empty($customer_id)) {
            return [];
        }

        $select = "car.id, car.lotnumber ,car.sales_price , car.vin ,car.year , car.photo, car.purchasedate, car_make.name AS carMakerName , car_model.name AS carModelName, vehicle_type.name AS vehicleName, auction.us_dollar_rate,
        auction.candian_dollar_rate,auction.title AS auction_title, al.auction_location_name, al.country_id, af.day_of_cancellation,
        af.amount_cancellation, af.min_cancellation, af.max_cancellation, customer.full_name AS CustomerName, IF(port.tag is not null,'".Constants::JEBEL_ALI_PORT_NAME."',port.port_name) port_name, region.region_name as region";

        $query = self::getQueryCustomerCancelledCars($args)
            ->groupBy('car.id');
        if($order){
            $order = Helpers::getOrder($order);
            if($order){
                $query->orderBy($order['col'],$order['dir']);
            }
        }
        if($limit != 'all'){
            $query->skip($page * $limit)->take($limit);
        }
        $query->select(DB::raw($select));

        return $query->get()->toArray();
    }

    public static function getCompletedCarsCount($args)
    {
        $query = self::getQueryCompletedCars($args);
        $query->select(DB::raw('COUNT(DISTINCT(car.id)) as totalRecords'));
        return $query->first()->totalRecords;
    }
    public static function getQueryCompletedCars($args)
    {
        $customer_id = $args['customer_id'];

        $query = DB::Table('car')
            ->leftJoin('car_make', 'car_make.id_car_make', '=', 'car.id_car_make')
            ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
            ->leftJoin('color', 'car.color','=','color.color_id')
            ->leftJoin('vehicle_type', 'vehicle_type.id_vehicle_type', '=', 'car.id_vehicle_type')
            ->leftJoin('auction', 'auction.id', '=', 'car.auction_id')
            ->leftJoin('auction_location', 'auction_location.auction_location_id', '=', 'car.auction_location_id')
            ->leftJoin('region', 'auction_location.region_id', '=', 'region.region_id')
            ->leftJoin('port', 'port.port_id', '=', 'car.destination')
            ->leftJoin('receive_car', 'receive_car.car_id', '=', 'car.id')
            ->leftJoin('final_payment_invoices_details', 'final_payment_invoices_details.car_id', '=', 'car.id')
            ->leftJoin('car_total_cost', 'car_total_cost.car_id', '=', 'car.id')
            ->leftJoin('container_car', 'container_car.car_id', '=', 'car.id')
            ->leftJoin('container', 'container.container_id', '=', 'container_car.container_id')
            ->leftJoin('transport_request', 'transport_request.container_id', '=', 'container.container_id')
            ->leftJoin('customer', 'customer.customer_id', '=', 'car.customer_id')
            ->where('car.customer_id', $customer_id)
            ->whereNotNull('car_total_cost.car_id')
            ->where('car.deleted', '0')
            ->orderBy('container.container_number', 'desc')
            ->orderBy('car_total_cost.create_date', 'desc');

        if (!empty($args['lotnumber'])) {
            $query->where('car.lotnumber', $args['lotnumber']);
        }

        if (!empty($args['region'])) {
            $query->where('region.region_id', $args['region']);
        }

        return $query;
    }
    public static function getCompletedCars($args)
    {

        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;
        $customer_id = $args['customer_id'];
        if (empty($customer_id)) {
            return [];
        }

        $select = 'car.* , car_make.name AS carMakerName ,container.clearance_by_customer, car_model.name AS carModelName,
        vehicle_type.name AS vehicleName,color.color_code as color,color.color_name,auction.title AS auction_title,container.container_number,
        final_payment_invoices_details.storage_fine,car_total_cost.create_date as completed_date,
        auction_location.auction_location_name,port.port_name, customer.full_name AS CustomerName,customer.full_name_ar, customer.account_id customer_account_id,
        CAST(receive_car.create_date AS DATE) AS  receive_date';

        $query = self::getQueryCompletedCars($args)
            ->groupBy('car.id')
            ->skip($page * $limit)->take($limit);

        $query->select(DB::raw($select));

        return $query->get()->toArray();
    }
    public static  function search($vin) {
        return DB::Table('car')
        ->select(DB::raw('car.*,car_make.name AS carMakerName , car_model.name AS carModelName'))
        ->leftJoin('car_make', 'car.id_car_make','=','car_make.id_car_make')
        ->leftJoin('car_model', 'car.id_car_model','=','car_model.id_car_model')
        ->where('vin','like', '%'.$vin.'%')
        ->orWhere('lotnumber', $vin)
        ->orderBy("car.id", "desc")
        ->first();
    }
    public static function search1($car_id) {
        return DB::Table('shipping_order')
        ->select(DB::raw('shipping_order.*'))
        ->join('shipping_order_car', 'shipping_order.shipping_order_id','=','shipping_order_car.shipping_order_id')
        ->where('shipping_order_car.car_id','=', $car_id)
        ->first();
    }
    public static  function getBill($car_id) {
        return DB::Table('car')
        ->select(DB::raw('car.* , bill_details.bill_id AS bill_id , bill_details.create_date'))
        ->join('bill_details', 'car.id','=','bill_details.car_id')
        ->join('bill', 'bill_details.bill_id','=','bill.ID')
        ->where('car.deleted','=', '0')
        ->where('car.id','=', $car_id)
        ->first();
    }
    public static function getAuction($car_id) {
        return DB::Table('car')
        ->select(DB::raw('car.* , bill_details.bill_id AS bill_id , IF(STRCMP(car.car_payment_to_cashier,"3") = 0, bill_details.create_date , car.received_by_auction_date) AS create_date'))
        ->join('bill_details', 'car.id','=','bill_details.car_id')
        ->where('car.deleted','=', '0')
        ->where('car.id','=', $car_id)
        ->first();
    }

    public static function postedstatus($car_id) {

        return DB::Table('car')
        ->select(DB::raw('car.* , posted_cars.create_date AS posted_date'))
        ->join('posted_cars', 'car.id','=','posted_cars.car_id')
        ->where('car.deleted','=', '0')
        ->where('car.id', $car_id)
        ->first();
    }
    public static  function towingstatus($car_id) {

        return DB::Table('car')
        ->select(DB::raw('car.* , towing_status.picked_date AS Picked_date'))
        ->join('towing_status', 'car.id','=','towing_status.car_id')
        ->where('car.deleted','=','0')
        ->where('car.id','=',$car_id)
        ->first();
    }

    public static  function arrivedstatus($car_id) {
        return DB::Table('car')
        ->select(DB::raw('car.* , DATE(arrived_car.delivered_date) AS 	delivered_date'))
        ->join('arrived_car', 'car.id','=','arrived_car.car_id')
        ->where('car.deleted','=', '0')
        ->where('car.id','=', $car_id)
        ->first();
    }

    public static  function loading_status($car_id) {
        return DB::Table('car')
        ->select(DB::raw('car.* , shipping_order_car.shipping_order_id AS 	shipping_order_id,booking.booking_id as booking_id,loaded_status.loaded_date as loaded_date'))
        ->join('shipping_order_car', 'car.id','=','shipping_order_car.car_id')
        ->join('booking', 'shipping_order_car.shipping_order_id','=','booking.order_id')
        ->join('loaded_status', 'loaded_status.booking_id','=','booking.booking_id')
        ->where('car.deleted','=', '0')
        ->where('car.id','=', $car_id)
        ->first();
    }

    public static function shipping_status($car_id) {
        return DB::Table('car')
        ->select(DB::raw('car.* , shipping_order_car.shipping_order_id AS 	shipping_order_id,booking.booking_id as booking_id,shipping_status.shipping_date as shipping_date'))
        ->join('shipping_order_car', 'car.id','=','shipping_order_car.car_id')
        ->join('booking', 'shipping_order_car.shipping_order_id','=','booking.order_id')
        ->join('shipping_status', 'booking.booking_id','=','shipping_status.booking_id')
        ->where('car.deleted', '0')
        ->where('car.id', $car_id)
        ->first();
    }

    public static function arrived_port($car_id) {
        return DB::Table('car')
        ->select(DB::raw('car.* , booking.booking_id as booking_id ,booking.booking_id as booking_id,booking_bl_container.arrival_date as arrival_date,booking_bl_container.booking_bl_container_id'))
        ->join('shipping_order_car', 'car.id','=','shipping_order_car.car_id')
        ->join('container_car', 'car.id','=','container_car.car_id')
        ->join('container', 'container_car.container_id','=','container.container_id')
        ->join('booking_bl_container', 'container.booking_bl_container_id','=','booking_bl_container.booking_bl_container_id')
        ->join('booking', 'shipping_order_car.shipping_order_id','=','booking.order_id')
        ->where('car.deleted','=', '0')
        ->where('car.id','=', $car_id)
        ->first();
    }

    public static function pick_status($car_id) {
        return DB::Table('car')
        ->select(DB::raw('car.* , container_car.container_id AS 	container_id,transport_request.pick_create_date  AS pick_create_date'))
        ->join('container_car', 'car.id','=','container_car.car_id')
        ->join('transport_request', 'container_car.container_id','=','transport_request.container_id')
        ->where('car.deleted','=', '0')
        ->where('car.id','=', $car_id)
        ->first();
    }

    public static function arrive_store($car_id) {
        return DB::Table('car')
        ->select(DB::raw('car.* , 	CAST(receive_car.create_date AS DATE) as create_date,warehouse_request.warehouse_name  as warehouse_request,warehouse_transport2.warehouse_name as warehouse_transport'))
        ->join('receive_car', 'car.id','=','receive_car.car_id')
        ->leftJoin('transport_request', 'transport_request.container_id','=','receive_car.container_id')
        ->leftJoin('warehouse_transport', 'warehouse_transport.car_id','=','receive_car.car_id')
        ->leftJoin('warehouse as warehouse_request', 'transport_request.warehouse_id','=','warehouse_request.warehouse_id')
        ->leftJoin('warehouse as warehouse_transport2', 'warehouse_transport.to_destination_warehouse_id','=','warehouse_transport2.warehouse_id')
        ->where('car.deleted','=', '0')
        ->where('car.id','=', $car_id)
        ->first();
    }

    public static function deliver_customer($car_id) {
        return DB::Table('car')
        ->select(DB::raw('car.* , 	CAST(receive_car.deliver_create_date AS  DATE) deliver_create_date'))
        ->join('receive_car', 'car.id','=','receive_car.car_id')
        ->where('car.deleted','=', '0')
        ->where('car.id','=', $car_id)
        ->first();
    }
    public static function getCustomerLot($id) {
        return DB::Table('car')
        ->select(DB::raw('car.lotnumber'))
        ->where('car.deleted','=', '0')
        ->where('car.customer_id','=',$id)
        ->get()->toArray();
    }
    public static function getCustomerVin($id) {
        return DB::Table('car')
        ->select(DB::raw('car.vin'))
        ->where('car.deleted','=', '0')
        ->where('car.customer_id','=',$id)
        ->get()->toArray();
    }
    public static function getSpecialPortCustomerCars($id) {
        return DB::Table('car')
        ->select(DB::raw('car.id'))
        ->where('car.deleted','=', '0')
        ->where('car.cancellation','=', '0')
        ->where('car.destination','=', Constants::UMMQASR_PORT)
        ->where('car.customer_id','=',$id)
        ->where('car.loading_status','=','0')
        ->get()->toArray();
    }
    public static function getCustomerBalance($customerId){
        $customer_idAccountID = Helpers::get_customer_account_id($customerId);
        $totalDebit = $totalCredit = 0;
        $closed_date = Helpers::get_closed_date();

        $from = '2020-01-01';
        $to = date( "Y-m-d");
        $args = [
            'customer_id' => $customerId,
            'customer_account_id' => $customer_idAccountID,
            'arrived_status' => '1',
            'paid_status' => '',
            'date_from' => $from,
            'date_to' => $to,
            'search' => '',
            'closed_date' => $closed_date,
        ];
        $carTransactions = CarService::make_datatablesView($args)->toArray();
        $transactionAfterCompleted = CarService::transactionAfterCompleted($args);
        $getAllTransation = CarService::getAllTransation2($args);

        if($from <= $closed_date){
            $args_closing = $args;
            $args_closing['closingTable'] = 1;
            $carTransactions_closing = CarService::make_datatablesView($args_closing)->toArray();
            $transactionAfterCompleted_closing = CarService::transactionAfterCompleted($args_closing);
            $getAllTransation_closing = CarService::getAllTransation2($args_closing);

            $carTransactions = array_merge($carTransactions, $carTransactions_closing);
            $transactionAfterCompleted = array_merge($transactionAfterCompleted, $transactionAfterCompleted_closing);
            $getAllTransation = array_merge($getAllTransation, $getAllTransation_closing);
        }
        $carTransactions = array_merge($carTransactions, $transactionAfterCompleted);

        if($from <= $closed_date){
            // if car has transaction in both closing & active tables
            // it will come 2 times: merge the values
            $unset_array_keys = [];
            foreach($carTransactions as $key => $row){
                foreach($carTransactions as $subKey => $subRow){
                    if($row->id == $subRow->id && $subKey > $key){
                        $row->Debit += $subRow->Debit;
                        $row->Credit += $subRow->Credit;
                        $unset_array_keys [] = $subKey;
                    }
                }
                $carTransactions[$key] = $row;
            }
            foreach($unset_array_keys as $key){
                unset($carTransactions[$key]);
            }
        }
        $showen_cars_id = array_column($carTransactions, 'id');
        $storagePrevious = CarService::getTotalPreviousStorage(['customer_id' => $customerId, 'date_from' => $from, 'date_to' => $to, 'showen_cars_id' => $showen_cars_id]);
        $fetch_data_storage_remaining = CarService::carsInfoStorageFineRemaining($customerId, $from, $to, []); // all fines sum together instead of like car statement, because there we have to show car fine in each row also
        $totalStorageOnlyCars = array_column($fetch_data_storage_remaining, 'fine_value','id');//array_sum(array_column($fetch_data_storage_remaining, 'fine_value'));

        $totalDebit += $storagePrevious;// + $totalStorageOnlyCars;
        foreach($carTransactions as $key=>$row){
            $row->fine_value = 0;
            if ($row->deliver_status == 1 && $row->final_payment_status == 1) {}
            elseif(!empty($totalStorageOnlyCars[$row->id])){
                $row->fine_value = $totalStorageOnlyCars[$row->id];
            }
            $row->Discount = CarService::getDiscountFromTransaction($row->id,$customer_idAccountID,$from,'');
            if($from <= $closed_date){
                $row->Discount += CarService::getDiscountFromTransaction($row->id,$customer_idAccountID,$from,'', ['closingTable' => 1]);
            }
            $row->Debit += $row->fine_value;
            $row->Debit -= $row->Discount;
            $totalDebit += $row->Debit;
        }


        foreach($getAllTransation as $key=>$row){
            $totalDebit += $row['Debit'];
            $totalCredit += $row['Credit'];
        }
        return number_format($totalDebit - $totalCredit, 2, '.', '');

    }

    public static function saveReceivableInfo($car_id, $name) {
        $car = DB::table('car')->where('id','=',$car_id)
        ->where('car.arrivedstatus', '=', '1')->first();
        if(!$car){
            $query = DB::table('special_port_receivable_info')->updateOrInsert(['car_id' => $car_id],
                ['customer_name' => $name]
            );
        }else {
            $query = 0;
        }
        return $query;
    }
    
    
    public static function destinationChangeCars($id) {
        $notPosted = DB::Table('car')
        ->leftJoin('port', 'car.destination','=','port.port_id')
        ->select(DB::raw("car.*, 'customer' tag"))
        ->where('car.deleted','=', '0')
        ->where('car.cancellation','=', '0')
        ->where('car.customer_id','=',$id)
        ->where('car.post_status','=','0')
        ->where('car.status','!=','4')
        ->whereNull('port.tag')
        ->get()->toArray();
        $notLoaded = DB::Table("car")
        ->leftJoin('port', 'car.destination','=','port.port_id')
        ->select(DB::raw("car.*, 'customer_service' tag"))
        ->where('car.deleted','=', '0')
        ->where('car.cancellation','=', '0')
        ->where('car.customer_id','=',$id)
        ->where('car.post_status','=','1')
        ->where('car.status','!=','4')
        ->where('car.arrivedstatus','=','0')
        ->whereNull('port.tag')
        ->get()->toArray();
        return array_merge($notPosted,$notLoaded);
    }
    public static function saveCustomerDestination($car_id, $customer_id, $destination, $sellService) {
        $query = DB::table('car')->where(['id'=> $car_id,'customer_id'=> $customer_id])->update(['destination' => $destination]);
        //add shipping cost
        $sellService->addShippingCost($car_id);
        return $query;
    }
    public static function changeReceiverName($car_id,$receiver_name)
    {

            $query = DB::table('special_port_receivable_info')->updateOrInsert(['car_id' => $car_id],
                ['customer_name' => $receiver_name]
            );
            return $query;
    }
    public static function saveDestinationRequest($car_id, $destination, $notes, $customer_id,$receiver_name) {
        $car = DB::table('car')->where('id','=',$car_id)->first();
        if($receiver_name){
            $query = DB::table('special_port_receivable_info')->updateOrInsert(['car_id'=> $car_id],
                ['customer_name' => $receiver_name]
            );
        }
        if($car){
            $data = [
                'car_id'=>$car->id,
                'old_port_id'=>$car->destination,
                'new_port_id'=>$destination,
                'notes'=>$notes,
                'create_by'=>$customer_id,
            ];
            $request = DB::Table('change_destination_requests')
            ->select(DB::raw("change_destination_requests.*"))
            ->where('deleted','=', '0')
            ->where('status','=', '0')
            ->where('car_id','=',$car_id)
            ->get()->toArray();
            if(!$request){
                $query = DB::table('change_destination_requests')->insertGetId($data);
                $customer_service = Helpers::get_users_by_department(Constants::DEPARTMENTS['CUSTOMER_SERVICE']);
                $IT = Helpers::get_users_by_department(Constants::DEPARTMENTS['IT']);
                $users = array_merge( $customer_service , $IT);
                $users = array_column($users, 'id');
                Helpers::send_notification_service(array(
                    'sender_id' => 1,
                    'recipients_ids' =>$users,
                    'subject' => 'Destination Change Request',
                    'subject_ar' => 'Destination Change Request',
                    'body' =>  "Destination Change Request for car by customer ". "Please click on the notification to review it.",
                    'body_ar' =>  "Destination Change Request for car by customer ". "Please click on the notification to review it.",
                    'priority' => Constants::NOTIFICATION_PRIORITY_HIGH,
                    'url' => "Customer/changeDestinationRequests",
                    'type' => Constants::NOTIFICATION_ALERT_TYPE,
                ));
                return $query;
            }
        }
        return 0;
    }
    public static function deleteDestinationRequest($id){
        $query = DB::table('change_destination_requests')->where(['id'=> $id])->update(['deleted' => '1']);
        return $query;
    }

    public static function isDischargePort($port_id){
        $res = DB::table('port')->where(['port_id'=> $port_id, 'tag' => 'discharge'])->first();
        if ($res) {
            return true;
        }
        return false;
    }

    public static function getAllDestinationRequest($customer_id){
        $request = DB::Table('change_destination_requests')
            ->select(DB::raw("change_destination_requests.id,CAST(change_destination_requests.create_date as DATE) as date, old.port_name as old_port_name, old.port_name_ar as old_port_name_ar, change_destination_requests.status,
            new.port_name as new_port_name, new.port_name_ar as new_port_name_ar, car.lotnumber as lot, car.vin"))
            ->leftJoin('car', 'car.id','=','change_destination_requests.car_id')
            ->leftJoin('port as old', 'old.port_id','=','change_destination_requests.old_port_id')
            ->leftJoin('port as new', 'new.port_id','=','change_destination_requests.new_port_id')
            ->where('change_destination_requests.deleted', '0')
            ->where('car.customer_id',$customer_id)
            ->get()->toArray();
        return $request;
    }

    public static function getQueryCustomerContainers($args) {
        $customer_id = $args['customer_id'];
        $search      = $args['search'];
        $region = $args['region'];
        $date_from = $args['date_from'];
        $date_to = $args['date_to'];
        $date_type = $args['date_type'];

        $query = DB::Table('container')
            ->join('container_car', 'container_car.container_id','=','container.container_id')
            ->join('car', 'car.id','=','container_car.car_id')
            ->join('customer', 'customer.customer_id','=','car.customer_id')
            ->leftJoin('booking_bl_container', 'booking_bl_container.booking_bl_container_id','=','container.booking_bl_container_id')
            ->leftJoin('booking', 'booking.booking_id','=','container.booking_id')
            ->leftJoin('shipping_status', 'shipping_status.booking_id','=','container.booking_id')
            ->leftJoin('shipping_order', 'shipping_order.shipping_order_id','=','booking.order_id')
            ->leftJoin('port as port_departure', 'port_departure.port_id','=','shipping_order.take_off_port_id')
            ->leftJoin('port as port_destination', 'port_destination.port_id','=','shipping_order.destination_port')
            ->leftJoin('transport_request', 'transport_request.container_id','=','container.container_id')
            ->leftJoin('loaded_status', 'loaded_status.booking_id','=','container.booking_id')
            ->leftJoin('car_make', 'car_make.id_car_make', '=', 'car.id_car_make')
            ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
            ->leftJoin('car_total_cost', 'car_total_cost.car_id', '=', 'car.id')
            ->where('car.customer_id',$customer_id)
            ->when ($search , function ($query) use($search){
                $query->where(function($query)  use($search){
                    $query->where('car.vin', 'like','%' . $search . '%')
                        ->orWhere('car.lotnumber', 'like','%' . $search . '%')
                        ->orWhere('car_make.name', 'like','%' . $search . '%')
                        ->orWhere('car_model.name', 'like','%' . $search . '%')
                        ->orWhere('container.container_number', 'like','%' . $search . '%')
                        ->orWhere('booking.booking_number', 'like','%' . $search . '%');
                });
            })->when ($region , function ($query) use($region){
                    $query->where('port_departure.region_id', $region);
            });

        if($args['status'] == 'arrivedPort'){
            $query->where('car.arrived_port', '1');
            $query->where('car.deliver_customer', '0');
            $query->where('container.clearance_by_customer', '0');
            $query->whereNull("car_total_cost.car_id");
        }
        else if($args['status'] == 'delivered' && $args['type'] == 'unPaid'){
            $query->where('car.deliver_customer', '1');
            $query->where('car.final_payment_status', '0');
        }
        else if($args['status'] == 'delivered'  && $args['type'] == 'paid'){
            $query->where('car.deliver_customer', '1');
            $query->where('car.final_payment_status', '1');
        }
        else if($args['status'] == 'deliveredAll'){
            $query->where('car.deliver_customer', '1');
        }
        else if($args['status'] == 'arrivedStore'){
            $query->where('car.deliver_customer', '0');
            $query->where('transport_request.status', '1');
            $query->where('transport_request.received_status', '1');
        }
        else if($args['status'] == 'inShipping'){
            $query->where('car.arrived_port', '0');
            $query->where(function($query){
                $query->where('loaded_status.loaded_status', '1')
                    ->orWhere('container.loaded_status', '1');
            });
            $query->whereNull("car_total_cost.car_id");
        }
        if($date_type && ($date_from || $date_to)){
            if($date_type == 'loaded_date'){
                if($date_from){
                    $query->where(DB::raw('IF(loaded_status.booking_id, loaded_status.loaded_date, DATE(container.loaded_date))'),'>=', $date_from );
                }if($date_to){
                    $query->where(DB::raw('IF(loaded_status.booking_id, loaded_status.loaded_date, DATE(container.loaded_date))'),'<=', $date_to );
                }
            }elseif($date_type == 'arrived_port_date'){
                if($date_from){
                    $query->where('booking_bl_container.arrival_date','>=', $date_from );
                }
                if($date_to){
                    $query->where('booking_bl_container.arrival_date','<=', $date_to );
                }
            }
        }
        if(!empty($args['export'])){
            $query->leftJoin('external_car', 'car.id','=','external_car.car_id')
            ->leftJoin('auction', 'car.auction_id','=','auction.id')
            ->leftJoin('auction_location', 'car.auction_location_id','=','auction_location.auction_location_id')
            ->leftJoin('auction_location as external_auction_location', 'external_car.auction_location_id','=','external_auction_location.auction_location_id')
            ->leftJoin('auction as external_auction', 'external_auction_location.auction_id','=','external_auction.id')
            ->leftJoin('towing_status', 'towing_status.car_id','=','car.id')
            ->leftJoin('arrived_car', 'arrived_car.car_id','=','car.id')
            ->leftJoin('bill_details', 'bill_details.car_id','=','car.id')
            ->leftJoin('cars_title_status', 'cars_title_status.cars_title_status_id', '=', DB::raw('(select max(cars_title_status_id) as cars_title_status_id from cars_title_status as cts4 WHERE `cts4`.`car_id` = car.id)'));
        }
        return $query;
    }

    public static function getCustomerContainers($args){

        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;
        $order = !empty($args['order']) ? $args['order'] : '';
        $customer_id = $args['customer_id'];
        if (empty($customer_id)) {
            return [];
        }

        $select = "DISTINCT(container.container_number), container.container_id, count(container_car.car_id) as total_cars, shipping_status.shipping_date, booking.etd, booking.eta,booking.booking_number,
        car.arrived_port, DATE(container.loaded_date) loaded_date, port_destination.port_name destination, container.loaded_status, shipping_status.shipping_status, transport_request.received_status,
        booking_bl_container.arrival_date arrived_port_date, DATE(transport_request.received_create_date) arrived_store_date, IF(loaded_status.booking_id, loaded_status.loaded_date, DATE(container.loaded_date)) loaded_date,
        SUM(car_total_cost.total_price) as total_shipping, port_departure.port_name pol_name, IF(COUNT(car_total_cost.car_id) = COUNT(container_car.car_id), '1', 0) as all_cars_completed,
        CONCAT('N', LPAD(container.container_id, 6, 0)) as invoice_no, container_car.car_id, shipping_order.shipping_company_id";

        $query = self::getQueryCustomerContainers($args)
            ->groupBy('container.container_id');
        if($order){
            $order = Helpers::getOrder($order);
            if($order){
                $query->orderBy($order['col'],$order['dir']);
            }
        }else{
            $query->orderBy('loaded_status.loaded_date', 'desc');
        }
        if($limit != 'all'){
            $query->skip($page * $limit)->take($limit);
        }

        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }

    public static function getCustomerContainersCount($args)
    {
        $query = self::getQueryCustomerContainers($args);
        $query->select(DB::raw('COUNT(DISTINCT(container.container_id)) as totalRecords'));
        return $query->first()->totalRecords;
    }

    public static function totalContainersCount($args, $status, $type = '')
    {
        $args['limit']  = -1;
        $args['status'] = $status;
        $args['type'] = $type;
        $query = self::getQueryCustomerContainers($args);
        $query->select(DB::raw('COUNT(DISTINCT(container.container_id)) as totalRecords'));
        return $query->first()->totalRecords;
    }

    public static function getContainerCars($args){
        $container_id = $args['container_id'];
        $customer_id = $args['customer_id'];

        if(empty($container_id)){
            return [];
        }

        $query = DB::Table('container')
        ->select(DB::raw("car.*"))
        ->join('container_car', 'container_car.container_id','=','container.container_id')
        ->join('car', 'car.id','=','container_car.car_id')
        ->where('container.container_id', $container_id)
        ->where('car.customer_id', $customer_id);
        return $query->get()->toArray();
    }
    public static function getContainerDetail($args)
    {
        $container_id = $args['container_id'];
        $customer_id = $args['customer_id'];
        $NEJOUM_IMAGE = Constants::NEJOUM_CDN;

        if(empty($container_id)){
            return [];
        }

        $query = DB::Table('container')
        ->select(DB::raw("container.lock_number, container.size,
        IF(container.bl_attach_id > 0, CONCAT('$NEJOUM_IMAGE', 'upload/customer_file/', customer_file.file_name), '') as bl_file,
        GROUP_CONCAT(DISTINCT(car.vin)) as cars_list"))
        ->join('container_car', 'container_car.container_id','=','container.container_id')
        ->join('car', 'car.id','=','container_car.car_id')
        ->leftJoin('booking_bl_container', 'booking_bl_container.booking_id','=','container.booking_id')
        ->leftJoin('customer_file', 'customer_file.customer_file_id','=','container.bl_attach_id')
        ->where('container.container_id', $container_id)
        ->where('car.customer_id', $customer_id);
        return $query->first();
    }

    public static function getContainerInvoiceDetail($args)
    {
        $container_id = $args['container_id'];
        $customer_id = $args['customer_id'];
        $today = date('Y-m-d');

        if(empty($container_id)){
            return [];
        }

        $query = DB::Table('container')
        ->select(DB::raw("customer.full_name full_name_en, customer.full_name_ar, customer.phone, shipping_companies.name carrier_name, pol.port_name pol_name, pod.port_name pod_name, booking.booking_number, customer.full_name customer_name_en, customer.phone customer_phone,
        shipping_status.shipping_date invoice_create_date, booking_bl_container.arrival_date port_arrival_date, IF(customer_contract.credit_limit > 0, DATE_ADD(booking_bl_container.arrival_date, INTERVAL customer_contract.credit_limit DAY), booking_bl_container.arrival_date) due_date,
        DATEDIFF(CURDATE(),STR_TO_DATE(IF(customer_contract.credit_limit > 0, DATE_ADD(booking_bl_container.arrival_date, INTERVAL customer_contract.credit_limit DAY), booking_bl_container.arrival_date), '%Y-%m-%d')) AS past_due_days,
        booking.etd, container.container_number, CONCAT('N', LPAD(container.container_id, 6, 0)) as invoice_no, CURDATE() as printed_date, 'Nejoum Al Jazeera' as shipper_name, 'Nejoum Al Jazeera' as consignee_name,
        customer_country.name as customer_country, customer_state.name as customer_state, customer_city.name customer_city, container.size container_size"))
        ->join('container_car', 'container_car.container_id','=','container.container_id')
        ->join('shipping_status', 'shipping_status.booking_id','=','container_car.booking_id')
        ->join('car', 'car.id','=','container_car.car_id')
        ->join('customer', 'customer.customer_id','=','car.customer_id')
        ->join('countries as customer_country', 'customer.country_id','=','customer_country.id')
        ->join('states as customer_state', 'customer.state_id','=','customer_state.id')
        ->join('cities as customer_city', 'customer.city_id','=','customer_city.id')
        ->join('booking', 'booking.booking_id','=','container.booking_id')
        ->join('shipping_order', 'shipping_order.shipping_order_id','=','booking.order_id')
        ->join('shipping_companies', 'shipping_companies.company_id','=','shipping_order.shipping_company_id')
        ->join('port as pol', 'pol.port_id','=','shipping_order.take_off_port_id')
        ->join('port as pod', 'pod.port_id','=','shipping_order.destination_port')
        ->leftJoin('booking_bl_container', 'booking_bl_container.booking_id','=','container.booking_id')
        ->leftJoin('customer_contract', function($join) use ($today) {
            $join->on('customer_contract.customer_id', '=', 'customer.customer_id');
            $join->on('customer_contract.status', '=', DB::raw("'1'"));
            $join->on('customer_contract.start_date', '<=', DB::raw("'$today'"))
            ->where(function ($q) use ($today){
                $q->whereNull('customer_contract.end_date')->orWhere('customer_contract.end_date', '>=', $today);
              });
        })
        ->where('container.container_id', $container_id)
        ->where('car.customer_id', $customer_id);
        return $query->first();
    }

    public static function getContainerInvoiceCars($args)
    {
        $container_id = $args['container_id'];
        $customer_id = $args['customer_id'];
        $usd_rate = Helpers::get_usd_rate();

        if(empty($container_id)){
            return [];
        }

        $query = DB::Table('car')
        ->select(DB::raw("car.id car_id, car.lotnumber, car.vin, car.year, color.color_code, color.color_name, car_make.name AS carMakerName, car_model.name AS carModelName, vehicle_type.name AS vehicleName,
        (car.carcost * $usd_rate) car_cost, auction.title AS auction_title, IF(external_car.auction_location_id > 0, auction_location_external.auction_location_name, auction_location.auction_location_name) auction_location_name,
        IF(states_external.id, states_external.state_code, states.state_code) auction_location_state"))
        ->join('container_car', 'container_car.car_id','=','car.id')
        ->join('customer', 'customer.customer_id','=','car.customer_id')
        ->join('car_total_cost', 'car_total_cost.car_id','=','car.id')
        ->leftJoin('external_car', 'external_car.car_id', '=', 'car.id')
        ->leftJoin('auction', 'auction.id', '=', 'car.auction_id')
        ->leftJoin('auction_location', 'auction_location.auction_location_id', '=', 'car.auction_location_id')
        ->leftJoin('auction_location as auction_location_external', 'auction_location_external.auction_location_id', '=', 'external_car.auction_location_id')
        ->leftJoin('states', 'states.id', '=', 'auction_location.state_id')
        ->leftJoin('states as states_external', 'states_external.id', '=', 'auction_location_external.state_id')
        ->leftJoin('car_make', 'car_make.id_car_make', '=', 'car.id_car_make')
        ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
        ->leftJoin('vehicle_type', 'vehicle_type.id_vehicle_type', '=', 'car.id_vehicle_type')
        ->leftJoin('color', 'car.color','=','color.color_id')
        ->where('container_car.container_id', $container_id)
        ->where('car.customer_id', $customer_id);
        return $query->get()->toArray();
    }



    public static function saveDeliveredToCustomer($args){
        $car_id = $args['car_id'];
        $note = $args['message'];
        $deliver_date  = date('Y-m-d H:i:s');

        DB::Table('car')
        ->where('id', $car_id)
        ->update(['deliver_customer' =>  '1']);

        $receive_car = DB::Table('receive_car')
        ->where('car_id', $car_id)
        ->first();

        if(empty($receive_car)){
            $data = [
                'car_id' => $car_id,
                'deliver_status' => '1',
                'note' => $note,
                'deliver_create_date' => $deliver_date,
                'create_by' => 98,
            ];
            $affected_rows = DB::Table('receive_car')->insert($data);
        }
        else{
            $affected_rows = DB::Table('receive_car')
            ->where('car_id', $car_id)
            ->update(['deliver_status' => '1', 'note' => $note,
            'deliver_create_date' => $deliver_date, 'create_by' => 98]);
        }

        return $affected_rows;
    }

    public static function saveArrivedToStore($args){
        $car_id = $args['car_id'];
        $note = $args['message'];
        $arrived_date  = date('Y-m-d H:i:s');

        DB::Table('car')
        ->where('id', $car_id)
        ->update(['arrive_store' =>  '1']);

        $receive_car = DB::Table('receive_car')
        ->where('car_id', $car_id)
        ->first();

        if(empty($receive_car)){
            $data = [
                'car_id' => $car_id,
                'status' => '1',
                'note' => $note,
                'create_date' => $arrived_date,
                'create_by' => 98,
            ];
            $affected_rows = DB::Table('receive_car')->insert($data);
        }
        else{
            $affected_rows = DB::Table('receive_car')
            ->where('car_id', $car_id)
            ->update(['status' => '1', 'note' => $note,
            'create_date' => $arrived_date, 'create_by' => 98]);
        }

        return $affected_rows;
    }

    public static function getAllOnlinePayment($customer_id){
        $request = DB::Table('online_payment')
            ->select(
                DB::raw("*, exchange_company.name_en, exchange_company.name_ar, online_payment.status as request_status, online_payment.note as request_notes, online_payment.id as payment_id, online_payment.create_date as payment_date, COUNT(online_payment_cars.id) AS cars_count"),
                DB::raw("(SELECT IF(online_payment_cars.status = 2, 2, 1) FROM online_payment_cars WHERE online_payment_cars.online_payment_id = online_payment.id LIMIT 1) AS role_label"),
                DB::raw("(SELECT IF(online_payment_cars.status = 2, online_payment_cars.note, 0) FROM online_payment_cars WHERE online_payment_cars.online_payment_id = online_payment.id LIMIT 1) AS role_label_notes"))
            ->leftJoin('online_payment_cars', 'online_payment_cars.online_payment_id','=','online_payment.id')
            ->leftJoin('account_home', 'account_home.ID','=','online_payment.bank_to')
            ->leftJoin('exchange_company', 'exchange_company.id','=','online_payment.exchange_company_id')
            ->where('online_payment.deleted', '0')
            ->where('online_payment.customer_id',$customer_id)
            ->groupBy('online_payment.id')
            ->get()->toArray();
        return $request;
    }

    public static function getQueryWarehouseCarRequests($args) {
        $customer_id = $args['customer_id'];
        $search      = $args['search'];

        $query = DB::Table('warehouse_car_requests')
            ->leftJoin('car_make', 'car_make.id_car_make', '=', 'warehouse_car_requests.id_car_make')
            ->leftJoin('car_model', 'car_model.id_car_model', '=', 'warehouse_car_requests.id_car_model')
            ->leftJoin('vehicle_type', 'vehicle_type.id_vehicle_type', '=', 'warehouse_car_requests.id_vehicle_type')
            ->leftJoin('color', 'warehouse_car_requests.color','=','color.color_id')
            ->leftJoin('port', 'warehouse_car_requests.destination','=','port.port_id')
            ->leftJoin('region', 'warehouse_car_requests.region_id','=','region.region_id')
            ->leftJoin('states', 'warehouse_car_requests.state_id','=','states.id')
            ->leftJoin('cities', 'warehouse_car_requests.city_id','=','cities.id')
            ->leftJoin('customer', 'warehouse_car_requests.customer_id','=','customer.customer_id')
            ->where('warehouse_car_requests.deleted', 0)
            ->when ($search , function ($query) use($search){
                $query->where(function($query)  use($search){
                    $query->where('warehouse_car_requests.vin', 'like','%' . $search . '%')
                        ->orWhere('warehouse_car_requests.lotnumber', 'like','%' . $search . '%')
                        ->orWhere('warehouse_car_requests.driver_name', 'like','%' . $search . '%');
                });
            });

        if (!empty($customer_id)) {
            $query->where('warehouse_car_requests.customer_id', $customer_id);
        }

        if (!empty($args['external_car'])) {
            $query->where('warehouse_car_requests.external_car', $args['external_car']);
        }

        return $query;
    }
    public static function getWarehouseCarRequests($args)
    {
        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;
        $NEJOUM_CDN = Constants::NEJOUM_CDN;

        $select = "warehouse_car_requests.*, DATE(warehouse_car_requests.create_date) visible_create_date, color.color_code,
        car_make.name AS carMakerName , car_model.name AS carModelName, vehicle_type.name AS vehicleName, region.region_name, cities.name city_name, states.name state_name,
        DATE(warehouse_car_requests.customer_approved_date) customer_approved_date, port.port_name destination_name,
        IF(warehouse_car_requests.car_photo > '', concat('$NEJOUM_CDN', IF(warehouse_car_requests.external_car='1', 'uploads/towing_cars/photos/', 'uploads/warehouse_cars/photos/') , warehouse_car_requests.car_photo), '') as car_photo_file,
        IF(warehouse_car_requests.invoice > '', concat('$NEJOUM_CDN', IF(warehouse_car_requests.external_car='1', 'uploads/towing_cars/invoices/', 'uploads/warehouse_cars/invoices/'), warehouse_car_requests.invoice), '') as invoice_file";
        $query = self::getQueryWarehouseCarRequests($args)
            ->groupBy('warehouse_car_requests.id');
        if($limit != 'all'){
            $query->skip($page * $limit)->take($limit);
        }
        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }

    public static function getWarehouseCarRequestsCount($args)
    {
        $query = self::getQueryWarehouseCarRequests($args);
        $query->select(DB::raw('COUNT(warehouse_car_requests.id) as totalRecords'));
        return $query->first()->totalRecords;
    }

    public static function getWarehouseCarRequest($args)
    {
        $select = $args['check_exist'] ? "warehouse_car_requests.id" : "warehouse_car_requests.*";
        $query = self::getQueryWarehouseCarRequests($args)
            ->groupBy('warehouse_car_requests.id');

        if($args['id']){
            $query->where('warehouse_car_requests.id', $args['id']);
        }

        if($args['where']){
            $query->where($args['where']);
        }

            $query->select(DB::raw($select));

        return $query->first();
    }

    public static function lotVinExist($args)
    {
        $select = "car.id";
        $query = DB::Table('car');
        $query->where(function($query) use($args) {
            $query->where('car.lotnumber', $args['lotnumber'])
            ->orWhere('car.vin', $args['vin']);
        });

        $query->select(DB::raw($select));
        return $query->first();
    }

    public static function  saveWarehouseCarRequest($request_id, $data) {
        $query = DB::table('warehouse_car_requests')->updateOrInsert(['warehouse_car_requests.id'=> $request_id], $data);
        return DB::getPdo()->lastInsertId();
    }

    public static function  updateWarehouseCarRequest($request_id, $data) {
        return DB::table('warehouse_car_requests')->where(['warehouse_car_requests.id'=> $request_id])->update($data);
    }

    public static function  deleteWarehouseCarRequest($request_id) {
        return DB::table('warehouse_car_requests')->where(['id' => $request_id])->update(['deleted' => 1]);
    }

    public static function  customerApproveWarehouseCarRequest($request_id) {

        $request = DB::table('warehouse_car_requests')->where(['id' => $request_id])->first();

        $data = ['towing_approved' => 1, 'towing_approved_date' => Helpers::get_db_timestamp()];
        DB::table('arrived_car')->where(['car_id' => $request->car_id])->update($data); // approve auto

        $data = ['customer_approved' => 1, 'customer_approved_date' => Helpers::get_db_timestamp()];
        return DB::table('warehouse_car_requests')->where(['id' => $request_id])->update($data);
    }

    public static function getCustomerContainersExport($args){

        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;
        $order = !empty($args['order']) ? $args['order'] : '';
        $customer_id = $args['customer_id'];
        $args['export'] = 1;
        if (empty($customer_id)) {
            return [];
        }

        $totalShippingQuery = DB::Table('container as c')
        ->join('container_car', 'container_car.container_id','=','c.container_id')
        ->join('car', 'container_car.car_id','=','car.id')
        ->leftJoin('car_total_cost', 'car_total_cost.car_id', '=', 'container_car.car_id')
        ->select(DB::raw("SUM(car_total_cost.total_price)"))
        ->whereRaw('c.container_id = container.container_id')
        ->whereRaw("car.customer_id = $customer_id")
        ->groupBy('c.container_id');
        $totalShippingQuery = $totalShippingQuery->toSql();

        $select = "container.container_number,container.container_id, shipping_status.shipping_date, booking.etd, booking.eta,booking.booking_number,
        car.arrived_port, DATE(container.loaded_date) loaded_date, port_destination.port_name destination, container.loaded_status, shipping_status.shipping_status, transport_request.received_status,
        booking_bl_container.arrival_date arrived_port_date, DATE(transport_request.received_create_date) arrived_store_date, IF(loaded_status.booking_id, loaded_status.loaded_date, DATE(container.loaded_date)) loaded_date,
        ($totalShippingQuery) as total_shipping, port_departure.port_name pol_name, container.size,
        car.year, car.vin, car_make.name AS carMakerName, car_model.name AS carModelName,car.lotnumber,car.vin, IF(external_car.car_id, external_auction_location.auction_location_name, auction_location.auction_location_name) auction_location_name,
        IF(external_car.car_id, external_auction.title, auction.title) auctionTitle,IF(port_destination.tag is not null,'".Constants::JEBEL_ALI_PORT_NAME."',port_destination.port_name) destination,car.purchasedate,CAST(bill_details.create_date AS DATE) as paymentDate,towing_status.picked_date,
        arrived_car.delivered_date,arrived_car.delivered_title,arrived_car.delivered_car_key,
        CAST(cars_title_status.create_date AS DATE) as titleDate,cars_title_status.follow_car_title_note,cars_title_status.follow_title";

        $query = self::getQueryCustomerContainers($args)
            ->groupBy('car.id');
        if($order){
            $order = Helpers::getOrder($order);
            if($order){
                $query->orderBy($order['col'],$order['dir']);
            }
        }else{
            $query->orderBy('loaded_status.loaded_date', 'desc');
        }
        if($limit != 'all'){
            $query->skip($page * $limit)->take($limit);
        }

        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }


    public static function saveOnlinePayment($data, $cars) {
        DB::beginTransaction();
        $cars = json_decode($cars);
        $query = 0;
        try {
            $carsArray = array();
            if($data){
                $query = DB::table('online_payment')->insertGetId($data);
                if($query){
                    foreach($cars as $key => $value)
                    {
                        $carsArray []= array(
                            'online_payment_id' => $query,
                            'car_id'            => $value
                        );
                    }
                    $querycars = DB::table('online_payment_cars')->insert($carsArray);
                }
            }
            DB::commit();
            $cashier = Helpers::get_users_by_role(Constants::ROLES['CASHIER']);
            $op = Helpers::get_users_by_role(Constants::ROLES['SALES_STORE_CUSTOMS_VAT_CARS_OFFICER_ACCOUTANT']);
            $IT = Helpers::get_users_by_department(Constants::DEPARTMENTS['IT']);
            $ACCOUNTANT = Helpers::get_users_by_department(Constants::DEPARTMENTS['ACCOUNTANT']);
            $ACCOUNTING_DATA_ENTRY = Helpers::get_users_by_department(Constants::DEPARTMENTS['ACCOUNTING_DATA_ENTRY']);
            $users = array_merge( $cashier, $IT, $op, $ACCOUNTANT, $ACCOUNTING_DATA_ENTRY);
            $users = array_column($users, 'id');
            Helpers::send_notification_service(array(
                'sender_id' => 1,
                'recipients_ids' =>$users,
                'subject' => 'New Customer Transfer',
                'subject_ar' => 'حوالة جديدة من عميل',
                'body' =>  "New Customer Transfer from customer, ". "Please click on the notification to review it.",
                'body_ar' =>  "يوجد حوالة جديدة مرسلة من التطبيق يرجى التطبيق  ". "يرجى الضغط على الاشعارات للمراجعة",
                'priority' => Constants::NOTIFICATION_PRIORITY_HIGH,
                'url' => "CarReport/carPaymentsFromApp",
                'type' => Constants::NOTIFICATION_ALERT_TYPE,
            ));
            return $query;
        }
        catch (\Exception $e) {
            DB::rollback();
            // something went wrong
        }
        return 0;
    }

    public static function saveOnlinePaymentOther($data, $cars) {
        DB::beginTransaction();
        $cars = json_decode($cars);
        $query = 0;
        try {
            $carsArray = array();
            if($data){
                $query = DB::table('online_payment')->insertGetId($data);
                if($query){
                    foreach($cars as $key => $value)
                    {
                        if($value->amount > 0){
                            $carsArray []= array(
                                'online_payment_id' => $query,
                                'service_id'        => $value->serviceId,
                                'amount'            => $value->amount
                            );
                        }
                    }
                    $querycars = DB::table('online_payment_cars')->insert($carsArray);
                }
            }
            DB::commit();
            $cashier = Helpers::get_users_by_role(Constants::ROLES['CASHIER']);
            $op = Helpers::get_users_by_role(Constants::ROLES['SALES_STORE_CUSTOMS_VAT_CARS_OFFICER_ACCOUTANT']);
            $IT = Helpers::get_users_by_department(Constants::DEPARTMENTS['IT']);
            $ACCOUNTANT = Helpers::get_users_by_department(Constants::DEPARTMENTS['ACCOUNTANT']);
            $ACCOUNTING_DATA_ENTRY = Helpers::get_users_by_department(Constants::DEPARTMENTS['ACCOUNTING_DATA_ENTRY']);
            $users = array_merge( $cashier, $IT, $op, $ACCOUNTANT, $ACCOUNTING_DATA_ENTRY);
            $users = array_column($users, 'id');
            Helpers::send_notification_service(array(
                'sender_id' => 1,
                'recipients_ids' =>$users,
                'subject' => 'New Customer Transfer',
                'subject_ar' => 'حوالة جديدة من عميل',
                'body' =>  "New Customer Transfer from customer, ". "Please click on the notification to review it.",
                'body_ar' =>  "يوجد حوالة جديدة مرسلة من التطبيق يرجى التطبيق  ". "يرجى الضغط على الاشعارات للمراجعة",
                'priority' => Constants::NOTIFICATION_PRIORITY_HIGH,
                'url' => "CarReport/carPaymentsFromApp",
                'type' => Constants::NOTIFICATION_ALERT_TYPE,
            ));
            return $query;
        }
        catch (\Exception $e) {
            DB::rollback();
            // something went wrong
        }
        return 0;
    }

    public static function saveOnlinePaymentFiles($data){
        DB::beginTransaction();
        try {
            $affected_rows = DB::Table('general_files')->insert($data);
            DB::commit();
            return 1;
        }catch (\Exception $e) {
            DB::rollback();
        }
        return 0;
    }


    public static function deletePaymentRequest($id){
        return 0;
        DB::beginTransaction();
        try {
            $s3 = Helpers::getS3Client();
            $bucket = env('AWS_S3_BUCKET_NAME');
            $request = DB::table('online_payment')->where(['id' => $id])->first()->file;
            $query1 = DB::table('online_payment')->where(['id'=> $id])->update(['deleted' => '1']);
            $query2 = DB::table('online_payment_cars')->where(['online_payment_id'=> $id])->update(['deleted' => '1']);
            if($request){
                $result = $s3->deleteObject(array(
                    'Bucket' => $bucket,
                    'Key'    => $request
                ));
            }
            DB::commit();
            return 1;
        } catch (\Exception $e) {
            DB::rollback();
        }
        return 0;
    }

    public static function getPaymentDetails($payment_id){
        $request = DB::Table('online_payment_cars')
            ->select(DB::raw("car.id, car.lotnumber, car.vin, car.year, car.photo, car.purchasedate, auction.title AS auction_title, car_make.name AS carMakerName , car_model.name AS carModelName"))
            ->leftJoin('car', 'online_payment_cars.car_id','=','car.id')
            ->leftJoin('car_make', 'car_make.id_car_make', '=', 'car.id_car_make')
            ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
            ->leftJoin('auction', 'auction.id', '=', 'car.auction_id')
            ->where('online_payment_cars.online_payment_id', $payment_id)
            ->get()->toArray();
        return $request;
    }

    public static function getTransferFee($customer_id, $cars){
        $today = date('Y-m-d');
        $request = DB::Table('car')
            ->select(DB::raw("car.id, customer_auction_rates.transfer_fee"))
            ->leftJoin('auction', 'auction.id', '=', 'car.auction_id')
            ->leftJoin('car_total_cost', 'car_total_cost.car_id', '=', 'car.id')
            ->leftJoin('customer', 'customer.customer_id', '=', 'car.customer_id')
            ->leftJoin('customer_contract', function($join) use ($today) {
                $join->on('customer_contract.customer_id', '=', 'customer.customer_id');
                $join->where('customer_contract.status', '=', '1');
                $join->on('customer_contract.start_date', '<=', DB::raw("'$today'"))
                ->where(function ($q) use ($today){
                    $q->whereNull('customer_contract.end_date')->orWhere('customer_contract.end_date', '>=', $today);
                  });
            })
            ->leftJoin('customer_auction_rates', function($join) {
                $join->on('customer_auction_rates.auction_id', '=', 'auction.id');
                $join->on('customer_auction_rates.customer_id', '=', 'customer.customer_id');
                $join->on('customer_auction_rates.contract_id', '=', 'customer_contract.customer_contract_id');
            })
            ->where('car.customer_id', $customer_id)
            ->whereNull('car_total_cost.car_id')
            ->whereIn('car.id', $cars)
            ->groupBy('car.buyer_id')
            ->get()->toArray();
        return $request;
    }
    
    public static function deleteCommonBuyerCars($id){
        DB::beginTransaction();
        try {
            $query1 = DB::table('common_buyer_cars')->where(['id'=> $id, 'status' => 2])->update(['deleted' => '1']);
            DB::commit();
            return 1;
        } catch (\Exception $e) {
            DB::rollback();
        }
        return 0;
    }

    public static function getAllCommonBuyerCarsWithFiles($customer_id) {
        $NEJOUM_IMAGE = Constants::NEJOUM_CDN;
        $cars = DB::Table('common_buyer_cars as cbc')
            ->select(DB::raw("cbc.*, buyer.buyer_number"))
            ->leftJoin('buyer', 'buyer.buyer_id', '=', 'cbc.buyer_id')
            ->where('cbc.deleted', '0')
            ->where('cbc.customer_id', $customer_id)
            ->get()->toArray();
        
        foreach ($cars as &$car) {
            $fileNames = DB::table('general_files')
                ->where([
                    'table_id' => 4, 
                    'primary_column' => $car->id, 
                    'tag' => 'Common Buyer Cars'
                ])
                ->select(DB::raw("CONCAT('$NEJOUM_IMAGE', '', file_name) as file_url"))
                ->pluck('file_url')
                ->toArray();
            
            $car->files = $fileNames;
        }
        return $cars;
    }
    
    public static function saveCommonBuyerCars($data) {
        DB::beginTransaction();
        $query = 0;
        try {
            if($data){
                $query = DB::table('common_buyer_cars')->insertGetId($data);
            }
            DB::commit();
            $customer_service = Helpers::get_users_by_department(Constants::DEPARTMENTS['CUSTOMER_SERVICE']);
            $op = Helpers::get_users_by_role(Constants::ROLES['SALES_STORE_CUSTOMS_VAT_CARS_OFFICER_ACCOUTANT']);
            $buyer_officer = Helpers::get_users_by_role(Constants::ROLES['BUYER_OFFICER']);
            $posting_officer = Helpers::get_users_by_role(Constants::ROLES['POSTING_OFFICER']);
            $cars_officer = Helpers::get_users_by_role(Constants::ROLES['CARS_OFFICER']);
            $IT = Helpers::get_users_by_department(Constants::DEPARTMENTS['IT']);
            $users = array_merge($IT, $op, $customer_service, $buyer_officer, $posting_officer, $cars_officer);
            $users = array_column($users, 'id');
            Helpers::send_notification_service(array(
                'sender_id' => 1,
                'recipients_ids' =>$users,
                'subject' => 'New Car added from customer',
                'subject_ar' => 'سيارة جديدة من عميل',
                'body' =>  "New Car added from customer, ". "Please click on the notification to review it.",
                'body_ar' =>  "يوجد سيارة جديدة مرسلة من التطبيق يرجى إدخالها على النظام  ". "يرجى الضغط على الاشعارات للمراجعة",
                'priority' => Constants::NOTIFICATION_PRIORITY_HIGH,
                'url' => "CarReport/carPaymentsFromApp",
                'type' => Constants::NOTIFICATION_ALERT_TYPE,
            ));
            return $query;
        }
        catch (\Exception $e) {
            DB::rollback();
            // something went wrong
        }
        return 0;
    }

    public static function saveCommonBuyerCarsFiles($data){
        DB::beginTransaction();
        try {
            $affected_rows = DB::Table('general_files')->insert($data);
            DB::commit();
            return 1;
        }catch (\Exception $e) {
            DB::rollback();
        }
        return 0;
    }

    public static function saveArrivedToPort($args){
        $car_id = $args['car_id'];
        $note = $args['message'];
        $arrived_date  = date('Y-m-d');

        DB::Table('car')
        ->where('id', $car_id)
        ->update(['arrived_port' =>  '1']);

        $arrived_port = self::arrived_port($car_id);

        if(!empty($arrived_port)){
            $affected_rows = DB::Table('booking_bl_container')
            ->where('booking_bl_container_id', $arrived_port->booking_bl_container_id)
            ->update(['arrival_date' => $arrived_date]);
        }

        return !empty($arrived_port);
    }

    public static function getServicesDetails(){
        $ids = [46, 47];
        $request = DB::Table('services_functionalities')
            ->select(DB::raw("services_functionalities.*"))
            ->whereIn('id', $ids)
            ->get()->toArray();
        return $request;
    }

    public static function getAllOnlinePaymentOther($customer_id){
        $request = DB::Table('online_payment')
            ->select(
                DB::raw("*,account_home.AccountName, services_functionalities.name, services_functionalities.description, services_functionalities.description_ar, online_payment.status as request_status, online_payment.note as request_notes, online_payment.id as payment_id, online_payment.create_date as payment_date, COUNT(online_payment_cars.id) AS cars_count"),
                DB::raw("(SELECT IF(online_payment_cars.status = 2, 2, 1) FROM online_payment_cars WHERE online_payment_cars.online_payment_id = online_payment.id LIMIT 1) AS role_label"),
                DB::raw("(SELECT IF(online_payment_cars.status = 2, online_payment_cars.note, 0) FROM online_payment_cars WHERE online_payment_cars.online_payment_id = online_payment.id LIMIT 1) AS role_label_notes"))
            ->leftJoin('online_payment_cars', 'online_payment_cars.online_payment_id','=','online_payment.id')
            ->leftJoin('account_home', 'account_home.ID','=','online_payment.bank_to')
            ->Join('services_functionalities', 'services_functionalities.id','=','online_payment_cars.service_id')
            ->where('online_payment.deleted', '0')
            ->where('online_payment.customer_id',$customer_id)
            ->groupBy('online_payment.id')
            ->get()->toArray();
        return $request;
    }

    public static function getPaymentOtherDetails($payment_id){
        $request = DB::Table('online_payment_cars')
            ->select(DB::raw("services_functionalities.*"))
            ->leftJoin('services_functionalities', 'services_functionalities.id','=','online_payment_cars.service_id')
            ->where('online_payment_cars.online_payment_id', $payment_id)
            ->get()->toArray();
        return $request;
    }


    public static function getAllPaidByCustomersCar($customer_id){
        $NEJOUM_IMAGE = Constants::NEJOUM_CDN;
        $cars = DB::Table('paid_by_customer_request')
            ->select(DB::raw("CONCAT('" . Constants::NEJOUM_CDN . "uploads/" . "', car.photo) as photo , paid_by_customer_request.review_notes, paid_by_customer_request.id,CAST(paid_by_customer_request.create_date as DATE) as date, paid_by_customer_request.status,
            car.lotnumber as lot, car.vin, paid_by_customer_request.notes"))
            ->leftJoin('car', 'car.id','=','paid_by_customer_request.car_id')
            ->where('paid_by_customer_request.deleted', '0')
            ->where('car.customer_id',$customer_id)
            ->get()->toArray();

        foreach ($cars as &$car) {
            $fileNames = DB::table('general_files')
                ->where([
                    'table_id' => 5, 
                    'primary_column' => $car->id, 
                    'tag' => 'Paid By Customer'
                ])
                ->select(DB::raw("CONCAT('$NEJOUM_IMAGE', '', file_name) as file_url"))
                ->pluck('file_url')
                ->toArray();
            
            $car->files = $fileNames;
        }
        return $cars;
    }

    public static function savePaidByCustomersCar($car_id, $notes, $customer_id) {
        $data = [
            'car_id'    => $car_id,
            'notes'     => $notes,
        ];
        $request = DB::Table('paid_by_customer_request')
        ->select(DB::raw("paid_by_customer_request.*"))
        ->where('deleted','=', '0')
        ->where('status','=', '0')
        ->where('car_id','=',$car_id)
        ->get()->toArray();
        if(!$request){
            $query = DB::table('paid_by_customer_request')->insertGetId($data);
            $customer_service = Helpers::get_users_by_department(Constants::DEPARTMENTS['CUSTOMER_SERVICE']);
            $op = Helpers::get_users_by_role(Constants::ROLES['SALES_STORE_CUSTOMS_VAT_CARS_OFFICER_ACCOUTANT']);
            $buyer_officer = Helpers::get_users_by_role(Constants::ROLES['BUYER_OFFICER']);
            $posting_officer = Helpers::get_users_by_role(Constants::ROLES['POSTING_OFFICER']);
            $cars_officer = Helpers::get_users_by_role(Constants::ROLES['CARS_OFFICER']);
            $IT = Helpers::get_users_by_department(Constants::DEPARTMENTS['IT']);
            $accounting_data_entry = Helpers::get_users_by_department(Constants::DEPARTMENTS['ACCOUNTING_DATA_ENTRY']);
            
            $users = array_merge($IT, $op, $customer_service, $buyer_officer, $posting_officer, $cars_officer, $accounting_data_entry);
            $users = array_column($users, 'id');
            Helpers::send_notification_service(array(
                'sender_id' => 1,
                'recipients_ids' =>$users,
                'subject' => 'Car Paid by customer',
                'subject_ar' => 'دفع سيارة في المزاد للعميل',
                'body' =>  "Car was Paid by customer to auction ". "Please click on the notification to review it.",
                'body_ar' =>  "تم دفع سيارة في المزاد من العميل ". "يرجى الضغط على الإشعار للمراجعة",
                'priority' => Constants::NOTIFICATION_PRIORITY_HIGH,
                'url' => "CarReport/carReviewsFromApp?carReviewsFromApp?type=paid_by_customer",
                'type' => Constants::NOTIFICATION_ALERT_TYPE,
            ));
            return $query;
        }
        return 0;
    }

    public static function deletePaidByCustomersCar($id){
        DB::beginTransaction();
        try {
            $query1 = DB::table('paid_by_customer_request')->where(['id'=> $id, 'status' => 0])->update(['deleted' => 1]);
            DB::commit();
            return 1;
        } catch (\Exception $e) {
            DB::rollback();
        }
        return 0;
    }

}

