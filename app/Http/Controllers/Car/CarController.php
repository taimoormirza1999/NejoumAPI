<?php


namespace App\Http\Controllers\Car;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Libraries\Constants;
use App\Libraries\Helpers;
use App\Services\CustomerCarService as CustomerCar;
use App\Models\CarAccounting;
use App\Services\CarService;
use App\Services\DashboardService;
use App\Services\GeneralService;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CarController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */


    protected $customer_id;
    public function __construct()
    {
        $this->customer_id        = Auth::user()->customer_id;
    }

    public function showAllCars(Request $request)
    {
        $args                   = $request->all();
        $args['customer_id']    = $this->customer_id;
        $allCarsDetails        = CarService::allCarsDetails($args);
        $allCars = [];
        foreach ($allCarsDetails as $dbRow):
            $dbRow->image = Constants::NEJOUM_CDN . 'uploads/' . $dbRow->photo;
            $dbRow->file_name = $dbRow->file_name?Constants::NEJOUM_CDN . 'upload/customer_file/' . $dbRow->file_name:'';
            $dbRow->invoice_file_auction  = $dbRow->invoice_file_auction?Constants::NEJOUM_CDN . 'uploads/invoice_file_auction/' . $dbRow->invoice_file_auction:'';
            $dbRow->sold = $dbRow->for_sell == '2'?'Sold':'';
            $dbRow->final_payment_status =  $dbRow->final_payment_status == '1'? 'Paid' : '';
            $dbRow->picked_car_title_note =  Helpers::formatNotes($dbRow->picked_car_title_note);
            $dbRow->showReceiverChange= $this->showReceiverChange($dbRow);
            $allCars[] = $dbRow;
        endforeach;
        $data = [
            'data'          => $allCars,
            'totalRecords'  => CarService::allCarsDetailsCount($args)
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    // car.port_id ==IRAQ_PORT_ID
    // car.car_loading_status === '0' &&
    // car.car_shipping_status === '0'
    private function showReceiverChange($raw):bool{
        if($raw->port_id == Constants::IRAQ_PORT_ID && $raw->arrivedstatus === '0' && $raw->car_loading_status === '0' && $raw->car_shipping_status === '0'){
            return true;
        }
        return false;
    }

    public function newCars(Request $request)
    {
        $args                   = $request->all();
        $args['customer_id']    = $this->customer_id;
        $newCarsCustomer        = CarService::getNewCars($args);
        $auction_location_fines = CarService::getAuctionLocationFines($newCarsCustomer);
        $newCars                = [];
        foreach ($newCarsCustomer as $dbRow) {

            if ($dbRow->country_id == Constants::COUNTRY['Canada']) {
                $dollar_price = $dbRow->candian_dollar_rate;
                $dollarType = 'CAD';
            } else {
                $dollar_price = $dbRow->us_dollar_rate;
                $dollarType = '$';
            }

            if ($dbRow->auction_id == Constants::AUCTIONS['Copart'] || $dbRow->auction_id == Constants::AUCTIONS['Copart_VIP']) {
                $dollar_price = $dbRow->us_dollar_rate;
                $dollarType = '$';
            }

            $late_payment_fine  = 0;
            $fineTotalCost      = 0;
            $extraDate          = null;
            $remainingDays      = null;
            $startStorage       = null;
            $lastDateToPay      = null;
            $daysOff            = null;
            if(!empty($auction_location_fines[$dbRow->id]['fineTotalCost'])){
                $fineTotalCost = $auction_location_fines[$dbRow->id]['fineTotalCost'];
            }
            if(!empty($auction_location_fines[$dbRow->id]['late_payment_fine'])){
                $late_payment_fine = $auction_location_fines[$dbRow->id]['late_payment_fine'];
            }
            if(!empty($auction_location_fines[$dbRow->id]['extraDate'])){
                $extraDate = $auction_location_fines[$dbRow->id]['extraDate'];
            }
            if(!empty($auction_location_fines[$dbRow->id]['remainingDays'])){
                $remainingDays = $auction_location_fines[$dbRow->id]['remainingDays'];
            }
            if(!empty($auction_location_fines[$dbRow->id]['fineTotalCost'])){
                $fineTotalCost = $auction_location_fines[$dbRow->id]['fineTotalCost'];
            }
            if(!empty($auction_location_fines[$dbRow->id]['startStorage'])){
                $startStorage = $auction_location_fines[$dbRow->id]['startStorage'];
            }
            if(!empty($auction_location_fines[$dbRow->id]['lastDateToPay'])){
                $lastDateToPay = $auction_location_fines[$dbRow->id]['lastDateToPay'];
            }
            if(!empty($auction_location_fines[$dbRow->id]['daysOff'])){
                $daysOff = $auction_location_fines[$dbRow->id]['daysOff'];
            }
            $delivered_title = 0; $delivered_car_key = 0;
            if ($dbRow->car_title == '1') {
                $delivered_title = 1;
            }if($dbRow->car_key == '1') {
                $delivered_car_key = 1;
            }
            $total_required = $dbRow->carcost * $dollar_price + $fineTotalCost * $dollar_price + $late_payment_fine * $dollar_price;
            $total_paida =  round($dbRow->amount_pay +  CarService::getallTransferedAmount($dbRow->id),2);
            $dataRow = [
                'carId'                 => $dbRow->id,
                'lotnumber'             => $dbRow->lotnumber,
                'vin'                   => $dbRow->vin,
                'carMakerName'          => $dbRow->carMakerName,
                'carModelName'          => $dbRow->carModelName,
                'year'                  => $dbRow->year,
                'destination'           => $dbRow->port_name,
                'image'                 => Constants::NEJOUM_CDN . 'uploads/' . $dbRow->photo,
                'purchasedDate'         => $dbRow->purchasedate,
                'auctionTitle'          => $dbRow->auctionTitle,
                'auctionLocationName'   => $dbRow->auction_location_name,
                'currencyRate'          => $dollar_price,
                'currencyName'          => $dollarType,
                'customerName'          => $dbRow->customerName,
                'extraDate'             => $extraDate,
                'lastDateToPay'         => $lastDateToPay,
                'daysOff'               => $daysOff,
                'paymentDate'           => $dbRow->paymentDate,
                'status'                => $dbRow->cancellation?'Cancelled':'Paid',
                'remainingAmount'        => Helpers::format_money($dbRow->remaining_amount),
                'remainingDays'         => $remainingDays,
                'startStorage'          => $startStorage,
                'delivered_title'       => $delivered_title,
                'delivered_car_key'     => $delivered_car_key,
                'total_paida'           => $total_paida,
                'late_payment_fineUSD'     => Helpers::format_money($late_payment_fine),
                'late_payment_fineAED'     => Helpers::format_money($late_payment_fine * $dollar_price),
                'carCostUSD'            => Helpers::format_money($dbRow->carcost),
                'carCostAED'            => Helpers::format_money($dbRow->carcost * $dollar_price),
                'totalUSD'              => Helpers::format_money($dbRow->carcost + $fineTotalCost + $late_payment_fine),
                'totalAED'              => Helpers::format_money($total_required),
                'fineTotalCost'         => Helpers::format_money($fineTotalCost * $dollar_price + $late_payment_fine * $dollar_price),
                'fineTotalCostUSD'      => Helpers::format_money($fineTotalCost + $late_payment_fine),
                'region'                => $dbRow->region,
                'buyer_number'          => $dbRow->buyer_number,
                'change_port_request_id'     => $dbRow->change_port_request_id,
                'change_port_request_create_date'     => $dbRow->change_port_request_create_date,
                'changed_port_name'     => $dbRow->changed_port_name,
                'showReceiverChange'    => $this->showReceiverChange($dbRow),
            ];

            $newCars[] = $dataRow;
        }

        $data = [
            'data'          => $newCars,
            'totalRecords'  => CarService::getNewCarsCount($args)
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function towingCars(Request $request)
    {
        $args                   = $request->all();
        $args['customer_id']    = $this->customer_id;
        $towingCarsCustomer     = CarService::getTowedCars($args);
        $towingCars             = [];
        foreach ($towingCarsCustomer as $dbRow) {
            $dataRow = [
                'carId'                 => $dbRow->id,
                'lotnumber'             => $dbRow->lotnumber,
                'vin'                   => $dbRow->vin,
                'carMakerName'          => $dbRow->carMakerName,
                'carModelName'          => $dbRow->carModelName,
                'year'                  => $dbRow->year,
                'image'                 => Constants::NEJOUM_CDN . 'uploads/' . $dbRow->photo,
                'purchasedDate'         => $dbRow->purchasedate,
                'auctionTitle'          => $dbRow->auctionTitle,
                'auctionLocationName'   => $dbRow->auction_location_name,
                'deliveredKey'          => $dbRow->deliveredKey,
                'deliveredTitle'        => $dbRow->deliveredTitle,
                'portName'              => $dbRow->port_name,
                'pickedDate'            => $dbRow->picked_date,
                'paymentDate'           => $dbRow->paymentDate,
                'ETD'                   => $dbRow->ETD,
                'region'                => $dbRow->region,
                'buyer_number'          => $dbRow->buyer_number,
                'picked_car_title_note' => Helpers::formatNotes($dbRow->picked_car_title_note),
            ];
            $towingCars[] = $dataRow;
        }
        $data = [
            'data'          => $towingCars,
            'totalRecords'  => CarService::getTowedCarsCount($args)
        ];
        return response()->json($data, Response::HTTP_OK);
    }


    public function warehouseCars(Request $request)
    {
        $args                   = $request->all();
        $args['customer_id']    = $this->customer_id;
        $warehouseCarsCustomer  = CarService::getWarehouseCars($args);
        $warehouseCars          = [];
        foreach ($warehouseCarsCustomer as $dbRow) {
            if ($dbRow->delivered_title == '1') {
                $date = $dbRow->delivered_date;
            }else if($dbRow->follow_title == '1'){
                $date = $dbRow->titleDate;
            }
            if($dbRow->follow_title == 1){
                $follow_title = '1';
            }else {
                $follow_title = '0';
            }
            $dataRow = [
                'carId'                 => $dbRow->id,
                'lotnumber'             => $dbRow->lotnumber,
                'vin'                   => $dbRow->vin,
                'carMakerName'          => $dbRow->carMakerName,
                'carModelName'          => $dbRow->carModelName,
                'year'                  => $dbRow->year,
                'image'                 => Constants::NEJOUM_CDN . 'uploads/' . $dbRow->photo,
                'purchasedDate'         => $dbRow->purchasedate,
                'auctionTitle'          => $dbRow->auctionTitle,
                'auctionLocationName'   => $dbRow->auction_location_name,
                'region'                => $dbRow->region,
                'portName'              => $dbRow->port_name,
                'pickedDate'            => $dbRow->picked_date,
                'paymentDate'           => $dbRow->paymentDate,
                'arrivedDate'           => $dbRow->delivered_date,
                'deliveredKey'          => $dbRow->car_key || $dbRow->delivered_car_key?'1':'0',
                'deliveredTitle'        => $dbRow->car_title || $dbRow->delivered_title?'1':'0',
                'titleDate'             => $date,
                'titleNote'             => $dbRow->follow_car_title_note,
                'buyer_number'          => $dbRow->buyer_number,
                'followTitle'           => $follow_title,
                'picked_car_title_note' => Helpers::formatNotes($dbRow->picked_car_title_note),
            ];
            $warehouseCars[] = $dataRow;
        }
        $data = [
            'data'          => $warehouseCars,
            'totalRecords'  => CarService::getWarehouseCarsCount($args)
        ];

        return response()->json($data, Response::HTTP_OK);

    }

    public function portCars(Request $request)
    {
        $args                   = $request->all();
        $args['customer_id']    = $this->customer_id;
        $portCarsCustomer  = CarService::getPortCars($args);
        $portCars          = [];
        foreach ($portCarsCustomer as $dbRow) {
            $dataRow = [
                'carId'                 => $dbRow->id,
                'lotnumber'             => $dbRow->lotnumber,
                'vin'                   => $dbRow->vin,
                'carMakerName'          => $dbRow->carMakerName,
                'carModelName'          => $dbRow->carModelName,
                'year'                  => $dbRow->year,
                'image'                 => Constants::NEJOUM_CDN . 'uploads/' . $dbRow->photo,
                'purchasedDate'         => $dbRow->purchasedate,
                'auctionTitle'          => $dbRow->auctionTitle,
                'auctionLocationName'   => $dbRow->auction_location_name,
                'region'                => $dbRow->region,
                'portName'              => $dbRow->port_name,
                'loaded_date'           => $dbRow->loaded_date,
                'booking_number'        => $dbRow->booking_number,
                'container_number'      => $dbRow->container_number,
                'shipping_date'         => $dbRow->shipping_date,
                'arrival_date'          => $dbRow->booking_arrival_date,
                'pickedDate'            => $dbRow->picked_date,
                'paymentDate'           => $dbRow->paymentDate,
                'arrivedDate'           => $dbRow->delivered_date,
                'deliveredKey'          => $dbRow->car_key,
                'deliveredTitle'        => $dbRow->car_title,
                'titleNote'             => $dbRow->follow_car_title_note,
                'isUAEPort'             => $dbRow->isUAEPort,
                'buyer_number'          => $dbRow->buyer_number,
                'picked_car_title_note' =>  Helpers::formatNotes($dbRow->picked_car_title_note)
            ];
            $portCars[] = $dataRow;
        }
        $data = [
            'data'          => $portCars,
            'totalRecords'  => CarService::getPortCarsCount($args)
        ];

        return response()->json($data, Response::HTTP_OK);

    }

    public function onWayCars(Request $request)
    {
        $args                   = $request->all();
        $args['customer_id']    = $this->customer_id;
        $onWayCarsCustomer      = CarService::getOnWayCars($args);
        $onWayCars              = [];
        foreach ($onWayCarsCustomer as $dbRow) {
            $dbRow->picked_car_title_note = Helpers::formatNotes($dbRow->picked_car_title_note);

            $dataRow        = $dbRow;
            $dataRow->image = Constants::NEJOUM_CDN . 'uploads/' . $dbRow->photo;
            $onWayCars[]    = $dataRow;
        }
        $data = [
            'data'          => $onWayCars,
            'totalRecords'  => CarService::getOnWayCarsCount($args)
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function arrivedCars(Request $request)
    {
        $args                   = $request->all();
        $args['customer_id']    = $this->customer_id;
        $arrivedCarsCustomer    = CarService::getArrivedCars($args);
        $arrivedCars            = [];
        foreach ($arrivedCarsCustomer as $dbRow) {
            $dbRow->picked_car_title_note = Helpers::formatNotes($dbRow->picked_car_title_note);

            $dataRow        = $dbRow;
            $dataRow->image = Constants::NEJOUM_CDN . 'uploads/' . $dbRow->photo;
            $arrivedCars[]  = $dataRow;
        }
        $data = [
            'data'          => $arrivedCars,
            'totalRecords'  => count($arrivedCars)
        ];

        return response()->json($data, Response::HTTP_OK);

    }

    public function deliveredCars(Request $request)
    {
        $args                   = $request->all();
        $args['customer_id']    = $this->customer_id;
        $deliveredCarsCustomer  = CarService::getDeliveredCars($args);
        $deliveredCars          = [];
        foreach ($deliveredCarsCustomer as $dbRow) {
            $dataRow            = $dbRow;
            $dataRow->image     = Constants::NEJOUM_CDN . 'uploads/' . $dbRow->photo;
            $dataRow->picked_car_title_note = Helpers::formatNotes($dbRow->picked_car_title_note);
            $deliveredCars[]    = $dataRow;
        }
        $data = [
            'data'          => $deliveredCars,
            'totalRecords'  => CarService::getDeliveredCarsCount($args)
        ];

        return response()->json($data, Response::HTTP_OK);
    }
    public function getModelCustomer(Request $request)
    {
        $customer_id    = $this->customer_id;
        $id = $request->maker_id;
        $year = $request->year;
        $carModelCustomer = CustomerCar::carModelCustomer($customer_id, $id, $year);
        $data = [
            'data' => $carModelCustomer
        ];

        return response()->json($data, Response::HTTP_OK);
    }
    public function saveNotes(Request $request)
    {
        $customer_id = $this->customer_id;
        $car_id = $request->car_id;
        $notes = $request->notes;
        $data = CustomerCar::saveNotes($customer_id, $car_id, $notes);
        if($data){
            $data = [
                'success'=> true,
                'message' => 'Updated successfully'
            ];
        }else{
            $data = [
                'success'=> false,
                'message' => 'Failed to update'
            ];
        }
        return response()->json($data, Response::HTTP_OK);

    }

    public function getCustomerAllCancelledCars(Request $request)
    {
        $args = $request->all();
        $args['customer_id']  = $this->customer_id;
        $customerCancelledCars = CustomerCar::getCustomerCancelledCars($args);

        $customerCars = [];
        foreach ($customerCancelledCars as $dbRow) {

            if ($dbRow->country_id == Constants::COUNTRY['Canada']) {
                $dollarPrice = $dbRow->candian_dollar_rate;
                $dollarType = 'CAD';
            } else {
                $dollarPrice = $dbRow->us_dollar_rate;
                $dollarType = '$';
            }

            // exception for auction id 7 OR 14
            if ($dbRow->auction_id == Constants::AUCTIONS['Copart'] || $dbRow->auction_id == Constants::AUCTIONS['Copart_VIP']) {
                $dollarPrice = $dbRow->us_dollar_rate;
                $dollarType = '$';
            }
            //calculate cancellation date : from the purchase date
            $cancellationDate = strtotime("+{$dbRow->day_of_cancellation} day", strtotime($dbRow->purchasedate));
            $cancellationDate = date('Y-m-d', $cancellationDate);

            if ($dbRow->sales_price <= $dbRow->amount_cancellation) {
                $fineCost = $dbRow->min_cancellation;
            } else {
                $fineCost = $dbRow->sales_price * $dbRow->max_cancellation / 100;
            }
            $cancel_fine_cost_aed = $fineCost * $dollarPrice;
            $dataRow = [
                'carId' => $dbRow->id,
                'lotnumber' => $dbRow->lotnumber,
                'vin' => $dbRow->vin,
                'carMakerName' => $dbRow->carMakerName,
                'carModelName' => $dbRow->carModelName,
                'vehicleName' => $dbRow->vehicleName,
                'year' => $dbRow->year,
                'image' => Constants::NEJOUM_CDN . 'uploads/' . $dbRow->photo,
                'purchaseDate' => $dbRow->purchasedate,
                'auctionTitle' => $dbRow->auction_title,
                'auctionLocationName' => $dbRow->auction_location_name,
                'currencyRate' => $dollarPrice,
                'currencyName' => $dollarType,
                'cancellationDate' => $cancellationDate,
                'destination' => $dbRow->port_name,
                'region' => $dbRow->region,
                'totalAED' => Helpers::format_money($cancel_fine_cost_aed)
            ];
            $customerCars[] = $dataRow;
        }

        $data = [
            'data' => $customerCars,
            'totalRecords' => CustomerCar::getCustomerCancelledCarsCount($args)
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function getCustomerAllCompletedCars(Request $request)
    {
        $args = $request->all();
        $args['customer_id']  = $this->customer_id;
        $customerCarsDB = CustomerCar::getCompletedCars($args);
        $customerCars = [];
        foreach ($customerCarsDB as $dbRow) {
            $args2 = [
                'car_id' => $dbRow->id,
                'account_id' => $dbRow->customer_account_id,
            ];

            $carTransactions = CarAccounting::getCarAccountTransactions($args2);
            $transactionLabels = Helpers::getCarTransactionLabels($carTransactions);
            $totalDebit = array_sum(array_column($transactionLabels, 'debit'));
            $totalCredit = array_sum(array_column($transactionLabels, 'credit'));

            $dataRow = [
                'car_id' => $dbRow->id,
                'carModelName' => $dbRow->carModelName,
                'carMakerName' => $dbRow->carMakerName,
                'vehicleName' => $dbRow->vehicleName,
                'color_name' => $dbRow->color_name,
                'color' => $dbRow->color,
                'year' => $dbRow->year,
                'lotnumber' => $dbRow->lotnumber,
                'vin' => $dbRow->vin,
                'auction_title' => $dbRow->auction_title,
                'container_number' => $dbRow->container_number,
                'purchased_date' => $dbRow->purchasedate,
                'completed_date' => $dbRow->completed_date,
                'transactionLabels' => $transactionLabels,
                'remainingBalance' => Helpers::format_money($totalDebit - $totalCredit),
                'photo' => Constants::NEJOUM_CDN . 'uploads/' . $dbRow->photo,
                'photo_small' => Constants::NEJOUM_CDN . 'uploads/app/' . $dbRow->photo,

            ];
            $customerCars[] = $dataRow;
        }
        $data = [
            'data' => $customerCars,
            'totalRecords' => CustomerCar::getCompletedCarsCount($args)
        ];

        return response()->json($data, Response::HTTP_OK);
    }
    public function getTrackSearch(Request $request){
        $lot_vin     = $request->lot_vin;
        $result = CustomerCar::search($lot_vin);
        $sub_array = array();
        $bill = NULL;
        $auction = NULL;
        $postedstatus = NULL;
        $towingstatus = NULL;
        $arrivedstatus = NULL;
        $loading_status = NULL;
        $shipping_status = NULL;
        $arrived_port = NULL;
        $pick_status = NULL;
        $arrive_store = NULL;
        $deliver_customer = NULL;

        if ($result) {
            $result1 = CustomerCar::search1($result->id);
            if ($result->car_payment_to_cashier == 1 || $result->car_payment_to_cashier == 2) {
                $bill = CustomerCar::getBill($result->id);
            }
            if ($result->paidstatus == 1 || $result->paidstatus == 2) {
                $auction = CustomerCar::getAuction($result->id);
            }
            if ($result->post_status == 1) {
                $postedstatus = CustomerCar::postedstatus($result->id);
            }
            if ($result->towingstatus == 1) {
                $towingstatus = CustomerCar::towingstatus($result->id);
            }
            if ($result->arrivedstatus == 1) {
                $arrivedstatus = CustomerCar::arrivedstatus($result->id);
            }

            if ($result->loading_status == 1) {
                $loading_status = CustomerCar::loading_status($result->id);
            }
            if ($result->shipping_status == 1) {
                $shipping_status = CustomerCar::shipping_status($result->id);
            }
            if ($result->arrived_port == 1) {
                $arrived_port = CustomerCar::arrived_port($result->id);
            }
            if ($result->pick_status == 1) {
                $pick_status = CustomerCar::pick_status($result->id);
            }
            if ($result->arrive_store == 1) {
                $arrive_store = CustomerCar::arrive_store($result->id);
            }
            if ($result->deliver_customer == 1) {
                $deliver_customer = CustomerCar::deliver_customer($result->id);
            }

            $notesDB = GeneralService::getOperationNotesArray(['car_id' => $result->id, 'type' => Constants::OperationNotes['ISSUE_NOTES']]);
            $notes = [];
            foreach ($notesDB as $row) {
                $notes[$row->type] = $row;
            }
            unset($notesDB);

            $arrayTracking =array(
                'delivercustomer' => $result->deliver_customer,
                 'arrive_store'=>$result->arrive_store,
                 'arrived_port'=>$result->arrived_port,
                 'shipping_status'=>$result->shipping_status,
                 'loading_status' =>$result->loading_status,
                 'arrivedstatus' =>$result->arrivedstatus,

            );
            $lastStatus=array_search(1, $arrayTracking);
            $sub_array['car_data']          = $result;
            $sub_array['num']           = $result->vin;
            $sub_array['bill']          = $bill;
            $sub_array['auction']       = $auction;
            $sub_array['postedstatus']  = $postedstatus;
            $sub_array['towingstatus']  = $towingstatus;
            $sub_array['arrivedstatus'] = $arrivedstatus;
            $sub_array['planned']       = $result1;
            $sub_array['loading_status'] = $loading_status;
            $sub_array['shipping_status'] = $shipping_status;
            $sub_array['arrived_port'] = $arrived_port;
            $sub_array['pick_status'] = $pick_status;
            $sub_array['arrive_store'] = $arrive_store;
            $sub_array['deliver_customer'] = $deliver_customer;
            $sub_array['lastStatus'] = $lastStatus;
            $sub_array['arrayTracking'] = $arrayTracking;
            $sub_array['notes'] = $notes;
        }
        $data = [
            'data' => $sub_array
        ];
        return response()->json($data, Response::HTTP_OK);
    }
    public function getCustomerVinLot(Request $request){
        $customer_id = $this->customer_id;
        $lotCars = CustomerCar::getCustomerLot($customer_id);
        $vinCars = CustomerCar::getCustomerVin($customer_id);
        $full_array = array();
        foreach ($lotCars as $key => $value) {
            array_push($full_array, $value->lotnumber);
        }
        foreach ($vinCars as $key => $value) {
            array_push($full_array, $value->vin);
        }

        $data = [
            'data' => $full_array
        ];
        return response()->json($data, Response::HTTP_OK);
    }
    public function getSpecialPortCustomerCars(Request $request){
        $customer_id = $request->customer_id;
        $cars = CustomerCar::getSpecialPortCustomerCars($customer_id);
        $full_array = array();
        foreach ($cars as $key => $value) {
            array_push($full_array, $value->id);
        }

        $data = [
            'data' => $full_array
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function warehouseRegion(){
        $customer_id = $this->customer_id;
        $cars = CarService::getCarWarehouseRegion($customer_id);
        $data = [
            'data' => $cars
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function getPricesLists(){
        $customer_id = $this->customer_id;
        $cars = CarService::getPricesLists($customer_id);
        $data = [
            'data' => $cars
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function statesCount(){
        $customer_id = $this->customer_id;
        $cars = CarService::getStatesCount($customer_id);

        $data = [
            'data' => $cars
        ];
        return response()->json($data, Response::HTTP_OK);
    }
    public function carsArrivedStore(Request $request){
        $args = $request->all();
        $args['customer_id'] = $this->customer_id;
        $data = CarService::carsArrivedStoreDetailsd($args);
        foreach ($data as $key => $value) {
            $data[$key]->picked_car_title_note = Helpers::formatNotes($value->picked_car_title_note);

            $data[$key]->image_small = Constants::NEJOUM_CDN.'uploads/' . $value->photo;
        }
        $output = array(
            "data"      => $data,
            'totalRecords' => CarService::carsArrivedStoreDetailsCount($args)
        );
        return response()->json($output, Response::HTTP_OK);
    }

    public function dashboardCarsCount(){
        $args['customer_id'] = $this->customer_id;
        $data = DashboardService::dashboardCarsCount($args);

        $output = array(
            "data"      => $data,
        );
        return response()->json($output, Response::HTTP_OK);
    }

    public function getCustomerBalance(){
        $data = CustomerCar::getCustomerBalance($this->customer_id);

        $output = array(
            "data"      => $data,
        );
        return response()->json($output, Response::HTTP_OK);
    }

    public function getCustomerBalancenoAuth(Request $request){
        $data = CustomerCar::getCustomerBalance($request->customer_id);

        $output = array(
            "data"      => number_format($data,2),
        );
        return response()->json($output, Response::HTTP_OK);
}
    public function specialPortReceivableInfo(Request $request){
        $car_id = $request->car_id;
        $name = $request->customer_name;
        $data = false;
        if($car_id && $name){
            $data = CustomerCar::saveReceivableInfo($car_id, $name);
        }
        if($data){
            $data = [
                'success'=> true,
                'message' => 'Updated successfully'
            ];
        }else{
            $data = [
                'success'=> false,
                'message' => 'Failed to update or car in warehouse not allowed to edit'
            ];
        }
        return response()->json($data, Response::HTTP_OK);
    }
    public function destinationChangeCars(Request $request){
        $customer_id = $request->customer_id;
        $data = [];
        if($customer_id){
            $data = CustomerCar::destinationChangeCars($customer_id);
        }
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }
    public function updateCustomerDestination(Request $request, \App\Services\CarSellService $sellService ){
        $customer_id = $request->customer_id;
        $car_id = $request->car_id;
        $destination = $request->destination;
        if($destination == Constants::UMMQASR_PORT){
            if($sellService->checkYear($car_id) < '2021'){
                $data = [
                    'success'=> false,
                    'message_ar' => trans('Umm Qasr cars less than 2021 model cannot be updated',[],'ar'),
                    'message'=> 'Umm Qasr cars less than 2021 model cannot be updated',
                ];
                return response()->json($data, Response::HTTP_OK);
            }
        }
        $isDischargePort = CustomerCar::isDischargePort($destination);
        if($isDischargePort){
                $data = [
                    'success'=> false,
                    'message_ar' => trans('This car cannot be updated',[],'ar'),
                    'message'=> 'This car cannot be updated',
                ];
                return response()->json($data, Response::HTTP_OK);
        }
        if($car_id && $customer_id && $destination){
            $data = CustomerCar::saveCustomerDestination($car_id, $customer_id, $destination, $sellService);
        }
        if($data){
            $data = [
                'success'=> true,
                'message' => 'Updated successfully'
            ];
        }else{
            $data = [
                'success'=> false,
                'message' => 'Failed to update'
            ];
        }
        return response()->json($data, Response::HTTP_OK);
    }
    public function deleteDestinationRequest(Request $request){
        $request_id = $request->id;
        $deleted = CustomerCar::deleteDestinationRequest($request_id);
        if($deleted){
            $data = [
                'success'=> true,
                'message' => 'Deleted successfully'
            ];
        }else{
            $data = [
                'success'=> false,
                'message' => 'Unable to Delete'
            ];
        }

        return response()->json($data, Response::HTTP_OK);
    }
    public function sendDestinationRequest(Request $request, \App\Services\CarSellService $sellService){
        $customer_id = $request->customer_id;
        $car_id = $request->car_id;
        $destination = $request->destination;
        $notes = $request->notes;
        $receiver_name = $request->receiver_name;
        if($destination == Constants::UMMQASR_PORT){
            if($sellService->checkYear($car_id) < '2021'){
                $data = [
                    'success'=> false,
                    'message_ar' => trans('Umm Qasr cars less than 2021 model cannot be updated',[],'ar'),
                    'message'=> 'Umm Qasr cars less than 2021 model cannot be updated',
                ];
                return response()->json($data, Response::HTTP_OK);
            }
        }
        $isDischargePort = CustomerCar::isDischargePort($destination);
        if($isDischargePort){
            $data = [
                'success'=> false,
                'message_ar' => trans('This car cannot be updated',[],'ar'),
                'message'=> 'This car cannot be updated',
            ];
            return response()->json($data, Response::HTTP_OK);
        }
        if($car_id && $customer_id && $destination){
            $data = CustomerCar::saveDestinationRequest($car_id, $destination, $notes, $customer_id, $receiver_name);
        }
        if($data){
            $data = [
                'success'=> true,
                'message' => 'Updated successfully'
            ];
        }else{
            $data = [
                'success'=> false,
                'message' => 'Failed to update'
            ];
        }
        return response()->json($data, Response::HTTP_OK);
    }

    public function containersCount(Request $request){
        $args = $request->all();
        $args['customer_id']    = $this->customer_id;

        $counts = [
            'all' => CustomerCar::totalContainersCount($args, ''),
            'inShipping' => CustomerCar::totalContainersCount($args, 'inShipping'),
            'arrivedPort' => CustomerCar::totalContainersCount($args, 'arrivedPort'),
            'arrivedStore' => CustomerCar::totalContainersCount($args, 'arrivedStore'),
            'deliveredAll' => CustomerCar::totalContainersCount($args, 'deliveredAll'),
            'deliveredUnPaid' => CustomerCar::totalContainersCount($args, 'delivered', 'unPaid'),
            'deliveredPaid' => CustomerCar::totalContainersCount($args, 'delivered', 'paid'),
        ];
        return response()->json($counts, Response::HTTP_OK);
    }

    public function customerContainers(Request $request){
        $args                   = $request->all();
        $args['customer_id']    = $this->customer_id;

        $containers = CustomerCar::getCustomerContainers($args);
        foreach($containers as $key => $row){
            $status = '';
            if($row->received_status == 1){
                $status = 'Arrived Store';
            }
            else if($row->arrived_port == 1){
                $status = 'Arrived Port';
            }
            else{
                $status = 'On Way ETA: '. $row->eta;
            }
            $row->status = $status;
            $row->cars_shipping_amount = 0;
            $containers[$key] = $row;
        }

        if($args['customer_id']){
            $output = array(
                "data"      => $containers,
                'totalRecords'  => CustomerCar::getCustomerContainersCount($args)
            );
        }
        else{
            $output = array(
                "data"      => [],
            );
        }

        return response()->json($output, Response::HTTP_OK);
    }

    public function containerDetail(Request $request){
        $args                   = $request->all();
        $args['customer_id']    = $this->customer_id;

        $containerDetail = CustomerCar::getContainerDetail($args);
        if($containerDetail->cars_list){
            $containerDetail->weight = CarService::getCarsWeight($containerDetail->cars_list);
        }

        $output = array(
            "data"      => $containerDetail,
        );
        return response()->json($output, Response::HTTP_OK);

    }

    public function getAllDestinationRequest(Request $request){
        $customer_id = $request->customer_id;
        $data = [];
        if($customer_id){
            $data = CustomerCar::getAllDestinationRequest($customer_id);
        }
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function saveDeliveredToCustomer(Request $request){
        $args  = $request->all();
        $data = CustomerCar::saveDeliveredToCustomer($args);

        if($data){
            $data = [
                'success'=> true,
                'message' => 'Updated successfully'
            ];
        }else{
            $data = [
                'success'=> false,
                'message' => 'Failed to update'
            ];
        }
        return response()->json($data, Response::HTTP_OK);
    }

    public function saveArrivedToStore(Request $request){
        $args  = $request->all();
        $data = CustomerCar::saveArrivedToStore($args);

        if($data){
            $data = [
                'success'=> true,
                'message' => 'Updated successfully'
            ];
        }else{
            $data = [
                'success'=> false,
                'message' => 'Failed to update'
            ];
        }
        return response()->json($data, Response::HTTP_OK);
    }


    public function getAllOnlinePayment(Request $request){
        $customer_id = $request->customer_id;
        $data = [];
        if($customer_id){
            $data = CustomerCar::getAllOnlinePayment($customer_id);
        }
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function warehouseCarRequests(Request $request){
        $args = $request->all();
        $args['customer_id'] = $this->customer_id;
        $customer_id = $this->customer_id;

        try{
            if($customer_id){
                $output = [
                    'data' => CustomerCar::getWarehouseCarRequests($args),
                    'totalRecords' => CustomerCar::getWarehouseCarRequestsCount($args)
                ];
            }
            else{
                $output = [
                    'data' => [],
                    'totalRecords' => 0
                ];
            }

            return response()->json($output, Response::HTTP_OK);
        } catch(\Exception $e){
            $output = array(
                'success'=> false,
                'message' => $e->getMessage()
            );
            return response()->json($output, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function warehouseCarRequestExist(Request $request){
        $args = $request->all();
        $args['check_exist'] = 1;

        $where = [];
        if($args['id']){
            $where[]= [ 'warehouse_car_requests.id', '!=', $args['id']];
            unset($args['id']);
        }

        try{
        $args['where'] = array_merge($where, [['warehouse_car_requests.lotnumber', $args['lotnumber']]]);
        $carRecord = CustomerCar::getWarehouseCarRequest($args);

        if(!$carRecord){
            $args['where'] = array_merge($where, [['warehouse_car_requests.vin', $args['vin']]]);
            $carRecord = CustomerCar::getWarehouseCarRequest($args);
        } 
        if(!$carRecord){
            $carRecord = CustomerCar::lotVinExist($args);
        } 

        $output = [
            'carExist' => !empty($carRecord)
        ];

        return response()->json($output, Response::HTTP_OK);
        }
        catch(\Exception $e){
            $output = array(
                'success'=> false,
                'message' => $e->getMessage()
            );
            return response()->json($output, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function warehouseCarRequest(Request $request){
        $args = $request->all();
        if(!$args['id']){
            return response()->json([], Response::HTTP_OK);
        }
        try{
        $output = CustomerCar::getWarehouseCarRequest($args);
        return response()->json($output, Response::HTTP_OK);
        }
        catch(\Exception $e){
            $output = array(
                'success'=> false,
                'message' => $e->getMessage()
            );
            return response()->json($output, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function saveWarehouseCarRequest(Request $request){
        $params = $request->all();
        $data = $params['fields'];
        if(empty($data['customer_id'])){
            $data['customer_id'] = $this->customer_id;
        }
        $request_id = $data['id'];
        $data['customer_id'] = trim($data['customer_id']);
        unset($data['id']);

        if(!$data['customer_id'] || $data['customer_id'] < 1){
            $output = ['success'=> false, 'message' => 'Failed to update'];
            return response()->json($output, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        Storage::disk('local')->put("uploads/car-requests/{$data['customer_id']}/{$data['lotnumber']}", json_encode(['request' => $request, 'data' => $data]));
        try{
        $request_id  = CustomerCar::saveWarehouseCarRequest($request_id, $data);
        if($request_id){

            if($data['external_car'] == '1' && $data['gate_pass_pin'] != '0'){ // same day notifcation for towing cars
                $dispatch_users = Helpers::get_users_by_role(Constants::ROLES['DISPATCHER']);
                $posting_officer_users = Helpers::get_users_by_role(Constants::ROLES['POSTING_OFFICER']);
                $cars_officer_users = Helpers::get_users_by_role(Constants::ROLES['CARS_OFFICER']);
                $dispatch_users = array_column($dispatch_users, 'id');
                $posting_officer_users = array_column($posting_officer_users, 'id');
                $cars_officer_users = array_column($cars_officer_users, 'id');
                $users = array_merge($dispatch_users, $posting_officer_users, $cars_officer_users);
                
                $lotnumber = $data['lotnumber'];
                Helpers::send_notification_service([
                    'sender_id' => 1,
                    'recipients_ids' =>$users,
                    'subject' => 'New external towing car request is added',
                    'subject_ar' => 'تمت إضافة طلب سيارة سحب خارجية جديدة',
                    'body' =>  "A request has been added for car lot # $lotnumber Click on the notification to add this car.",
                    'body_ar' => "تمت إضافة طلب لمجموعة سيارات # $lotnumber انقر على الإشعار لإضافة هذه السيارة.",
                    'priority' => Constants::NOTIFICATION_PRIORITY_HIGH,
                    'url' => "external_car/addnewcar?car_request_id=$request_id",
                    'type' => Constants::NOTIFICATION_ALERT_TYPE,
                ]);
            }

            $output = array(
                'success'=> true,
                'message' => 'Saved successfully'
            );
        }else {
            $output = array(
                'success'=> false,
                'message' => 'Failed to update'
            );
        }
        return response()->json($output, Response::HTTP_OK);
        }
        catch(\Exception $e){
            $output = array(
                'success'=> false,
                'message' => $e->getMessage()
            );
            return response()->json($output, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteWarehouseCarRequest(Request $request){
        $args = $request->all();
        $request_id = $args['id'];

        $result  = CustomerCar::deleteWarehouseCarRequest($request_id);
        if($result){
            $output = array(
                'success'=> true,
                'message' => 'Saved successfully'
            );
        }else {
            $output = array(
                'success'=> false,
                'message' => 'Failed to update'
            );
        }
        return response()->json($output, Response::HTTP_OK);

    }

    public function customerApproveWarehouseCarRequest(Request $request){
        $args = $request->all();
        $result  = CustomerCar::customerApproveWarehouseCarRequest($args['id']);

        if($result){
            $output = array(
                'success'=> true,
                'message' => 'Saved successfully'
            );
        }else {
            $output = array(
                'success'=> false,
                'message' => 'Failed to update'
            );
        }
        return response()->json($output, Response::HTTP_OK);
    }

    public function containerInvoice(Request $request){
        $args                   = $request->all();
        $args['customer_id']    = $this->customer_id;
        $currency = empty($request->currency) ? 'aed' : $request->currency;
        $exchange_rate = $currency == 'usd' ? Helpers::get_usd_rate() : 1;

        $bankDetail = [
          'bankName'  => 'Emirates NBD',
          'swiftCode'  => 'EBILAEAD',
          'accountName'  => 'NEJOUM AL JAZEERA USED CARS LLC',
          'iban' => 'AE20 0260 0010 1577 2387 001',
          'accountNumber' => '1015772387001',
          'bankAddress' => 'Sahara Mall Branch, Sharjah, UAE',
        ];

        $containerDetail = CustomerCar::getContainerInvoiceDetail($args);
        if($containerDetail){
            $containerDetail->past_due_days = max(0, $containerDetail->past_due_days);
        }
        $carsDetail = CustomerCar::getContainerInvoiceCars($args);

        $totalAmount = 0; $paidAmount = 0;
        foreach($carsDetail as $key => $carsDetailRow){
            $response = (new \App\Http\Controllers\Accounting\CarAccountingController())->shippingBillDetail($carsDetailRow->car_id, $request);
            $billDetail = $response->getData()->data;
            $temp_bill_detail = $billDetail;
            $otherAmount = 0;
            $discount = 0;
            foreach($billDetail as $bill_key=>$billItem){
                $billColumn = '';
                if($billItem->car_step == Constants::CAR_STEPS['LOADING'] || $billItem->car_step == Constants::CAR_STEPS['SHIPPING']){
                    $billColumn = 'shippingAmount';
                }
                else if($billItem->car_step == Constants::CAR_STEPS['CLEARANCE']){
                    $billColumn = 'clearanceAmount';
                }
                else if($billItem->car_step == Constants::CAR_STEPS['TOWING']){
                    $billColumn = 'towingAmount';
                }
                else if($billItem->car_step == Constants::CAR_STEPS['FOKLIFT']){
                    $billColumn = 'forkliftAmount';
                }
                else if($billItem->car_step == Constants::CAR_STEPS['LATE_PAYMENT']){
                    $billColumn = 'latePaymentAmount';
                }
                else if($billItem->car_step == Constants::CAR_STEPS['TowingFine']){
                    $billColumn = 'auctionFineAmount';
                }
                else if($billItem->car_step == Constants::CAR_STEPS['SPECIAL_CODE']){
                    $billColumn = 'specialCodeAmount';
                }
                else{
                    $otherAmount += $billItem->debit;
                }

                if($billColumn){
                    $carsDetailRow->$billColumn =  number_format($billItem->debit, 2, '.', '');
                }
                if(array_key_exists($billItem->car_step, Constants::DISCOUNT_CAR_STEPS) && $billItem->credit > 0){
                    $discount += $billItem->credit;
                    unset($billDetail[$bill_key]);
                }

            }
            $carsDetailRow->discount = number_format($discount, 2, '.', '');
            $carsDetailRow->notes = CarAccounting::getCarAccountingNotes(['car_id' => $carsDetailRow->car_id]);

            $mandatoryColumns = ['shippingAmount', 'clearanceAmount', 'towingAmount', 'forkliftAmount', 'latePaymentAmount', 'auctionFineAmount', 'specialCodeAmount'];
            foreach($mandatoryColumns as $billColumn){
                $carsDetailRow->$billColumn = isset($carsDetailRow->$billColumn) ? $carsDetailRow->$billColumn : '0.00';
            }

            $carsDetailRow->otherAmount = number_format($otherAmount, 2, '.', '');
            $carTotalAmount = array_sum( array_column($billDetail, 'debit') ) - $discount;
            $carsDetailRow->totalAmount = number_format($carTotalAmount, 2, '.', '');

            $totalAmount += $carTotalAmount;
            $paidAmount += array_sum( array_column($billDetail, 'credit') );
            $carsDetailRow->bill = $temp_bill_detail;
            $carsDetailRow->car_cost =  number_format($carsDetailRow->car_cost / $exchange_rate, 2, '.', '');
            $carsDetail[$key] = $carsDetailRow;
        }

        $balance = $totalAmount - $paidAmount;

        if($containerDetail){
            $containerDetail->totalAmount = round($totalAmount, 2);
            $containerDetail->paidAmount = round($paidAmount, 2);
            $containerDetail->balance = round($balance, 2);
        }

        $output = array(
            "bankDetail" => $bankDetail,
            "container"  => $containerDetail,
            "cars"      => $carsDetail,
        );
        return response()->json($output, Response::HTTP_OK);

    }

    public function containerExport(Request $request){
        $args                   = $request->all();
        $args['customer_id']    = $this->customer_id;
        $args['limit'] = 'all';
        $cars = CustomerCar::getCustomerContainersExport($args);
        $containers = [];
        foreach ($cars as $dbRow) {
            if(!array_key_exists($dbRow->container_id,$containers)){
                $singlecontainer = [];
                $singlecontainer["container_number"] = $dbRow->container_number;
                $singlecontainer["container_id"]= $dbRow->container_id;
                $singlecontainer["shipping_date"]= $dbRow->shipping_date;
                $singlecontainer["etd"]= $dbRow->etd;
                $singlecontainer["eta"]= $dbRow->eta;
                $singlecontainer["booking_number"]= $dbRow->booking_number;
                $singlecontainer["arrived_port"]= $dbRow->arrived_port;
                $singlecontainer["loaded_date"]= $dbRow->loaded_date;
                $singlecontainer["destination"]= $dbRow->destination;
                $singlecontainer["loaded_status"]= $dbRow->loaded_status;
                $singlecontainer["shipping_status"]= $dbRow->shipping_status;
                $singlecontainer["received_status"]= $dbRow->received_status;
                $singlecontainer["arrived_port_date"]= $dbRow->arrived_port_date;
                $singlecontainer["arrived_store_date"]= $dbRow->arrived_store_date;
                $singlecontainer["total_shipping"]= $dbRow->total_shipping;
                $singlecontainer["pol_name"]=$dbRow->pol_name;
                $singlecontainer["size"]=$dbRow->size;
                $singlecontainer["cars"]=[];
                $singlecontainer["total_cars"]=0;
                $status = '';
                if($dbRow->received_status == 1){
                    $status = 'Arrived Store';
                }
                else if($dbRow->arrived_port == 1){
                    $status = 'Arrived Port';
                }
                else{
                    $status = 'On Way ETA: '. $dbRow->eta;
                }
                $singlecontainer["status"]=$status;
                $singlecontainer["cars_shipping_amount"]=0;
                $containers[$dbRow->container_id] = $singlecontainer;
            }
            $singleCar = [];
            $singleCar['year'] = $dbRow->year;
            $singleCar['carMakerName'] = $dbRow->carMakerName;
            $singleCar['carModelName'] = $dbRow->carModelName;
            $singleCar['lotnumber'] = $dbRow->lotnumber.' '.$dbRow->vin;
            $singleCar['auction_location_name'] = $dbRow->auction_location_name;
            $singleCar['auctionTitle'] = $dbRow->auctionTitle;
            $singleCar["destination"]= $dbRow->destination;
            $singleCar['purchasedate'] = $dbRow->purchasedate;
            $singleCar['paymentDate'] = $dbRow->paymentDate;
            $singleCar['picked_date'] = $dbRow->picked_date;
            $singleCar['delivered_date'] = $dbRow->delivered_date;
            $singleCar['delivered_title'] = $dbRow->delivered_title;
            $singleCar['delivered_car_key'] = $dbRow->delivered_car_key;
            $singleCar['titleDate'] = $dbRow->titleDate;
            $singleCar['follow_car_title_note'] = $dbRow->follow_car_title_note;
            $singleCar['follow_title'] = $dbRow->follow_title;
            array_push($containers[$dbRow->container_id]['cars'],$singleCar);
            $containers[$dbRow->container_id]['total_cars'] = $containers[$dbRow->container_id]['total_cars']+1;
        }

        if($args['customer_id']){
            $containers = json_decode(json_encode($containers,TRUE));
            $output = array(
                "data"      => $containers,
            );
        }
        else{
            $output = array(
                "data"      => [],
            );
        }

        return response()->json($output, Response::HTTP_OK);
    }

    public function uploadTransfer(Request $request){
        $customer_id = $request->customer_id;
        $file = $request->image;
        $fileContent = $request->fileContent;
        $fileName = $request->name;
        $file_type = $request->file_type;
        $extension = $request->extension;
        $total = $request->total;
        $cars = $request->cars;
        $bank_to = $request->bank_to;
        $cu_notes = $request->cu_notes;
        $result = 0;
        $data = [];
        if(!empty($file)){
            $ext = $extension;
            $fileName = $fileName."$ext";
            $destinationPath = "uploads/transfer_payment/$fileName";
            $s3DestinationPath = $destinationPath;
            Storage::disk('local')->put($destinationPath, base64_decode($fileContent));
            $destinationPath = storage_path('app/'.$destinationPath);
            $s3FilePath = Helpers::uploadToS3($destinationPath, $s3DestinationPath, ['file_type' => $file_type]);
            if($s3FilePath){
                unlink($destinationPath);
                $data['file'] = $s3DestinationPath;
                $data['customer_id'] = $customer_id;
                $data['total'] = $total;
                $data['cu_notes'] = $cu_notes;
                $data['bank_to'] = $bank_to;
                $result = CustomerCar::saveOnlinePayment($data, $cars);
            }
        }
        $data = [
            'data'=> $result,
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function deletePaymentRequest(Request $request){
        $request_id = $request->id;
        $deleted = CustomerCar::deletePaymentRequest($request_id);
        if($deleted){
            $data = [
                'success'=> true,
                'message' => 'Deleted successfully'
            ];
        }else{
            $data = [
                'success'=> false,
                'message' => 'Unable to Delete'
            ];
        }

        return response()->json($data, Response::HTTP_OK);
    }


    public function getPaymentDetails(Request $request){
        $payment_id = $request->payment_id;
        $data = [];
        if($payment_id){
            $data = CustomerCar::getPaymentDetails($payment_id);
        }
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }


    public function getPaymentOtherDetails(Request $request){
        $payment_id = $request->payment_id;
        $data = [];
        if($payment_id){
            $data = CustomerCar::getPaymentOtherDetails($payment_id);
        }
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }
    
    

    public function getTransferFee(Request $request){
        $customer_id = $request->customer_id;
        $cars = $request->cars;
        $data = 0;
        $transfer_money = CarService::getTransferMoney();
        if(!empty($cars)){
            $carsArray = $cars;
            $dataArray = CustomerCar::getTransferFee($customer_id, $carsArray);
            if($dataArray){
                $data = 0;
                foreach ($dataArray as $item){
                    $data += isset($item->transfer_fee)?(float)$item->transfer_fee:$transfer_money;
                }
            }else {
                $data = 0;
            }
        }
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function uploadTransferTNew(Request $request){
        $customer_id = $request->input('customer_id');
        $images = $request->input('images', []);
        $total = $request->input('total');
        $cars = $request->input('cars', []);
        $bank_to = $request->input('bank_to');
        $t_type = $request->input('t_type');
        $cu_notes = $request->input('cu_notes');
        $result = 0;
        $data = [];

        $data['file'] = '';
        $data['customer_id'] = $customer_id;
        $data['total'] = $total;
        $data['cu_notes'] = $cu_notes;
        if($t_type == 'exchange') {
            $data['exchange_company_id'] = $bank_to;
            $data['bank_to'] = 0;
        }else {
            $data['bank_to'] = $bank_to;
            $data['exchange_company_id'] = 0;
        }

        $result = CustomerCar::saveOnlinePayment($data, $cars);
        if($result){
            foreach ($images as $image) {
                $fileContent = $image['fileContent'];
                $fileName = $image['name'];
                $file_type = $image['type'];
                $extension = $image['extension'];
                $fileName = $fileName;
                $destinationPath = "uploads/transfer_payment/$fileName";
                $s3DestinationPath = $destinationPath;
                Storage::disk('local')->put($destinationPath, base64_decode($fileContent));
                if(\Maestroerror\HeicToJpg::isHeic(storage_path("app/$destinationPath"))){
                    $newFileName = explode('.', $destinationPath)[0];
                    $file_type = "image/jpeg";
                    $destinationPathJPG = "$newFileName.jpg";
                    \Maestroerror\HeicToJpg::convert(storage_path("app/$destinationPath"))->saveAs(storage_path("app/$destinationPathJPG"));
                    unlink(storage_path("app/$destinationPath"));
                    $destinationPath = $s3DestinationPath = $destinationPathJPG;
                }
                $destinationPath = storage_path('app/'.$destinationPath);
                $s3FilePath = Helpers::uploadToS3($destinationPath, $s3DestinationPath, ['file_type' => $file_type]);
                if ($s3FilePath) {
                    unlink($destinationPath);
                    $imageData []= array(
                        'file_name'         => $s3DestinationPath,
                        'table_id'          => 2,
                        'primary_column'    => $result,
                        'tag'               => 'App Payment'
                    );
                }
            }
            $result = CustomerCar::saveOnlinePaymentFiles($imageData);
        }
        /**$data = [
            'data'=> $result,
        ];
        if($result){
            $data = [
                'success'=> true,
                'message' => 'Deleted successfully'
            ];
        }else{
            $data = [
                'success'=> false,
                'message' => 'Unable to Delete'
            ];
        }
        $responseData = [
            'data' => $data,
        ];**/
        return response()->json($result, Response::HTTP_OK);
    }
    public function changeReceiverName(Request $request) {
        // Get car_id and receiverName from the request
        $car_id = $request->input('car_id');
        $receiverName = $request->input('receiverName');

        // Validation (you can add more validation rules as necessary)
        if (!$car_id || !$receiverName) {
            return response()->json(['message' => 'car_id and receiverName are required.'], Response::HTTP_BAD_REQUEST);
        }
        $data = CustomerCar::changeReceiverName($car_id, $receiverName);


        if($data){
            $data = [
                'success'=> true,
                'message' => 'Updated successfully'
            ];
        }else{
            $data = [
                'success'=> false,
                'message' => 'Failed to update'
            ];
        }
        return response()->json($data, Response::HTTP_OK);

    }

    public function deleteCommonBuyerCars(Request $request){
        $request_id = $request->id;
        $deleted = CustomerCar::deleteCommonBuyerCars($request_id);
        if($deleted){
            $data = [
                'success'=> true,
                'message' => 'Deleted successfully'
            ];
        }else{
            $data = [
                'success'=> false,
                'message' => 'Unable to Delete'
            ];
        }

        return response()->json($data, Response::HTTP_OK);
    }

    public function getAllCommonBuyerCars(Request $request){
        $customer_id = $request->customer_id;
        $data = [];
        if($customer_id){
            $data = CustomerCar::getAllCommonBuyerCarsWithFiles($customer_id);
        }
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function uploadCommonBuyerCars(Request $request){
        $customer_id = $request->input('customer_id');
        $images = $request->input('images', []);
        $cu_notes = $request->input('cu_notes');
        $buyer_id = $request->input('buyer_id');
        $lotnumber = $request->input('lotnumber');
        $result = 0;
        $data = [];
        $data['buyer_id'] = $buyer_id;
        $data['customer_id'] = $customer_id;
        $data['cu_notes'] = $cu_notes;
        $data['lotnumber'] = $lotnumber;

        $result = CustomerCar::saveCommonBuyerCars($data, $cars);
        if($result){
            foreach ($images as $image) {
                $fileContent = $image['fileContent'];
                $fileName = $image['name'];
                $file_type = $image['type'];
                $extension = $image['extension'];
                $ext = $extension;
                $fileName = $fileName."$ext";
                $destinationPath = "uploads/common_buyer_cars/$fileName";
                $s3DestinationPath = $destinationPath;
                Storage::disk('local')->put($destinationPath, base64_decode($fileContent));
                $destinationPath = storage_path('app/'.$destinationPath);
                $s3FilePath = Helpers::uploadToS3($destinationPath, $s3DestinationPath, ['file_type' => $file_type]);
                if ($s3FilePath) {
                    unlink($destinationPath);
                    $imageData []= array(
                        'file_name'         => $s3DestinationPath,
                        'table_id'          => 4,
                        'primary_column'    => $result,
                        'tag'               => 'Common Buyer Cars'
                    );
                }
            }
            $result = CustomerCar::saveCommonBuyerCarsFiles($imageData);
        }
        return response()->json($result, Response::HTTP_OK);
    }

    public function saveArrivedToPort(Request $request){
        $args  = $request->all();
        $data = CustomerCar::saveArrivedToPort($args);

        if($data){
            $data = [
                'success'=> true,
                'message' => 'Updated successfully'
            ];
        }else{
            $data = [
                'success'=> false,
                'message' => 'Failed to update'
            ];
        }
        return response()->json($data, Response::HTTP_OK);
    }

    public function getServicesDetails(Request $request){
        $data = [];
        $data = CustomerCar::getServicesDetails();
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function uploadTransferOther(Request $request){
        $customer_id = $request->input('customer_id');
        $images = $request->input('images', []);
        $total = $request->input('total');
        $details = $request->input('details', []);
        $bank_to = $request->input('bank_to');
        $t_type = $request->input('t_type');
        $cu_notes = $request->input('cu_notes');
        $services = $request->input('services', []);
        $result = 0;
        $data = [];

        $data['file'] = '';
        $data['customer_id'] = $customer_id;
        $data['total'] = $total;
        $data['cu_notes'] = $cu_notes;
        $data['bank_to'] = $bank_to;

        $result = CustomerCar::saveOnlinePaymentOther($data, $services);
        if($result){
            foreach ($images as $image) {
                $fileContent = $image['fileContent'];
                $fileName = $image['name'];
                $file_type = $image['type'];
                $extension = $image['extension'];
                $fileName = $fileName;
                $destinationPath = "uploads/transfer_payment/$fileName";
                $s3DestinationPath = $destinationPath;
                Storage::disk('local')->put($destinationPath, base64_decode($fileContent));
                if(\Maestroerror\HeicToJpg::isHeic(storage_path("app/$destinationPath"))){
                    $newFileName = explode('.', $destinationPath)[0];
                    $file_type = "image/jpeg";
                    $destinationPathJPG = "$newFileName.jpg";
                    \Maestroerror\HeicToJpg::convert(storage_path("app/$destinationPath"))->saveAs(storage_path("app/$destinationPathJPG"));
                    unlink(storage_path("app/$destinationPath"));
                    $destinationPath = $s3DestinationPath = $destinationPathJPG;
                }
                $destinationPath = storage_path('app/'.$destinationPath);
                $s3FilePath = Helpers::uploadToS3($destinationPath, $s3DestinationPath, ['file_type' => $file_type]);
                if ($s3FilePath) {
                    unlink($destinationPath);
                    $imageData []= array(
                        'file_name'         => $s3DestinationPath,
                        'table_id'          => 2,
                        'primary_column'    => $result,
                        'tag'               => 'App Payment'
                    );
                }
            }
            $result = CustomerCar::saveOnlinePaymentFiles($imageData);
        }
        return response()->json($result, Response::HTTP_OK);
    }

    public function getAllOnlinePaymentOther(Request $request){
        $customer_id = $request->customer_id;
        $data = [];
        if($customer_id){
            $data = CustomerCar::getAllOnlinePaymentOther($customer_id);
        }
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }


    public function sendPaidByCustomersCar(Request $request){
        $customer_id = $request->input('customer_id');
        $images = $request->input('images', []);
        $car_id = $request->input('car_id');
        $cu_notes = $request->input('cu_notes');
        $data = [];
        $result = 0;
        if($car_id && $customer_id){
            $data = CustomerCar::savePaidByCustomersCar($car_id, $cu_notes, $customer_id);
            if($data && $images != "[]" && $images != []){
                foreach ($images as $image) {
                    $fileContent = $image['fileContent'];
                    $fileName = $image['name'];
                    $file_type = $image['type'];
                    $extension = $image['extension'];
                    $fileName = $fileName;
                    $destinationPath = "uploads/transfer_payment/$fileName";
                    $s3DestinationPath = $destinationPath;
                    Storage::disk('local')->put($destinationPath, base64_decode($fileContent));
                    if(\Maestroerror\HeicToJpg::isHeic(storage_path("app/$destinationPath"))){
                        $newFileName = explode('.', $destinationPath)[0];
                        $file_type = "image/jpeg";
                        $destinationPathJPG = "$newFileName.jpg";
                        \Maestroerror\HeicToJpg::convert(storage_path("app/$destinationPath"))->saveAs(storage_path("app/$destinationPathJPG"));
                        unlink(storage_path("app/$destinationPath"));
                        $destinationPath = $s3DestinationPath = $destinationPathJPG;
                    }
                    $destinationPath = storage_path('app/'.$destinationPath);
                    $s3FilePath = Helpers::uploadToS3($destinationPath, $s3DestinationPath, ['file_type' => $file_type]);
                    if ($s3FilePath) {
                        unlink($destinationPath);
                        $imageData []= array(
                            'file_name'         => $s3DestinationPath,
                            'table_id'          => 5,
                            'primary_column'    => $data,
                            'tag'               => 'Paid By Customer'
                        );
                    }
                }
                $result = CustomerCar::saveOnlinePaymentFiles($imageData);
            }
        }
        if($data){
            $data = [
                'success'=> true,
                'message' => 'Updated successfully'
            ];
        }else{
            $data = [
                'success'=> false,
                'message' => 'Failed to update'
            ];
        }
        return response()->json($data, Response::HTTP_OK);
    }

    public function getAllPaidByCustomersCar(Request $request){
        $customer_id = $request->customer_id;
        $data = [];
        if($customer_id){
            $data = CustomerCar::getAllPaidByCustomersCar($customer_id);
        }
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function deletePaidByCustomersCar(Request $request){
        $request_id = $request->id;
        $deleted = CustomerCar::deletePaidByCustomersCar($request_id);
        if($deleted){
            $data = [
                'success'=> true,
                'message' => 'Deleted successfully'
            ];
        }else{
            $data = [
                'success'=> false,
                'message' => 'Unable to Delete'
            ];
        }

        return response()->json($data, Response::HTTP_OK);
    }

}
