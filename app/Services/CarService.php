<?php

namespace App\Services;

use App\Models\CarAccounting;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use DateTime;
use App\Libraries\Constants;
use App\Libraries\Helpers;
use Faker\Extension\Helper;

class CarService
{
    public static function storageFine($car_id)
    {
        $carStorageTransport = Store::carStorageTransport($car_id);
        if($carStorageTransport['skip_storage_after_payment']){
            return ['fine' => 0];
        }

        $contract = self::getCarContract($car_id);
        $storage_start_days = $contract->storage_start_days > 0?$contract->storage_start_days:0;
        $storage_start_days_remaining = $storage_start_days;

        $carStorageWarehouse = Store::carStorageWarehouse($car_id);
        $warehouseRules = Store::warehouseRules();

        $warehouse = [];
        foreach ($warehouseRules as $key => $row) {
            $warehouse[$row->warehouse_id] = $row;
        }

        $final_created_date = '';
        if ($carStorageTransport) {
            $thereFine = 1;

            $received_date = $carStorageTransport['received_date'];
            if ($carStorageTransport['receive_car_warehouse_id']) {
                $received_date = ($carStorageTransport['receive_car_create_date']) ? $carStorageTransport['receive_car_create_date'] : $received_date;
                $carStorageTransport['warehouse_id'] = $carStorageTransport['receive_car_warehouse_id'];
            }

            $enter_date_transport = new DateTime($received_date);
            $begin = new DateTime($received_date);
            $sell_begin_date = new DateTime($carStorageTransport['sell_create_date']);
            $calculateStorage['enter_date_transport'] = $enter_date_transport->format('d-m-Y');
            
            if (!empty($carStorageTransport['warehouse_id']) && !empty($warehouse[$carStorageTransport['warehouse_id']]) && !$storage_start_days) {
                $storage_start_days = $warehouse[$carStorageTransport['warehouse_id']]->allow_day;
            }

            if($storage_start_days > 0){
                $received_date = date('Y-m-d', strtotime($received_date . ' + ' . $storage_start_days . ' days'));
            }

            $begin = new DateTime($received_date);
            // get final invoice data to get new begin date for storage
            if (!empty($carStorageTransport['final_created_date'])) {
                $final_created_date = new DateTime($carStorageTransport['final_created_date']);
                if (!empty($carStorageTransport['deliver_create_date'])) {

                    $deliver_create_date = new DateTime($carStorageTransport['deliver_create_date']);
                    // if ($final_created_date > $begin &&  $final_created_date < $deliver_create_date) {
                    //     $begin = new DateTime($final_created_date->format('d-m-Y'));
                    //     $begin = $begin->modify('+1 day');
                    // }
                    if ($final_created_date > $deliver_create_date) {
                        $thereFine = 0;
                    }
                }
                if ($final_created_date > $begin &&  $final_created_date) {
                    $begin = new DateTime($final_created_date->format('d-m-Y'));
                    $begin = $begin->modify('+1 day');
                }
            }

            if ($carStorageTransport['recovery_date']) {
                $end = new DateTime($carStorageTransport['recovery_date']);
            } elseif ($carStorageTransport['deliver_create_date']) {
                $end = new DateTime($carStorageTransport['deliver_create_date']);
                $end = new DateTime($end->format('d-m-Y'));
                $end = $carStorageTransport['for_sell'] ? $sell_begin_date : $end; // sell car storage is separate
                $calculateStorage['deliver_create_date'] = $end->format('d-m-Y');
                $calculateStorage['deliver'] = 'تم التسليم';
            } else {
                $end = new DateTime(date("Y-m-d"));
                $end = $carStorageTransport['for_sell'] ? $sell_begin_date : $end; // sell car storage is separate
            }

            $calculateStorage['start_transport'] = $begin->format('d-m-Y');
            $calculateStorage['end_transport'] = $end->format('d-m-Y');
            // =======================================
            if ($end->format('Y-m-d') < $begin->format('Y-m-d')) {
                $fine = "<i class='fa fa-ban' aria-hidden='true' style='color: red;'></i>";
            } else {
                $fine = 0;
            }
            $enter_date_transport = new DateTime($calculateStorage['enter_date_transport']);
            $enter_date_transport_stop = $enter_date_transport->format('Y-m-d');

            if ($final_created_date > $end || $enter_date_transport_stop <= $end->format('Y-m-d') ) {
                $fine = 0;
            }

            // =======================================
            $end = $end->modify('+1 day');
            $difference = $end->diff($begin);
            $calculateStorage['days_transport'] = $difference->days;

            if ($end->format('Y-m-d') < $begin->format('Y-m-d')) {
                $calculateStorage['days_transport_allow'] = 1;
            } else {
                $calculateStorage['days_transport_allow'] = 0;
            }

            if ($carStorageTransport['receive_car_warehouse_name']) {
                $calculateStorage['warehouse_transport'] = $carStorageTransport['receive_car_warehouse_name'];
            } else {
                $calculateStorage['warehouse_transport'] = $carStorageTransport['warehouse_name'];
            }

            $differenceInterval = $begin->diff($end);
            $storage_start_days_remaining = ($storage_start_days && $differenceInterval->invert) ? $differenceInterval->days : 0;
            $totalDays = $differenceInterval->invert ? 0 : $differenceInterval->days;
            $finePerDay = $warehouse[$carStorageTransport['warehouse_id']]->per_day;
            if ($begin->format("Y-m-d") <= '2020-04-08') {
                if ($carStorageTransport['warehouse_id'] == 9) {
                    $finePerDay = 20;
                } else {
                    $finePerDay = 10;
                }
            }
            $fine = $totalDays * $finePerDay;

            if ($thereFine == 0) {
                $fine = 0;
            }
            $carStorageTransport['fine'] = $fine;
            $calculateStorage['fine_transport'] = $fine;
        }


        // ==================================================================
        // $carStorageWarehouse['fine_wearhouse_fine_total'] = 0;
        foreach ($carStorageWarehouse as $key => $value) {
            if (empty($value)) {
                continue;
            }
            $calculateStorage['warehouse_warehouse'] = [$key => $value['warehouse_name']];

            //calculate number of days car stayed in newjoum aljazeera store
            if ($key == 0) {

                // date of car arrived to transport_request table
                if ($value['received_create_date']) {
                    $received_create_date = $value['received_create_date'];
                    $received_create_date = date("Y-m-d", strtotime($received_create_date));
                }
                // car arrived to warehouse_transport table
                if ($value['warehouse_transport_recovery_date']) {
                    $recovery_date = $value['warehouse_transport_recovery_date'];
                    $recovery_date = date("Y-m-d", strtotime($recovery_date));
                }

                $datetime1 = new DateTime($received_create_date); //date car Arrived to first warehouse
                $datetime2 = new DateTime($recovery_date); // date car arrived to second warehouse
                $difference = $datetime1->diff($datetime2); // get days count  car staed in first warehouse
                $days = $difference->days + 1;
                if ($days > $warehouse[$value['from_destination_warehouse_id']]->allow_day) {
                    $warehouse[$value['warehouse_id']]->allow_day = max($storage_start_days_remaining, 0);;
                }
            }
            //end calculate number of days car stayed in newjoum aljazeera store
            if ($value['warehouse_id']) {
                // if car go to nejoumaljazeera allow days will be 0
                if ($value['to_destination_warehouse_id'] == 9) {
                    $warehouse[$value['warehouse_id']]->allow_day = 0;
                }

                // if cars was moved more than 1 time allow days will be Zero
                $NotFirst = 0;
                if ($value['carsWarehouseTransportNum'] > 1 && $key > 0) {
                    $warehouse[$value['warehouse_id']]->allow_day = max($storage_start_days_remaining, 0);;
                    $NotFirst = 1;
                } else {
                    $NotFirst = 0;
                }
            }


            $received_date = $value['received_date']; // car arrived to warehouse in warehouse_transport table
            $enter_date_warehouse = new DateTime($received_date);

            $calculateStorage['enter_date_warehouse'][$key] = $enter_date_warehouse->format('d-m-Y');
            // add allow days to recived date
            $received_date = date('Y-m-d', strtotime($received_date . ' + ' . $warehouse[$value['warehouse_id']]->allow_day . ' days'));
            $value['new_received_date'] = $received_date;
            $begin = new DateTime($received_date);

            // if car has fine in first warehouse add 1 day to begining
            // if ($carStorageTransport['fine'] != 0 || $NotFirst) {
            $begin = $begin->modify('+1 day');
            // }

            // get final invoice data to get new begin date for storage
            if ($carStorageTransport['final_created_date']) {
                $final_created_date = new DateTime($carStorageTransport['final_created_date']);
            }

            if ($final_created_date > $begin) {
                if(!$carStorageTransport['for_sell'] || $begin < $sell_begin_date){
                    $begin = new DateTime($final_created_date->format('d-m-Y'));
                    $beginAdd1Day = true;
                }
                if($beginAdd1Day){
                    $begin = $begin->modify('+1 day');
                }
            }

            if($carStorageTransport['for_sell'] && $carStorageTransport['sold_date'] > '' && $begin->format('Y-m-d') > $carStorageTransport['sold_date']){
                unset($calculateStorage['warehouse_warehouse'][$key]);
                unset($calculateStorage['enter_date_warehouse'][$key]);
                continue;
            }

            if (isset($carStorageWarehouse[$key + 1]['warehouse_transport_recovery_date'])) { // if car movied to another warehouse again
                $end = new DateTime($carStorageWarehouse[$key + 1]['warehouse_transport_recovery_date']);
            } elseif ($value['deliver_create_date']) { // else if car deliverd and dont move again to another warehouse get deliverd to customer date
                $end = new DateTime($value['deliver_create_date']);
                $end = new DateTime($end->format('d-m-Y'));
                $calculateStorage['deliver_create_date_warehouse'][$key] = $end->format('d-m-Y');
            } else {
                $end = new DateTime(date("Y-m-d")); // today date
                $end = $carStorageTransport['for_sell'] ? $sell_begin_date : $end; // sell car storage is separate
            }

            $calculateStorage['start_warehouse'][$key] = $begin->format('d-m-Y');
            $calculateStorage['end_warehouse'][$key] = $end->format('d-m-Y');
            $end = $end->modify('+1 day');

            $difference = $end->diff($begin);
            $calculateStorage['days_warehouse'][$key] = $difference->days;

            if ($end->format('Y-m-d') < $begin->format('Y-m-d')) {
                $calculateStorage['days_warehouse_allow'][$key] = 1;
            } else {
                $calculateStorage['days_warehouse_allow'][$key] = 0;
            }

            $differenceInterval = $begin->diff($end);
            $totalDays = $differenceInterval->invert ? 0 : $differenceInterval->days;
            $storage_start_days_remaining -= $totalDays;
            $finePerDay = $warehouse[$value['warehouse_id']]->per_day;
            if ($begin->format("Y-m-d") <= '2020-04-08') {
                if ($value['warehouse_id'] == 9) {
                    $finePerDay = 20;
                } else {
                    $finePerDay = 10;
                }
            }
            $value['fine'] = $fine = $totalDays * $finePerDay;
            $calculateStorage['fine_wearhouse'][$key] = $fine;
            $carStorageWarehouse['fine_wearhouse_fine_total'] += $fine;
        }

        if (is_string($carStorageTransport['fine'])) {
            $calculateStorage['fine'] = $carStorageTransport['fine'];
        } else {
            $calculateStorage['fine'] = $carStorageWarehouse['fine_wearhouse_fine_total'] + $carStorageTransport['fine'];
        }

        return $calculateStorage;
    }

    public static function getTransferMoney(){
        $query = DB::Table('general_settings')
            ->select(['transfer_money']);

        return $query->first()->transfer_money;
    }

    public static function carMakeTransaction($args){
        $car_id = $args['car_id'];
        $car_step = $args['car_step'];
        if (empty($car_id) || empty($car_step)) {
            return false;
        }

        $query = DB::Table('accounttransaction')
            ->select(['Journal_id'])
            ->where('deleted', '0')
            ->where('car_id', $car_id)
            ->where('car_step', $car_step);

        return $query->first();
    }
    public static function getAuctionLocationFines($cars)
    {
        $auction_location_ids = array_unique(array_column($cars, 'auction_location_id'));
        $query = DB::Table('auction_location_fines')
            ->join('auction_fines', 'auction_location_fines.auction_fines_id', '=', 'auction_fines.ID')
            ->select('*');

        if (!empty($auction_location_ids)) {
            $query->whereIn('auction_location_id', $auction_location_ids);
        }

        $auction_location_fines_db = $query->get()->toArray();
        $auction_location_fines = [];
        foreach ($auction_location_fines_db as $key => $row) {
            $auction_location_fines[$row->auction_location_id] = $row;
        }
        $newCars = [];
        foreach ($cars as $dbRow) {
            $number = 0;
            $allowed_days = 0;
            $late_payment_fine = 0;
            $purchaseDateTimeStamp = strtotime($dbRow->purchasedate);

            $auction_fines = [];
            if (!empty($auction_location_fines[$row->auction_location_id])) {
                $auction_fines = $auction_location_fines[$row->auction_location_id];
            }
            //dd($auction_fines);

            if($auction_fines) {
                //calucalte total fine cost
                $allowed_daysToCost = $auction_fines->allowed_days;
                for ($i = 1; $i < $allowed_daysToCost; $i++) {
                    $dateAfterPurchaseDate = strtotime("+$i day", $purchaseDateTimeStamp);
                    $dayOfWeek = date("l", $dateAfterPurchaseDate);
                    if ($auction_fines->$dayOfWeek == 0) {
                        $number += 1;
                        $allowed_daysToCost += 1;
                    }
                }
                $late_payment_fine = $auction_fines->late_payment_fine;
                $allowed_days = $auction_fines->allowed_days;
            }

            //gest last date to pay
            $lastPayDay = $allowed_days - 1;
            $lastDateToPay = strtotime("+$lastPayDay day", strtotime($dbRow->purchasedate));
            $lastDateToPay = date('Y-m-d', $lastDateToPay);
            //extra date
            if ($number) {
                $extraDaysDate = strtotime("+$number day", strtotime($lastDateToPay));
                $extraDaysDate = date('Y-m-d', $extraDaysDate);
            } else {
                $extraDaysDate = $lastDateToPay;
            }
            //get remaining days bettwen to dates
            //fine start
            $fineStartDate = strtotime("+1 day", strtotime($extraDaysDate));
            $fineStartDate = date('Y-m-d', $fineStartDate);

            $now = time(); // or your date as well
            $fineStartDateStamp = strtotime($fineStartDate);
            $datediff =  $now - $fineStartDateStamp;

            $datediff = round($datediff / (60 * 60 * 24));
            $remainingDate = $fineStartDateStamp - $now;
            $remainingDate = round($remainingDate / (60 * 60 * 24));

            $totalFinePreDay = $allowed_days + $datediff + 1;
            if ($remainingDate >= 0) {
                $late_payment_fine = '0.00';
            }
            //calcualte fine start
            $now = time();
            $datestartfine = $fineStartDate - $now;
            $datestartfine = round($datestartfine / (60 * 60 * 24));
            //calculate total fine cost
            $totalDays =  $now - strtotime($dbRow->purchasedate);
            $totalDays = round($totalDays / (60 * 60 * 24));
            $totalDaysToFine =  $now - strtotime($extraDaysDate);
            $totalDaysToFine = round($totalDaysToFine / (60 * 60 * 24));
            $totalDaysToFine = $totalDaysToFine + $allowed_days;

            //calculate auction fines
            $fineTotalCost = 0;
            if ($auction_fines) {
                for ($counter = 1; $counter < $totalFinePreDay; $counter++) {
                    $dayKey = "day$counter";
                    $fineTotalCost += $auction_fines->$dayKey;
                    if ($counter > 10) {
                        $fineTotalCost += 30;
                    }
                }
            }
            if ($late_payment_fine == '') {
                $late_payment_fine = '0.00';
            }
            if ($fineTotalCost == '') {
                $fineTotalCost = '0.00';
            }
            $newCars[$dbRow->id] = [
                'lastDateToPay' => $lastDateToPay,
                'daysOff' => $number,
                'extraDate' => $extraDaysDate,
                'remainingDays' => $remainingDate,
                'startStorage' => $fineStartDate,
                'fineTotalCost'=>$fineTotalCost,
                'late_payment_fine'=>$late_payment_fine
            ];
        }
        return $newCars;
    }
    public static function getallTransferedAmount($id){
        $query = DB::Table('journal')
            ->join('accounttransaction', 'accounttransaction.journal_id', '=', 'journal.ID')
            ->select(DB::Raw('SUM(accounttransaction.Debit) as TotalDebit'))
            ->where('accounttransaction.car_step', 0)
            ->where('accounttransaction.typePay', 1)
            ->where('accounttransaction.type_transaction', 1)
            ->where('accounttransaction.bill_id', 0)
            ->where('journal.deleted', 0)
            ->where('journal.car_id', $id)
            ->where('accounttransaction.deleted', 0);
        return ($query->first()->TotalDebit)?($query->first()->TotalDebit):0;
    }

    // New Cars
    public static function getQueryNewCars($args)
    {
        $customer_id        = $args['customer_id'];
        $paid               = $args['paid'];
        $unpaid             = $args['unpaid'];
        $paid_bycustomer    = $args['paid_bycustomer'];
        $search             = $args['search'];
        $query = DB::Table('car')
        ->leftJoin('external_car', 'car.id','=','external_car.car_id')
        ->leftJoin('car_make', 'car.id_car_make', '=', 'car_make.id_car_make')
        ->leftJoin('buyer', 'car.buyer_id', '=', 'buyer.buyer_id')
        ->leftJoin('auction', 'car.auction_id', '=', 'auction.id')
        ->leftJoin('auction_location', 'car.auction_location_id', '=', 'auction_location.auction_location_id')
        ->leftJoin('auction_location as external_auction_location', 'external_car.auction_location_id','=','external_auction_location.auction_location_id')
        ->leftJoin('auction as external_auction', 'external_auction_location.auction_id','=','external_auction.id')
        ->leftJoin('region', 'auction_location.region_id', '=', 'region.region_id')
        ->leftJoin('region as external_car_region', 'external_car.region_id','=','external_car_region.region_id')
        ->leftJoin('countries', 'countries.id', '=', 'region.country_id')
        ->leftJoin('port', 'car.destination', '=', 'port.port_id')
        ->leftJoin('bill_details', 'bill_details.car_id', '=', 'car.id')
        ->leftJoin('car_model', 'car.id_car_model', '=', 'car_model.id_car_model')
        ->leftJoin('customer_appnotes', 'customer_appnotes.car_id', '=', 'car.id')
        ->leftJoin('vehicle_type', 'car.id_vehicle_type', '=', 'vehicle_type.id_vehicle_type')
        ->leftJoin('customer', 'car.customer_id', '=', 'customer.customer_id')
        ->leftJoin('color', 'car.color', '=', 'color.color_id')
        ->leftJoin('change_destination_requests', 'car.id', '=', 'change_destination_requests.car_id')
        ->leftJoin('port as changed_port', 'change_destination_requests.new_port_id', '=', 'changed_port.port_id')
        ->where([
            ['car.cancellation', '=', '0'],
            ['car.status', '<>', '4'],
            ['towingstatus', '=', '0'],
            ['deliver_customer', '<>', '1'],
            ['car.deleted', '0'],
            ['car.customer_id', $customer_id]
        ])->when ($search , function ($query) use($search){
            $query->where(function($query)  use($search){
                $query->where('car.vin', $search)
                    ->orWhere('car.lotnumber', $search);
            });
        })
        ->when($paid && $unpaid && $paid_bycustomer,function ($query){
            return $query->where('car.car_payment_to_cashier', '3')
                ->orWhere('car.car_payment_to_cashier', '0')
                ->orWhere('car.car_payment_to_cashier', '1');
        })
        ->when($paid && $unpaid,function ($query){
            return $query->where('car.car_payment_to_cashier', '0')
                ->orWhere('car.car_payment_to_cashier', '1');
        })
        ->when($paid && $paid_bycustomer,function ($query){
            return $query->where('car.car_payment_to_cashier', '3')
                ->orWhere('car.car_payment_to_cashier', '1');
        })
        ->when($unpaid && $paid_bycustomer,function ($query){
            return $query->where('car.car_payment_to_cashier', '3')
                ->orWhere('car.car_payment_to_cashier', '0');
        })
        ->when($paid,function ($query){
            return $query->where('car.car_payment_to_cashier', '1');
        })
        ->when($paid_bycustomer,function ($query){
            return $query->where('car.car_payment_to_cashier', '3');
        })
        ->when($unpaid,function ($query){
            return $query->where('car.car_payment_to_cashier', '0');
        })
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

        if (!empty($args['region'])) {
            $query->where(function($query)  use($args){
                $query->where('region.region_id', $args['region']);
                $query->orWhere('external_car_region.region_id', $args['region']);
            });
        }

        return $query;
    }

    public static function getNewCars($args)
    {
        $limit          = !empty($args['limit']) ? $args['limit'] : 10;
        $page           = !empty($args['page']) ? $args['page'] : 0;
        $order = !empty($args['order']) ? $args['order'] : '';
        $customer_id    = $args['customer_id'];

        if (empty($customer_id)) {
            return [];
        }

        $select = "region.short_name, car.* , car_make.name AS carMakerName , car_model.name AS carModelName, vehicle_type.name AS vehicleName,
        auction.us_dollar_rate,auction.candian_dollar_rate, auction.title AS aTitle, IF(external_car.car_id, external_auction.title, auction.title) auctionTitle,
        IF(car.external_car='0', auction_location.auction_location_name, external_auction_location.auction_location_name) auction_location_name,IF(port.tag is not null,'".Constants::JEBEL_ALI_PORT_NAME."',port.port_name) port_name, customer.full_name AS CustomerName,
        countries.id as country,bill_details.*,CAST(bill_details.create_date AS DATE) as paymentDate, buyer.buyer_number,
        color.color_code,color.color_name, IF(car.external_car='0', region.region_name, external_car_region.region_name) as region, customer_appnotes.notes as special_notes,
        car.loading_status as car_loading_status,car.shipping_status as car_shipping_status, port.port_id as port_id, change_destination_requests.id change_port_request_id,
        change_destination_requests.create_date change_port_request_create_date, changed_port.port_name changed_port_name";

        $query = self::getQueryNewCars($args)
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

    public static function getNewCarsCount($args)
    {
        $query = self::getQueryNewCars($args);
        $query->select(DB::raw('COUNT(car.id) as totalRecords'));
        return $query->first()->totalRecords;
    }

    // End New Cars

    // Towing Cars
    public static function getQueryTowedCars($args) {
        $customer_id = $args['customer_id'];
        $search      = $args['search'];
        $query = DB::Table('car')
            ->leftJoin('external_car', 'car.id','=','external_car.car_id')
            ->leftJoin('car_make', 'car.id_car_make','=','car_make.id_car_make')
            ->leftJoin('buyer', 'car.buyer_id', '=', 'buyer.buyer_id')
            ->leftJoin('auction', 'car.auction_id','=','auction.id')
            ->leftJoin('auction_location', 'car.auction_location_id','=','auction_location.auction_location_id')
            ->leftJoin('auction_location as external_auction_location', 'external_car.auction_location_id','=','external_auction_location.auction_location_id')
            ->leftJoin('auction as external_auction', 'external_auction_location.auction_id','=','external_auction.id')
            ->leftJoin('customer_appnotes', 'customer_appnotes.car_id','=','car.id')
            ->leftJoin('car_carrier', 'car_carrier.car_id','=','car.id')
            ->leftJoin('region', 'auction_location.region_id','=','region.region_id')
            ->leftJoin('region as external_car_region', 'external_car.region_id','=','external_car_region.region_id')
            ->leftJoin('countries', 'countries.id','=','region.country_id')
            ->leftJoin('port', 'car.destination','=','port.port_id')
            ->leftJoin('car_model', 'car.id_car_model','=','car_model.id_car_model')
            ->leftJoin('vehicle_type', 'car.id_vehicle_type','=','vehicle_type.id_vehicle_type')
            ->leftJoin('towing_status', 'towing_status.car_id','=','car.id')
            ->leftJoin('color', 'car.color','=','color.color_id')
            ->leftJoin('bill_details', 'bill_details.car_id','=','car.id')
            ->leftJoin('customer', 'car.customer_id','=','customer.customer_id')
            ->where([
                ['car.deleted','=', '0'],
                ['towingstatus','=', '1'],
                ['arrivedstatus','=', '0'],
                ['car.status','!=', '4'],
                ['status_of_issue','=', '0'],
                ['customer.customer_id','=', $customer_id]
            ])->when ($search , function ($query) use($search){
                $query->where(function($query)  use($search){
                    $query->where('car.vin', $search)
                        ->orWhere('car.lotnumber', $search);
                });
            });

        if (!empty($args['region'])) {
            $query->where(function($query)  use($args){
                $query->where('region.region_id', $args['region']);
                $query->orWhere('external_car_region.region_id', $args['region']);
            });
        }

        return $query;
    }
    public static function getTowedCars($args)
    {
        $limit          = !empty($args['limit']) ? $args['limit'] : 10;
        $page           = !empty($args['page']) ? $args['page'] : 0;
        $order          = !empty($args['order']) ? $args['order'] : '';
        $customer_id    = $args['customer_id'];
        if (empty($customer_id)) {
            return [];
        }

        $select = "region.short_name, car.id, car.carnotes, car.car_title as deliveredTitle, car.car_key as deliveredKey, car.lotnumber,car.vin,car.year, car.purchasedate,car.auction_location_id,car.photo , car_make.name AS carMakerName , car_model.name AS carModelName, vehicle_type.name AS vehicleName,
        IF(external_car.car_id, external_auction.title, auction.title) auctionTitle, IF(car.external_car='0', auction_location.auction_location_name, external_auction_location.auction_location_name) auction_location_name,IF(car.external_car='0', region.region_name, external_car_region.region_name) as region,
        IF(port.tag is not null,'".Constants::JEBEL_ALI_PORT_NAME."',port.port_name) port_name,color.color_name,CAST(bill_details.create_date AS DATE) as paymentDate,customer_appnotes.notes as specialNotes,
        towing_status.picked_date,car_carrier.ETD, buyer.buyer_number,towing_status.picked_car_title_note";

        $query = self::getQueryTowedCars($args)
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

    public static function getTowedCarsCount($args)
    {
        $query = self::getQueryTowedCars($args);
        $query->select(DB::raw('COUNT(car.id) as totalRecords'));
        return $query->first()->totalRecords;
    }

    // End Towing Cars

    // On Warehouse Cars
    public static function getQueryWarehouseCars($args) {
        $customer_id = $args['customer_id'];
        $search      = $args['search'];
        $query = DB::Table('car')
            ->leftJoin('external_car', 'car.id','=','external_car.car_id')
            ->leftJoin('car_make', 'car.id_car_make','=','car_make.id_car_make')
            ->leftJoin('buyer', 'car.buyer_id', '=', 'buyer.buyer_id')
            ->leftJoin('auction', 'car.auction_id','=','auction.id')
            ->leftJoin('auction_location', 'car.auction_location_id','=','auction_location.auction_location_id')
            ->leftJoin('auction_location as external_auction_location', 'external_car.auction_location_id','=','external_auction_location.auction_location_id')
            ->leftJoin('auction as external_auction', 'external_auction_location.auction_id','=','external_auction.id')
            ->leftJoin('customer_appnotes', 'customer_appnotes.car_id','=','car.id')
            ->leftJoin('towing_status', 'towing_status.car_id','=','car.id')
            ->leftJoin('region', 'auction_location.region_id','=','region.region_id')
            ->leftJoin('region as external_car_region', 'external_car.region_id','=','external_car_region.region_id')
            ->leftJoin('countries', 'countries.id','=','region.country_id')
            ->leftJoin('port', 'car.destination','=','port.port_id')
            ->leftJoin('car_model', 'car.id_car_model','=','car_model.id_car_model')
            ->leftJoin('arrived_car', 'arrived_car.car_id','=','car.id')
            ->leftJoin('vehicle_type', 'car.id_vehicle_type','=','vehicle_type.id_vehicle_type')
            ->leftJoin('color', 'car.color','=','color.color_id')
            ->leftJoin('bill_details', 'bill_details.car_id','=','car.id')
            ->leftJoin('customer', 'car.customer_id','=','customer.customer_id')
            ->leftJoin('car_total_cost', 'car.id','=','car_total_cost.car_id')
            ->leftJoin('cars_title_status', 'cars_title_status.cars_title_status_id', '=', DB::raw('(select max(cars_title_status_id) as cars_title_status_id from cars_title_status as cts4 WHERE `cts4`.`car_id` = car.id)'))
            ->where([
                ['car.deleted','=', '0'],
                ['car.status','!=', '4'],
                ['arrivedstatus','=', '1'],
                ['status_of_issue','=', '0'],
                ['loading_status','=','0'],
                ['customer.customer_id','=', $customer_id]
            ])->when ($search , function ($query) use($search){
                $query->where(function($query)  use($search){
                    return $query->where('car.vin', $search)
                        ->orWhere('car.lotnumber', $search);
                });
            })
            ->whereNull("car_total_cost.car_id");

        if (!empty($args['region'])) {
            $query->where(function($query)  use($args){
                $query->where('region.region_id', $args['region']);
                $query->orWhere('external_car_region.region_id', $args['region']);
            });
        }

        return $query;
    }
    public static function getWarehouseCars($args)
    {
        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;
        $customer_id = $args['customer_id'];
        if (empty($customer_id)) {
            return [];
        }

        $select = "arrived_car.car_id as aliasID,
        (SELECT cars_title_status.follow_title
            from cars_title_status
            where cars_title_status.car_id = aliasID
            order by cars_title_status.create_date DESC limit 1) as follow_title,
            region.short_name, car.* , car_make.name AS carMakerName , car_model.name AS carModelName, vehicle_type.name AS vehicleName,
          IF(external_car.car_id, external_auction.title, auction.title) auctionTitle, IF(car.external_car='0', auction_location.auction_location_name, external_auction_location.auction_location_name) auction_location_name, IF(port.tag is not null,'".Constants::JEBEL_ALI_PORT_NAME."',port.port_name) port_name,
          customer.full_name AS CustomerName,countries.id as country,arrived_car.*,color.color_name,
          color.color_code,towing_status.picked_date,towing_status.picked_car_title_note, IF(car.external_car='0', region.region_name, external_car_region.region_name) as region,
          CAST(bill_details.create_date AS DATE) as paymentDate, buyer.buyer_number,
          cars_title_status.follow_car_title_note,CAST(cars_title_status.create_date AS DATE) as titleDate
          ,customer_appnotes.notes as special_notes";

        $query = self::getQueryWarehouseCars($args)
            ->groupBy('car.id');
        if($limit != 'all'){
            $query->skip($page * $limit)->take($limit);
        }
        $query->select(DB::raw($select));
        //echo Helpers::getRawSql($query);die;
        return $query->get()->toArray();
    }

    public static function getWarehouseCarsCount($args)
    {
        $query = self::getQueryWarehouseCars($args);
        $query->select(DB::raw('COUNT(car.id) as totalRecords'));
        return $query->first()->totalRecords;
    }
    // End Warehouse Cars

    public static function getQueryPortCars($args) {
        $customer_id = $args['customer_id'];
        $search      = $args['search'];
        $query = DB::Table('car')
            ->leftJoin('external_car', 'car.id','=','external_car.car_id')
            ->leftJoin('car_make', 'car.id_car_make','=','car_make.id_car_make')
            ->leftJoin('buyer', 'car.buyer_id', '=', 'buyer.buyer_id')
            ->leftJoin('auction', 'car.auction_id','=','auction.id')
            ->leftJoin('auction_location', 'car.auction_location_id','=','auction_location.auction_location_id')
            ->leftJoin('auction_location as external_auction_location', 'external_car.auction_location_id','=','external_auction_location.auction_location_id')
            ->leftJoin('auction as external_auction', 'external_auction_location.auction_id','=','external_auction.id')
            ->leftJoin('region', 'auction_location.region_id','=','region.region_id')
            ->leftJoin('region as external_car_region', 'external_car.region_id','=','external_car_region.region_id')
            ->leftJoin('countries', 'countries.id','=','region.country_id')
            ->leftJoin('customer_appnotes', 'customer_appnotes.car_id','=','car.id')
            ->leftJoin('port', 'car.destination','=','port.port_id')
            ->leftJoin('car_model', 'car.id_car_model','=','car_model.id_car_model')
            ->leftJoin('vehicle_type', 'car.id_vehicle_type','=','vehicle_type.id_vehicle_type')
            ->leftJoin('customer', 'car.customer_id','=','customer.customer_id')
            ->leftJoin('container_car', 'container_car.car_id', '=', 'car.id')
            ->leftJoin('container', 'container.container_id', '=', 'container_car.container_id')
            ->leftJoin('booking', 'booking.booking_id', '=','container_car.booking_id')
            ->leftJoin('booking_bl_container', 'booking_bl_container.booking_bl_container_id', '=','container.booking_bl_container_id')
            ->leftJoin('loaded_status', 'loaded_status.booking_id', '=','booking.booking_id')
            ->leftJoin('shipping_status', 'booking.booking_id','=','shipping_status.booking_id')
            ->leftJoin('arrived_car', 'arrived_car.car_id','=','car.id')
            ->leftJoin('towing_status', 'towing_status.car_id','=','car.id')
            ->leftJoin('bill_details', 'bill_details.car_id','=','car.id')
            ->leftJoin(DB::raw('(SELECT MIN(cars_title_status.cars_title_status_id) as mc_id,car_id,follow_car_title_note from cars_title_status GROUP BY cars_title_status.car_id ) as c1'), 'c1.car_id','=','car.id')
            ->leftJoin(DB::raw('(SELECT MAX(cars_title_status.cars_title_status_id) as c_id,car_id from cars_title_status GROUP BY cars_title_status.car_id ) as c2'), 'c2.car_id','=','car.id')
            ->leftJoin('cars_title_status', 'cars_title_status.cars_title_status_id', '=', 'c2.c_id')
            ->leftJoin('color', 'car.color','=','color.color_id')
            ->leftJoin('car_total_cost', 'car.id','=','car_total_cost.car_id')
            ->where([
                ['car.deleted','=', '0'],
                ['car.arrive_store','=', '0'],
                // ['car.arrived_port','=', '1'],
                // ['container.clearance_by_customer','=','0'],
                // ['car.deliver_customer','=', '0'],
                ['customer.customer_id','=', $customer_id]
            ])->when ($search , function ($query) use($search){
                $query->where(function($query)  use($search){
                    $query->where('car.vin', $search)
                        ->orWhere('car.lotnumber', $search);
                });
            });

        if($args['container_id']){
            // same api is used in containers page
            $query->where('container.container_id', $args['container_id']);
            $query->where(function($query){
                $query->where('container.clearance_by_customer', '1')
                    ->orWhere('car.arrived_port', '1');
            });
        }
        else{
            $query->where('car.arrived_port', '1');
            $query->where('car.deliver_customer', '0');
            $query->where('container.clearance_by_customer', '0');
            $query->whereNull("car_total_cost.car_id");
        }

        if (!empty($args['region'])) {
            $query->where(function($query)  use($args){
                $query->where('region.region_id', $args['region']);
                $query->orWhere('external_car_region.region_id', $args['region']);
            });
        }

        return $query;
    }
    public static function getPortCars($args)
    {
        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;
        $customer_id = $args['customer_id'];
        if (empty($customer_id)) {
            return [];
        }

        $select = "region.short_name, car.* , car_make.name AS carMakerName , car_model.name AS carModelName, vehicle_type.name AS vehicleName, region.region_name region,
                    IF(external_car.car_id, external_auction.title, auction.title) auctionTitle,customer_appnotes.notes as special_notes, IF(car.external_car='0', auction_location.auction_location_name, external_auction_location.auction_location_name) auction_location_name,IF(port.tag is not null,'".Constants::JEBEL_ALI_PORT_NAME."',port.port_name) port_name, customer.full_name AS CustomerName,
                    countries.id as country,booking.arrival_date as arrival_date,color.color_code, color.color_name, towing_status.picked_date,IF(car.external_car='0', region.region_name, external_car_region.region_name) as region,
                    CAST(bill_details.create_date AS DATE) as paymentDate,arrived_car.*,cars_title_status.follow_title,c1.follow_car_title_note,booking.eta,booking.etd,
                    CAST(cars_title_status.create_date AS DATE) as titleDate ,loaded_status.loaded_date,booking.booking_number,booking.eta,container.container_number, buyer.buyer_number,
                    shipping_status.shipping_date,booking_bl_container.arrival_date as booking_arrival_date, IF(port.country_id = 229, 1, 0) isUAEPort,towing_status.picked_car_title_note";

        $query = self::getQueryPortCars($args)
            ->groupBy('car.id');
        if($limit != 'all'){
            $query->skip($page * $limit)->take($limit);
        }

        $query->select(DB::raw($select));
        //echo Helpers::getRawSql($query);die;
        return $query->get()->toArray();
    }

    public static function getPortCarsCount($args)
    {
        $query = self::getQueryPortCars($args);
        $query->select(DB::raw('COUNT(car.id) as totalRecords'));
        return $query->first()->totalRecords;
    }


    // On Way Cars
    public static function getQueryOnWayCars($args) {
        $customer_id = $args['customer_id'];
        $search      = $args['search'];
        $query = DB::Table('car')
            ->leftJoin('external_car', 'car.id','=','external_car.car_id')
            ->leftJoin('car_make', 'car.id_car_make','=','car_make.id_car_make')
            ->leftJoin('buyer', 'car.buyer_id', '=', 'buyer.buyer_id')
            ->leftJoin('auction', 'car.auction_id','=','auction.id')
            ->leftJoin('auction_location', 'car.auction_location_id','=','auction_location.auction_location_id')
            ->leftJoin('auction_location as external_auction_location', 'external_car.auction_location_id','=','external_auction_location.auction_location_id')
            ->leftJoin('auction as external_auction', 'external_auction_location.auction_id','=','external_auction.id')
            ->leftJoin('customer_appnotes', 'customer_appnotes.car_id','=','car.id')
            ->leftJoin('posted_cars', 'posted_cars.car_id','=','car.id')
            ->leftJoin('towing_status', 'towing_status.car_id','=','car.id')
            ->leftJoin('arrived_car', 'arrived_car.car_id','=','car.id')
            ->leftJoin('warehouse', DB::raw('warehouse.warehouse_id=posted_cars.warehouse_id OR  warehouse.warehouse_id'), '=', 'arrived_car.warehouse_id')
            ->leftJoin('region', 'auction_location.region_id','=','region.region_id')
            ->leftJoin('region as external_car_region', 'external_car.region_id','=','external_car_region.region_id')
            ->leftJoin('countries', 'countries.id','=','region.country_id')
            ->leftJoin('port', 'car.destination','=','port.port_id')
            ->leftJoin('car_model', 'car.id_car_model','=','car_model.id_car_model')
            ->leftJoin('vehicle_type', 'car.id_vehicle_type','=','vehicle_type.id_vehicle_type')
            ->leftJoin('shipping_order_car', 'car.id','=','shipping_order_car.car_id')
            ->leftJoin('shipping_order', 'shipping_order.shipping_order_id','=','shipping_order_car.shipping_order_id')
            ->leftJoin('port as port_departure', 'port_departure.port_id','=','shipping_order.take_off_port_id')
            ->leftJoin('container_car', 'container_car.car_id','=','car.id')
            ->leftJoin('container', 'container.container_id','=','container_car.container_id')
            ->leftJoin('booking', 'booking.booking_id','=','container_car.booking_id')
            ->leftJoin('booking_bl_container', 'booking_bl_container.booking_id','=','container_car.booking_id')
            ->leftJoin('customer_file', 'customer_file.customer_file_id','=','booking_bl_container.bl_attach_id')
            ->leftJoin('loaded_status', 'loaded_status.booking_id','=','booking.booking_id')
            ->leftJoin('shipping_status', 'booking.booking_id','=','shipping_status.booking_id')
            ->leftJoin('color', 'car.color','=','color.color_id')
            ->leftJoin('bill_details', 'bill_details.car_id','=','car.id')
            ->leftJoin('customer', 'car.customer_id','=','customer.customer_id')
            ->leftJoin('car_total_cost', 'car.id','=','car_total_cost.car_id')
            ->leftJoin('special_port_receivable_info', 'special_port_receivable_info.car_id','=','car.id')
            ->leftJoin('cars_title_status', 'cars_title_status.cars_title_status_id', '=', DB::raw('(select max(cars_title_status_id) as cars_title_status_id from cars_title_status as cts4 WHERE `cts4`.`car_id` = car.id)'))
            ->where(function ($query){
                $query->where('car.shipping_status', '1')
                      ->orWhere('car.loading_status', '1');
            })
            ->where([
                ['car.deleted','=', '0'],
                ['car.arrived_port','=', '0'],
                ['customer.customer_id','=', $customer_id]
            ])->when ($search , function ($query) use($search){
                $query->where(function($query)  use($search){
                    $query->where('car.vin', 'like','%' . $search . '%')
                        ->orWhere('car.lotnumber', 'like','%' . $search . '%')
                        ->orWhere('car_make.name', 'like','%' . $search . '%')
                        ->orWhere('car_model.name', 'like','%' . $search . '%')
                        ->orWhere('container.container_number', 'like','%' . $search . '%')
                        ->orWhere('booking.booking_number', 'like','%' . $search . '%')
                        ->orWhere('auction.title', 'like','%' . $search . '%')
                        ->orWhere('auction_location.auction_location_name', 'like','%' . $search . '%')
                        ->orWhere('external_auction_location.auction_location_name', 'like','%' . $search . '%')
                        ->orWhere('region.region_name', 'like','%' . $search . '%')
                        ->orWhere('external_car_region.region_name', 'like','%' . $search . '%')
                        ->orWhereRaw("IF(port.tag is not null,'".Constants::JEBEL_ALI_PORT_NAME."' like '%". $search . "%',port.port_name like '%". $search . "%')");
                });
            });
        $query->whereNull("car_total_cost.car_id");
        if($args['container_id']){
            $query->where('container.container_id', $args['container_id']);
        }

        if (!empty($args['region'])) {
            $query->where(function($query)  use($args){
                $query->where('region.region_id', $args['region']);
                $query->orWhere('external_car_region.region_id', $args['region']);
            });
        }

        return $query;
    }

    public static function getOnWayCars($args)
    {
        $NEJOUM_CDN = Constants::NEJOUM_CDN;
        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;
        $customer_id = $args['customer_id'];
        if (empty($customer_id)) {
            return [];
        }

        $select = "car.id as carId , region.short_name, port_departure.port_name as departurePort, car.carnotes, car.lotnumber,car.vin,car.year, car.purchasedate,car.auction_location_id,car.photo ,
        car_make.name AS carMakerName , car_model.name AS carModelName, vehicle_type.name AS vehicleName,
        IF(external_car.car_id, external_auction.title, auction.title) auctionTitle,customer_appnotes.notes as specialNotes, IF(external_car.car_id, external_auction_location.auction_location_name, auction_location.auction_location_name) auction_location_name, IF(car.external_car='0', region.region_name, external_car_region.region_name) region_name,IF(port.tag is not null,'".Constants::JEBEL_ALI_PORT_NAME."',port.port_name) destination,color.color_name,
        CAST(bill_details.create_date AS DATE) as paymentDate,towing_status.picked_date,arrived_car.delivered_date,arrived_car.delivered_title,arrived_car.delivered_car_key,
        arrived_car.car_id,CAST(cars_title_status.create_date AS DATE) as titleDate,cars_title_status.follow_car_title_note,cars_title_status.follow_title,
        loaded_status.loaded_date,container.container_number,booking.booking_number,booking.eta,booking.etd, shipping_status.shipping_date, buyer.buyer_number,
        IF(container.bl_attach_id > 0, CONCAT('$NEJOUM_CDN', 'upload/customer_file/', customer_file.file_name), '') as bl_file,towing_status.picked_car_title_note,IF(port.country_id = 229, 1, 0) isUAEPort,
        special_port_receivable_info.customer_name port_receiver_customer_name";

        $query = self::getQueryOnWayCars($args)
            ->groupBy('car.id');
        if($limit != 'all'){
            $query->skip($page * $limit)->take($limit);
        }
        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }

    public static function getOnWayCarsCount($args)
    {
        $query = self::getQueryOnWayCars($args);
        $query->select(DB::raw('COUNT(car.id) as totalRecords'));
        return $query->first()->totalRecords;
    }
    // End On Way Cars

    //Arrived cars
    public static function getQueryArrivedCars($args) {
        $customer_id = $args['customer_id'];
        $search      = $args['search'];
        $query = DB::Table('car')
            ->leftJoin('external_car', 'car.id','=','external_car.car_id')
            ->leftJoin('car_make', 'car.id_car_make','=','car_make.id_car_make')
            ->leftJoin('buyer', 'car.buyer_id', '=', 'buyer.buyer_id')
            ->leftJoin('auction', 'car.auction_id','=','auction.id')
            ->leftJoin('auction_location', 'car.auction_location_id','=','auction_location.auction_location_id')
            ->leftJoin('auction_location as external_auction_location', 'external_car.auction_location_id','=','external_auction_location.auction_location_id')
            ->leftJoin('auction as external_auction', 'external_auction_location.auction_id','=','external_auction.id')
            ->leftJoin('customer_appnotes', 'customer_appnotes.car_id','=','car.id')
            ->leftJoin('towing_status', 'towing_status.car_id','=','car.id')
            ->leftJoin('arrived_car', 'arrived_car.car_id','=','car.id')
            ->leftJoin('region', 'auction_location.region_id','=','region.region_id')
            ->leftJoin('region as external_car_region', 'external_car.region_id','=','external_car_region.region_id')
            ->leftJoin('countries', 'countries.id','=','region.country_id')
            ->leftJoin('port', 'car.destination','=','port.port_id')
            ->leftJoin('car_model', 'car.id_car_model','=','car_model.id_car_model')
            ->leftJoin('vehicle_type', 'car.id_vehicle_type','=','vehicle_type.id_vehicle_type')
            ->leftJoin('shipping_order_car', 'car.id','=','shipping_order_car.car_id')
            ->leftJoin('shipping_order', 'shipping_order.shipping_order_id','=','shipping_order_car.shipping_order_id')
            ->leftJoin('container_car', 'container_car.car_id','=','car.id')
            ->leftJoin('container', 'container.container_id','=','container_car.container_id')
            ->leftJoin('booking', 'booking.booking_id','=','container_car.booking_id')
            ->leftJoin('booking_bl_container', 'booking_bl_container.booking_id','=','container_car.booking_id')
            ->leftJoin('loaded_status', 'loaded_status.booking_id','=','booking.booking_id')
            ->leftJoin('shipping_status', 'booking.booking_id','=','shipping_status.booking_id')
            ->leftJoin('color', 'car.color','=','color.color_id')
            ->leftJoin('bill_details', 'bill_details.car_id','=','car.id')
            ->leftJoin('customer', 'car.customer_id','=','customer.customer_id')
            ->leftJoin(DB::raw('(SELECT MIN(cars_title_status.cars_title_status_id) as mc_id,car_id,follow_car_title_note from cars_title_status GROUP BY cars_title_status.car_id ) as c1'), 'c1.car_id','=','car.id')
            ->leftJoin(DB::raw('(SELECT MAX(cars_title_status.cars_title_status_id) as c_id,car_id from cars_title_status GROUP BY cars_title_status.car_id ) as c2'), 'c2.car_id','=','car.id')
            ->leftJoin('cars_title_status', 'cars_title_status.cars_title_status_id','=','c2.c_id')
            ->leftJoin('car_total_cost', 'car.id','=','car_total_cost.car_id')
            ->where(function ($query){
                $query->where('car.shipping_status', '1')
                      ->orWhere('car.loading_status', '1');
            })
            ->where([
                ['car.deleted','=', '0'],
                ['car.arrived_port','=', '1'],
                ['car.arrive_store','=', '0'],
                ['container.clearance_by_customer','=', '0'],
                ['car.deliver_customer','=', '0'],
                ['customer.customer_id','=', $customer_id]
            ])->when ($search , function ($query) use($search){
                $query->where(function($query)  use($search){
                    $query->where('car.vin', $search)
                        ->orWhere('car.lotnumber', $search);
                });
            })
            ->whereNull("car_total_cost.car_id");

        if (!empty($args['region'])) {
            $query->where(function($query)  use($args){
                $query->where('region.region_id', $args['region']);
                $query->orWhere('external_car_region.region_id', $args['region']);
            });
        }

        return $query;
    }
    public static function getArrivedCars($args)
    {
        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;
        $customer_id = $args['customer_id'];
        if (empty($customer_id)) {
            return [];
        }

        $select = "car.photo , car.id as carId , region.short_name, car.lotnumber,car.vin,car.year,car.purchasedate , car_make.name AS carMakerName , car_model.name AS carModelName, vehicle_type.name AS vehicleName,
        IF(external_car.car_id, external_auction.title, auction.title) auctionTitle,customer_appnotes.notes as specialNotes, IF(car.external_car='0', auction_location.auction_location_name, external_auction_location.auction_location_name) auction_location_name,IF(port.tag is not null,'".Constants::JEBEL_ALI_PORT_NAME."',port.port_name) port_name, customer.full_name AS customerName,
        countries.id as country,booking.arrival_date as arrivalDate,color.color_code, color.color_name, towing_status.picked_date,IF(car.external_car='0', region.region_name, external_car_region.region_name) as region,
        CAST(bill_details.create_date AS DATE) as paymentDate,arrived_car.*,cars_title_status.follow_title,c1.follow_car_title_note,booking.eta,booking.etd,
        CAST(cars_title_status.create_date AS DATE) as titleDate, buyer.buyer_number,
        loaded_status.loaded_date,booking.booking_number,booking.eta,container.container_number
        ,shipping_status.shipping_date,booking_bl_container.arrival_date as booking_arrival_date,towing_status.picked_car_title_note";

        $query = self::getQueryArrivedCars($args)
            ->groupBy('car.id')
            ->skip($page * $limit)->take($limit);

        $query->select(DB::raw($select));
        //echo Helpers::getRawSql($query);die;
        return $query->get()->toArray();
    }

    public static function getArrivedCarsCount($args)
    {
        $query = self::getQueryArrivedCars($args);
        $query->select(DB::raw('COUNT(car.id) as totalRecords'));
        return $query->first()->totalRecords;
    }

    //End Arrived Cars

    //Delivered Paid Cars
    public static function getQueryDeliveredPaidCars($args) {
        $customer_id = $args['customer_id'];
        $search      = $args['search'];
        $query = DB::Table('car')
        ->leftJoin('external_car', 'car.id','=','external_car.car_id')
        ->leftJoin('car_make', 'car.id_car_make','=','car_make.id_car_make')
        ->leftJoin('buyer', 'car.buyer_id', '=', 'buyer.buyer_id')
        ->leftJoin('auction', 'car.auction_id','=','auction.id')
        ->leftJoin('customer_appnotes', 'customer_appnotes.car_id','=','car.id')
        ->leftJoin('auction_location', 'car.auction_location_id','=','auction_location.auction_location_id')
        ->leftJoin('auction_location as external_auction_location', 'external_car.auction_location_id','=','external_auction_location.auction_location_id')
        ->leftJoin('auction as external_auction', 'external_auction_location.auction_id','=','external_auction.id')
        ->leftJoin('region', 'auction_location.region_id','=','region.region_id')
        ->leftJoin('region as external_car_region', 'external_car.region_id','=','external_car_region.region_id')
        ->leftJoin('countries', 'countries.id','=','region.country_id')
        ->leftJoin('port', 'car.destination','=','port.port_id')
        ->leftJoin('car_model', 'car.id_car_model','=','car_model.id_car_model')
        ->leftJoin('vehicle_type', 'car.id_vehicle_type','=','vehicle_type.id_vehicle_type')
        ->leftJoin('customer', 'car.customer_id','=','customer.customer_id')
        ->join('receive_car', 'car.id','=','receive_car.car_id')
        ->leftJoin('final_payment_invoices_details', 'final_payment_invoices_details.car_id','=','car.id')
        ->leftJoin('color', 'car.color','=','color.color_id')
        ->leftJoin('container_car', 'container_car.car_id','=','car.id')
        ->leftJoin('container', 'container.container_id','=','container_car.container_id')
        ->leftJoin('booking', 'booking.booking_id','=','container_car.booking_id')
        ->leftJoin('booking_bl_container', 'booking_bl_container.booking_bl_container_id','=','container.booking_bl_container_id')
        ->leftJoin('shipping_status', 'booking.booking_id','=','shipping_status.booking_id')
        ->leftJoin('arrived_car', 'arrived_car.car_id','=','car.id')
        ->leftJoin('car_total_cost', 'car.id','=','car_total_cost.car_id')
        ->leftJoin('towing_status', 'towing_status.car_id','=','car.id')
        ->leftJoin('bill_details', 'bill_details.car_id','=','car.id')
        ->join('loaded_status', 'loaded_status.booking_id','=','booking.booking_id')
        ->leftJoin('recovery', 'recovery.recovery_id','=','receive_car.recovery_id')
        ->leftJoin('cars_title_status', DB::raw('cars_title_status.follow_title=1 AND cars_title_status.car_id'), '=', 'car.id')
            ->where([
                ['car.deleted','=', '0'],
                ['car.final_payment_status','=', '1'],
                ['car.deliver_customer','=', '1'],
                ['customer.customer_id','=', $customer_id]
            ])->when ($search , function ($query) use($search){
                $query->where(function($query)  use($search){
                    $query->where('car.vin', $search)
                        ->orWhere('car.lotnumber', $search);
                });
            });

        if (!empty($args['region'])) {
            $query->where(function($query)  use($args){
                $query->where('region.region_id', $args['region']);
                $query->orWhere('external_car_region.region_id', $args['region']);
            });
        }

        return $query;
    }

    public static function getQueryDeliveredUnPaidCars($args) {
        $customer_id = $args['customer_id'];
        $search      = $args['search'];
        $query = DB::Table('car')
        ->leftJoin('external_car', 'car.id','=','external_car.car_id')
        ->leftJoin('car_make', 'car.id_car_make','=','car_make.id_car_make')
        ->leftJoin('buyer', 'car.buyer_id', '=', 'buyer.buyer_id')
        ->leftJoin('auction', 'car.auction_id','=','auction.id')
        ->leftJoin('customer_appnotes', 'customer_appnotes.car_id','=','car.id')
        ->leftJoin('auction_location', 'car.auction_location_id','=','auction_location.auction_location_id')
        ->leftJoin('auction_location as external_auction_location', 'external_car.auction_location_id','=','external_auction_location.auction_location_id')
        ->leftJoin('auction as external_auction', 'external_auction_location.auction_id','=','external_auction.id')
        ->leftJoin('region', 'auction_location.region_id','=','region.region_id')
        ->leftJoin('region as external_car_region', 'external_car.region_id','=','external_car_region.region_id')
        ->leftJoin('countries', 'countries.id','=','region.country_id')
        ->leftJoin('port', 'car.destination','=','port.port_id')
        ->leftJoin('car_model', 'car.id_car_model','=','car_model.id_car_model')
        ->leftJoin('vehicle_type', 'car.id_vehicle_type','=','vehicle_type.id_vehicle_type')
        ->leftJoin('customer', 'car.customer_id','=','customer.customer_id')
        ->join('receive_car', 'car.id','=','receive_car.car_id')
        ->leftJoin('final_payment_invoices_details', 'final_payment_invoices_details.car_id','=','car.id')
        ->leftJoin('color', 'car.color','=','color.color_id')
        ->leftJoin('container_car', 'container_car.car_id','=','car.id')
        ->leftJoin('container', 'container.container_id','=','container_car.container_id')
        ->leftJoin('booking', 'booking.booking_id','=','container_car.booking_id')
        ->leftJoin('booking_bl_container', 'booking_bl_container.booking_bl_container_id','=','container.booking_bl_container_id')
        ->join('shipping_status', 'booking.booking_id','=','shipping_status.booking_id')
        ->leftJoin('arrived_car', 'arrived_car.car_id','=','car.id')
        ->leftJoin('car_total_cost', 'car.id','=','car_total_cost.car_id')
        ->leftJoin('towing_status', 'towing_status.car_id','=','car.id')
        ->leftJoin('bill_details', 'bill_details.car_id','=','car.id')
        ->join('loaded_status', 'loaded_status.booking_id','=','booking.booking_id')
        ->leftJoin('recovery', 'recovery.recovery_id','=','receive_car.recovery_id')
        ->leftJoin('cars_title_status', DB::raw('cars_title_status.follow_title=1 AND cars_title_status.car_id'), '=', 'car.id')
            ->where([
                ['car.deleted','=', '0'],
                ['car.final_payment_status','=', '0'],
                ['car.deliver_customer','=', '1'],
                ['customer.customer_id','=', $customer_id]
            ])->when ($search , function ($query) use($search){
                $query->where(function($query)  use($search){
                    $query->where('car.vin', $search)
                        ->orWhere('car.lotnumber', $search);
                });
            });

        if (!empty($args['region'])) {
            $query->where(function($query)  use($args){
                $query->where('region.region_id', $args['region']);
                $query->orWhere('external_car_region.region_id', $args['region']);
            });
        }

        return $query;
    }
    public static function getDeliveredCars($args)
    {
        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;
        $customer_id = $args['customer_id'];
        $type = $args['type'];
        if (empty($customer_id)) {
            return [];
        }

        if($type == "All"){
            $selectPaid = "car.id as carId , region.short_name, car.lotnumber, car.photo, car.vin , car.year ,car.purchasedate , car_make.name AS carMakerName , car_model.name AS carModelName, vehicle_type.name AS vehicleName,IF(external_car.car_id, external_auction.title, auction.title) auctionTitle,
            loaded_status.loaded_date as loaded_date, shipping_status.shipping_date, IF(car.external_car='0', auction_location.auction_location_name, external_auction_location.auction_location_name) auction_location_name,IF(port.tag is not null,'".Constants::JEBEL_ALI_PORT_NAME."',port.port_name) port_name, customer.full_name AS CustomerName,countries.id as country,
            CAST(receive_car.create_date AS DATE) AS receive_date,CAST(receive_car.deliver_create_date AS DATE) AS deliver_create_date,final_payment_invoices_details.created_date as paymentDayFinl,
            final_payment_invoices_details.amount_paid,final_payment_invoices_details.remaining_amount,color.color_code,car_total_cost.total_price AS total_cost,
            booking_bl_container.arrival_date as arrival_date,color.color_name,towing_status.picked_date,IF(car.external_car='0', region.region_name, external_car_region.region_name) as region,bill_details.create_date as paymentDate,
            arrived_car.delivered_date,arrived_car.delivered_title,arrived_car.delivered_title,arrived_car.delivered_car_title_note,
            container.container_number,booking.booking_number,cars_title_status.follow_title,cars_title_status.follow_car_title_note,booking.eta,booking.etd,
            cars_title_status.create_date as titleDate,  recovery.name as recovery_name, receive_car.recovery_id as recovery_id,receive_car.customer_signature,
            customer_appnotes.notes as special_notes, buyer.buyer_number,towing_status.picked_car_title_note";

            $queryPaid = self::getQueryDeliveredPaidCars($args);
            $queryPaid->select(DB::raw($selectPaid));
            // $selectUnPaid = "car.id as carId , region.short_name, car_make.name AS carMakerName , car_model.name AS carModelName, vehicle_type.name AS vehicleName,
            // auction.title AS auctionTitle, loaded_status.loaded_date as loaded_date, shipping_status.shipping_date,container.container_number,auction_location.auction_location_name,port.port_name, customer.full_name AS CustomerName,countries.id as country,CAST(receive_car.create_date AS DATE) AS     receive_date
            // ,CAST(receive_car.deliver_create_date AS DATE) AS deliver_create_date,
            // car_total_cost.total_price as total_cost,color.color_name,booking.booking_number,
            // booking.arrival_date as arrival_date,color.color_code,towing_status.picked_date,
            // region.region_name as region,bill_details.create_date as paymentDate,
            // cars_title_status.follow_title,cars_title_status.follow_car_title_note,booking.eta,
            // booking.etd,cars_title_status.create_date as titleDate, recovery.name as recovery_name,
            // receive_car.recovery_id as recovery_id, receive_car.customer_signature, customer_appnotes.notes as special_notes";

            $queryUnPaid = self::getQueryDeliveredUnPaidCars($args)->union($queryPaid)
                    ->groupBy('car.id');
            if($limit != 'all'){
                $queryUnPaid->skip($page * $limit)->take($limit);
            }
            $queryUnPaid->select(DB::raw($selectPaid));
            return $queryUnPaid->get()->toArray();
            //return array_merge($queryPaid->get()->toArray(), $queryUnPaid->get()->toArray());
        }else if ($type == "Paid"){
            $selectPaid = "car.id as carId , region.short_name, car.lotnumber, car.photo, car.vin , car.year ,car.purchasedate , car_make.name AS carMakerName , car_model.name AS carModelName, vehicle_type.name AS vehicleName,IF(external_car.car_id, external_auction.title, auction.title) auctionTitle,
            loaded_status.loaded_date as loaded_date, shipping_status.shipping_date, IF(car.external_car='0', auction_location.auction_location_name, external_auction_location.auction_location_name) auction_location_name,IF(port.tag is not null,'".Constants::JEBEL_ALI_PORT_NAME."',port.port_name) port_name, customer.full_name AS CustomerName,countries.id as country,
            CAST(receive_car.create_date AS DATE) AS receive_date,CAST(receive_car.deliver_create_date AS DATE) AS deliver_create_date,final_payment_invoices_details.created_date as paymentDayFinl,
            final_payment_invoices_details.amount_paid,final_payment_invoices_details.remaining_amount,color.color_code,car_total_cost.total_price AS total_cost,
            booking_bl_container.arrival_date as arrival_date,color.color_name,towing_status.picked_date,IF(car.external_car='0', region.region_name, external_car_region.region_name) as region,bill_details.create_date as paymentDate,
            arrived_car.delivered_date,arrived_car.delivered_title,arrived_car.delivered_title,arrived_car.delivered_car_title_note,
            container.container_number,booking.booking_number,cars_title_status.follow_title,cars_title_status.follow_car_title_note,booking.eta,booking.etd,
            cars_title_status.create_date as titleDate,  recovery.name as recovery_name, receive_car.recovery_id as recoveryData,receive_car.customer_signature,
            customer_appnotes.notes as specialNotes, buyer.buyer_number,towing_status.picked_car_title_note";

            $queryPaid = self::getQueryDeliveredPaidCars($args)
                        ->groupBy('car.id');
            if($limit != 'all'){
                $queryPaid->skip($page * $limit)->take($limit);
            }

            $queryPaid->select(DB::raw($selectPaid));
            return $queryPaid->get()->toArray();
        }else{
            $selectUnPaid = "region.short_name, car.* , car_make.name AS carMakerName , car_model.name AS carModelName, vehicle_type.name AS vehicleName,
            IF(external_car.car_id, external_auction.title, auction.title) auctionTitle, loaded_status.loaded_date as loaded_date, shipping_status.shipping_date,container.container_number,IF(car.external_car='0', auction_location.auction_location_name, external_auction_location.auction_location_name) auction_location_name,IF(port.tag is not null,'".Constants::JEBEL_ALI_PORT_NAME."',port.port_name) port_name, customer.full_name AS CustomerName,countries.id as country,CAST(receive_car.create_date AS DATE) AS     receive_date
            ,CAST(receive_car.deliver_create_date AS DATE) AS deliver_create_date,
            car_total_cost.total_price as total_cost,color.color_name,booking.booking_number,
            booking.arrival_date as arrival_date,color.color_code,towing_status.picked_date,
            IF(car.external_car='0', region.region_name, external_car_region.region_name) as region,bill_details.create_date as paymentDate,arrived_car.*,
            cars_title_status.follow_title,cars_title_status.follow_car_title_note,booking.eta,
            booking.etd,cars_title_status.create_date as titleDate, recovery.name as recovery_name, buyer.buyer_number,
            receive_car.recovery_id as recovery_iddata, receive_car.customer_signature, customer_appnotes.notes as special_notes,towing_status.picked_car_title_note";

            $queryUnPaid = self::getQueryDeliveredUnPaidCars($args)
            ->groupBy('car.id');
            if($limit != 'all'){
                $queryUnPaid->skip($page * $limit)->take($limit);
            }

            $queryUnPaid->select(DB::raw($selectUnPaid));
            return $queryUnPaid->get()->toArray();
        }
    }

    public static function getDeliveredCarsCount($args)
    {
        $type = $args['type'];
        if($type == "All"){
            $queryPaid = self::getQueryDeliveredPaidCars($args);
            $queryUnPaid = self::getQueryDeliveredUnPaidCars($args);
            $queryPaid->select(DB::raw('COUNT(DISTINCT(car.id)) as totalRecords'));
            $queryUnPaid->select(DB::raw('COUNT(DISTINCT(car.id)) as totalRecords'));
            return ($queryPaid->first()->totalRecords + $queryUnPaid->first()->totalRecords);
        }
        else if($type == "Paid"){
            $queryPaid = self::getQueryDeliveredPaidCars($args);
            $queryPaid->select(DB::raw('COUNT(DISTINCT(car.id)) as totalRecords'));
            return ($queryPaid->first()->totalRecords);
        }
        else{
            $queryUnPaid = self::getQueryDeliveredUnPaidCars($args);
            $queryUnPaid->select(DB::raw('COUNT(DISTINCT(car.id)) as totalRecords'));
            return ($queryUnPaid->first()->totalRecords);
        }
    }
    //End Delivered Cars

    public static function getCarWarehouseRegion($customer_id){

        $select = "region.region_id as arrived_warehouse,
		car.id, region.region_id as posted_warehouse, count(car.id) as totalcount,
		region.region_name as warehouse_name, region.region_name as warehouse_name_ar, customer_appnotes.notes as special_notes";

        $query = self::getQueryarehouseRegion($customer_id)
            ->groupBy('warehouse.region_id');

        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }

    public static function getQueryarehouseRegion($customer_id) {
        $query = DB::Table('car')
            ->leftJoin('customer', 'car.customer_id','=','customer.customer_id')
            ->leftJoin('arrived_car', 'car.id','=', 'arrived_car.car_id')
            ->leftJoin('posted_cars', 'car.id' ,'=', 'posted_cars.car_id')
            ->leftJoin('customer_appnotes', 'customer_appnotes.car_id' ,'=', 'car.id')
            ->leftJoin('warehouse', function ($leftJoin) {
                $leftJoin->on(function($query){
                    $query->on('arrived_car.warehouse_id', '=', 'warehouse.warehouse_id')
                    ->orOn('posted_cars.warehouse_id', '=','warehouse.warehouse_id');
                });
               })
            ->leftJoin('region', 'region.region_id','=','warehouse.region_id')
            ->where([
                ['car.deleted','=', '0'],
                ['car.status','!=', '4'],
                ['arrivedstatus','=', '1'],
                ['loading_status','=','0'],
                ['customer.customer_id','=', $customer_id]
            ]);
        return $query;
    }

    public static function getCarCost($car_id, $account_id)
    {
        $query = DB::Table('accounttransaction')
            ->where('accounttransaction.AccountID', $account_id)
            ->where('accounttransaction.car_id', $car_id)
            ->where('accounttransaction.deleted', '0')
            ->where('accounttransaction.car_step', '1')
            ->where('accounttransaction.type_transaction', '3')
            ->select(DB::raw('accounttransaction.Debit'));

        return $query->first()->Debit;
    }

    public static function shippedCarsData($args){
        $arrived_status = $args['arrived_status'];
        $customer_account_id = $args['customer_account_id'];
        $customer_id = $args['customer_id'];
        $date_from = $args['date_from'];
        $date_to = $args['date_to'];

        $select = "car.*,car_total_cost.create_date as received_create_date,receive_car.deliver_status, container.container_number,
        car_make.name AS carMakerName , car_model.name AS carModelName,vehicle_type.name AS vehicleName,color.color_code,
        SUM(accounttransaction.Debit) Debit";

        if($arrived_status == 0){
            $select .= ", SUM(accounttransaction.Credit) Credit";
        }

        if(empty($args['closingTable'])){
            $query = DB::Table('accounttransaction')
            ->join('journal', 'journal.id', '=', 'accounttransaction.journal_id');
        }
        else{
            $query = DB::Table('accounttransaction_closing_1', 'accounttransaction')
            ->join('journal_closing_1 as journal', 'journal.id', '=', 'accounttransaction.journal_id');
        }

            $query->leftJoin('car', 'car.id', '=', 'accounttransaction.car_id')
            ->leftJoin('car_make', 'car.id_car_make', '=', 'car_make.id_car_make')
            ->leftJoin('car_model', 'car.id_car_model', '=', 'car_model.id_car_model')
            ->leftJoin('vehicle_type', 'car.id_vehicle_type', '=', 'vehicle_type.id_vehicle_type')
            ->leftJoin('color', 'color.color_id', '=', 'car.color')
            ->leftJoin('receive_car', 'receive_car.car_id', '=', 'car.id')
            ->leftJoin('container_car', 'container_car.car_id','=','car.id')
            ->leftJoin('container', 'container.container_id','=','container_car.container_id')
            ->where('accounttransaction.AccountID', $customer_account_id)
            ->where('car.deleted', 0)
            ->where('accounttransaction.deleted', 0)
            ->where('car.customer_id', $customer_id)
            ->groupBy("accounttransaction.car_id")
            ->orderBy("car_total_cost.create_date");

            if($arrived_status != 0){
                $query->where("accounttransaction.car_step", "!=", '1');
                $query->where("accounttransaction.type_transaction", "!=", '2');
            }

            if($arrived_status == 0){
                $query->leftJoin('car_total_cost', 'car_total_cost.car_id', '=', 'accounttransaction.car_id');
                $query->where("accounttransaction.DateOfTransAction", ">=", $date_from);
                $query->where("accounttransaction.DateOfTransAction", "<=", $date_to);
                $query->whereRaw("IF(car_total_cost.create_date,DATE(car_total_cost.create_date)>='$date_to',accounttransaction.DateOfTransAction >='$date_from' && accounttransaction.DateOfTransAction <='$date_to')");
            }
            elseif($arrived_status == 1){
                $query->join('car_total_cost', 'car_total_cost.car_id', '=', 'accounttransaction.car_id');
                $query->whereDate("car_total_cost.create_date", '>=', $date_from);
                $query->whereDate("car_total_cost.create_date", '<=', $date_to);
                $query->where("journal.date", '<=', $date_to);
            }
            elseif($arrived_status == 2){
                $query->leftJoin('car_total_cost', 'car_total_cost.car_id', '=', 'accounttransaction.car_id');

                if(isset($args['previous_balance_function'])){
                    $query->where("journal.date", '>=', $date_from);
                    $query->where("journal.date", '<=', $date_to);
                }
                else{
                    $query->whereRaw("IF(car_total_cost.car_id, DATE(car_total_cost.create_date) >= '$date_from' && journal.date >= '$date_from',  journal.date >= '$date_from' )");
                    $query->whereRaw("IF(car_total_cost.car_id, DATE(car_total_cost.create_date) <= '$date_to' && journal.date <= '$date_to',  journal.date <= '$date_to' )");
                }
            }

        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }

    public static function transactionAfterCompleted($args){
        $arrived_status = $args['arrived_status'];
        $customer_account_id = $args['customer_account_id'];
        $customer_id = $args['customer_id'];
        $date_from = $args['date_from'];
        $date_to = $args['date_to'];

        $select = "SUM(accounttransaction.Debit) Debit, car.*,max(accounttransaction.DateOfTransAction)  as received_create_date,receive_car.deliver_status,car_make.name AS carMakerName , car_model.name AS carModelName,vehicle_type.name AS vehicleName,color.color_code,
        container.container_number";

        if(empty($args['closingTable'])){
            $query = DB::Table('accounttransaction')
            ->join('journal', 'journal.id', '=', 'accounttransaction.journal_id');
        }
        else{
            $query = DB::Table('accounttransaction_closing_1', 'accounttransaction')
            ->join('journal_closing_1 as journal', 'journal.id', '=', 'accounttransaction.journal_id');
        }

        $query->leftJoin('car', 'car.id', '=', 'accounttransaction.car_id')
            ->leftJoin('car_make', 'car.id_car_make', '=', 'car_make.id_car_make')
            ->leftJoin('car_model', 'car.id_car_model', '=', 'car_model.id_car_model')
            ->leftJoin('vehicle_type', 'car.id_vehicle_type', '=', 'vehicle_type.id_vehicle_type')
            ->leftJoin('color', 'color.color_id', '=', 'car.color')
            ->leftJoin('receive_car', 'receive_car.car_id', '=', 'car.id')
            ->leftJoin('container_car', 'container_car.car_id','=','car.id')
            ->leftJoin('container', 'container.container_id','=','container_car.container_id')
            ->join('car_total_cost', 'car_total_cost.car_id', '=', 'accounttransaction.car_id')
            ->where('accounttransaction.AccountID', $customer_account_id)
            ->where('car.deleted','=', '0')
            ->where('accounttransaction.deleted','=', '0')
            ->where('car.customer_id', $customer_id)
            ->where("accounttransaction.car_step", '!=', '1')
            ->where("accounttransaction.type_transaction", '!=', '2')
            ->groupBy("accounttransaction.car_id")
            ->orderBy("car_total_cost.create_date");
            if($arrived_status == 0)
            {
                $query->leftJoin('final_bill', 'final_bill.car_id', '=', 'car.id');
                $query->whereNull('final_bill.car_id');
            }
            if($arrived_status == 1)
            {
                $query->join('final_bill', 'final_bill.car_id', '=', 'car.id');
            }
            if($date_from){
                $query->where("car_total_cost.create_date", '<=', $date_from);
                $query->where("journal.date", '>=', $date_from);
            }

            if($date_to){
                $query->where("journal.date", '<=', $date_to);
            }

        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }

    public static function getAllTransation2CarsStatement($args){
        $arrived_status = $args['arrived_status'];
        $customer_account_id = $args['customer_account_id'];
        $customer_id = $args['customer_id'];
        $date_from = $args['date_from'];
        $date_to = $args['date_to'];

        $select = "accounttransaction.*,car.lotnumber AS lotnumber,car.cancellation as car_cancellation, car.status as car_status, car.customer_id as car_customer_id, DATE(car_total_cost.create_date) as completed_date,
        car.*, car_total_cost.create_date as received_create_date,receive_car.deliver_status, car_make.name AS carMakerName , car_model.name AS carModelName,vehicle_type.name AS vehicleName,color.color_code,
        container.container_number";

        if(empty($args['closingTable'])){
            $query = DB::Table('accounttransaction');
        }
        else{
            $query = DB::Table('accounttransaction_closing_1', 'accounttransaction');
        }

        $query->leftJoin('car', 'car.id', '=', 'accounttransaction.car_id')
        ->leftJoin('car_make', 'car.id_car_make', '=', 'car_make.id_car_make')
        ->leftJoin('car_model', 'car.id_car_model', '=', 'car_model.id_car_model')
        ->leftJoin('vehicle_type', 'car.id_vehicle_type', '=', 'vehicle_type.id_vehicle_type')
        ->leftJoin('color', 'color.color_id', '=', 'car.color')
        ->leftJoin('car_total_cost', 'car_total_cost.car_id', '=', 'accounttransaction.car_id')
        ->leftJoin('receive_car', 'receive_car.car_id', '=', 'car.id')
        ->leftJoin('container_car', 'container_car.car_id','=','car.id')
        ->leftJoin('container', 'container.container_id','=','container_car.container_id')
        ->where('accounttransaction.AccountID', $customer_account_id)
        ->where('accounttransaction.deleted', 0)
        ->orderBy("DateOfTransAction");

        if(isset($args['previous_balance_function'])){
            if(empty($args['closingTable']) && $date_to < $args['closed_date']){
                $query->where('accounttransaction.car_step', '!=', Constants::CAR_STEPS['CLOSING_CUSTOMER_BALANCE']);
            }
        }
        else{
            if(empty($args['closingTable']) && $date_from <= $args['closed_date']){
                $query->where('accounttransaction.car_step', '!=', Constants::CAR_STEPS['CLOSING_CUSTOMER_BALANCE']);
            }
        }

        if($args['excludeOpeningJournal']){
            $query->where('accounttransaction.car_step', '!=', Constants::CAR_STEPS['CLOSING_CUSTOMER_BALANCE']);
        }

        if($arrived_status == 1){
            $query->where(function($query) use($customer_id) {
                $query->whereRaw(" ((type_transaction = 3 and car_step = 0 && Debit = 0) OR (type_transaction = 3 and car_step > 1 and accounttransaction.car_id = 0 && Debit > 0) OR (type_transaction = 3 and car_step = 0 and accounttransaction.car_id = 0) OR (type_transaction = 3 and Debit = 0 and car_step > 0 and car_step  != 106 and car_step  != 300 and car_step  != 301 and car_step  != 302  and car_step  != 303 and car_step  != 304 and car_step  != 305 and car_step  != 306 and car_step  != 307 and car_step  != 308 and car_step  != 309 and car_step  != 310 and car_step  != 108 and car_step  != 116 and car_step  != 110 and  car_step  != 111 and  car_step  != 311  and car_step!= 312 and car_step  != 313 and car_step  != 314 and  car_step  != 173 and car_step  != 15) OR (type_transaction = 3 and Debit = 0 and car_step > 1 and accounttransaction.car_id = 0) OR (type_transaction = 3 and car_step = 1)  OR (type_transaction = 1 or type_transaction = 2))");
                $query->orWhereRaw("(type_transaction = 3 and car_step > 0 and accounttransaction.car_id > 0 and car.customer_id != $customer_id )");
            });
        }
        else if($arrived_status == 0){
            $query->where("car.arrive_store", '=', '0');
            $query->whereRaw(" ((type_transaction = 3 and car_step = 1) OR (type_transaction = 3 and Debit = 0 and car_step > 0 and car_step  != 106 and car_step  != 300 and car_step  != 301 and car_step  != 302 and car_step  != 304 and car_step  != 305 and car_step  != 306 and car_step  != 307 and car_step  != 308 and car_step  != 309 and car_step  != 310 and car_step  != 108 and car_step  != 116 and car_step  != 110) OR (type_transaction = 1 and car_step = 1))");
            if($date_to){
            $query->whereRaw("IF(car_total_cost.create_date,DATE(car_total_cost.create_date) > '$date_to',DateOfTransAction <= '$date_to') ");
            }
            else{
                $query->whereNull("car_total_cost.car_id");
            }
        }
        else if($arrived_status == 2){
            $query->where(function($query) use($customer_id) {
                 $query->whereRaw(" ((type_transaction = 3 and car_step = 0 && Debit = 0) OR (type_transaction = 3 and car_step > 1 and accounttransaction.car_id = 0 && Debit > 0) OR (type_transaction = 3 and car_step = 0 and accounttransaction.car_id = 0) OR (type_transaction = 3 and Debit = 0 and car_step > 0 and car_step  != 106 and car_step  != 300 and car_step  != 301 and car_step  != 302  and car_step  != 303 and car_step  != 304 and car_step  != 305 and car_step  != 306 and car_step  != 307 and car_step  != 308 and car_step  != 309 and car_step  != 310 and car_step  != 108 and car_step  != 116 and car_step  != 110 and  car_step  != 111 and  car_step  != 311  and car_step!= 312 and car_step  != 313 and car_step  != 314 and  car_step  != 173 and car_step  != 15) OR (type_transaction = 3 and Debit = 0 and car_step > 1 and accounttransaction.car_id = 0) OR (type_transaction = 3 and car_step = 1)  OR (type_transaction = 1 or type_transaction = 2))");
                 $query->orWhereRaw("(type_transaction = 3 and car_step > 0 and accounttransaction.car_id > 0 and car.customer_id != $customer_id )");
             });
        }

        if($date_from){
            $query->whereDate("DateOfTransAction" ,'>=', $date_from);
        }
        if($date_to){
            $query->whereDate("DateOfTransAction" ,'<=', $date_to);
        }

        $query->select(DB::raw($select));
        return collect($query->get())->map(function ($x) {
            return (array) $x;
        })->toArray();
    }

    public static function getCarsInAuctionPreviousBalance($args){
        $customer_account_id = $args['customer_account_id'];
        $customer_id = $args['customer_id'];
        $date_from = $args['date_from'];

        $select = "SUM(Debit) totalDebit, SUM(Credit) totalCredit";

        $query = DB::Table('accounttransaction')
            ->join('car', 'car.id', '=', DB::raw("accounttransaction.car_id && car.customer_id=$customer_id"))
            ->leftJoin('car_total_cost', 'car_total_cost.car_id', '=', 'accounttransaction.car_id')
            ->where('accounttransaction.AccountID', $customer_account_id)
            ->where('accounttransaction.deleted', 0)
            ->whereRaw("((car_step=1 and accounttransaction.car_id > 0) or (type_transaction=1 and accounttransaction.car_id > 0 and car_step <= 1))")
            ->whereRaw("IF(car_total_cost.car_id, DATE(car_total_cost.create_date) > '$date_from', true)")
            ->where('DateOfTransAction', '<', $date_from);

        $query->select(DB::raw($select));
        return $query->first();
    }

    public static function getCarsInAuctionTransactions($args)
    {
        $customer_account_id = $args['customer_account_id'];
        $customer_id = $args['customer_id'];
        $date_from = $args['date_from'];
        $date_to = $args['date_to'];

        $select = "accounttransaction.*,  car.lotnumber, car.cancellation car_cancellation, car.id as car_id,
        SUM(accounttransaction.Debit)as Debit, SUM(accounttransaction.Credit) as Credit";

        $query = DB::Table('accounttransaction')
            ->join('car', 'car.id', '=', DB::raw("accounttransaction.car_id && car.customer_id=$customer_id"))
            ->leftJoin('car_total_cost', 'car_total_cost.car_id', '=', 'car.id')
            ->where('accounttransaction.AccountID', $customer_account_id)
            ->where('accounttransaction.deleted', 0)
            ->whereRaw(" ((car_step=1 and accounttransaction.car_id > 0) or (type_transaction=1 and accounttransaction.car_id > 0 and car_step <= 1)) ")
            ->whereRaw("IF(car_total_cost.car_id, DATE(car_total_cost.create_date) > '$date_to', true)");

        if (!empty($date_from)) {
            $query->where('DateOfTransAction', '>=', $date_from);
        }

        if (!empty($date_to)) {
            $query->where('DateOfTransAction', '<=', $date_to);
        }

        $query->orderBy("DateOfTransAction");
        $query->groupBy("car.id");

        $query->select(DB::raw($select));
        return $query->get();
    }

    public static function getGeneralTransactionsPreviousBalance($args){
        $customer_account_id = $args['customer_account_id'];
        $customer_id = $args['customer_id'];
        $date_from = $args['date_from'];

        $select = "SUM(Debit) totalDebit, SUM(Credit) totalCredit";

        $query = DB::Table('accounttransaction')
            ->leftJoin('car', 'car.id', '=', DB::raw("accounttransaction.car_id && car.customer_id=$customer_id"))
            ->where('accounttransaction.AccountID', $customer_account_id)
            ->where('accounttransaction.deleted', 0)
            ->whereNull('car.id')
            ->where('DateOfTransAction', '<', $date_from);

        $query->select(DB::raw($select));
        return $query->first();
    }

    public static function getGeneralTransactions($args)
    {
        $customer_account_id = $args['customer_account_id'];
        $customer_id = $args['customer_id'];
        $date_from = $args['date_from'];
        $date_to = $args['date_to'];

        $select = "accounttransaction.*, journal.serial_no";

        $query = DB::Table('accounttransaction')
            ->leftJoin('car', 'car.id', '=', DB::raw("accounttransaction.car_id && car.customer_id=$customer_id"))
            ->join('journal', 'journal.id', '=', 'accounttransaction.Journal_id')
            ->where('accounttransaction.AccountID', $customer_account_id)
            ->where('accounttransaction.deleted', 0)
            ->whereNull('car.id');

        if (!empty($date_from)) {
            $query->where('DateOfTransAction', '>=', $date_from);
        }

        if (!empty($date_to)) {
            $query->where('DateOfTransAction', '<=', $date_to);
        }

        $query->orderBy('accounttransaction.DateOfTransAction');

        $query->select(DB::raw($select));
        return $query->get();
    }

    public static function getAllCarsDetailsForStorage($cars_ids){

        $select = "car.final_payment_status,car.id,receive_car.deliver_status,car.vin";

        $query = DB::Table('car')
            ->leftJoin('receive_car', 'receive_car.car_id', '=', 'car.id')
            ->whereIn('car.id', $cars_ids)
            ->select(DB::raw($select));

        return $query->get();
    }

    public static function getDiscountFromTransaction($car_id, $account_id, $date_from, $date_to, $params=[]){

        $select = "SUM(accounttransaction.Credit) as totalCredit";
        $car_steps = array_merge(range(300,315), [15, 106, 108, 110, 116, 173]);

        if(empty($params['closingTable'])){
            $query = DB::Table('accounttransaction')
            ->join('journal', 'journal.id', '=', 'accounttransaction.journal_id');
        }
        else{
            $query = DB::Table('accounttransaction_closing_1', 'accounttransaction')
            ->join('journal_closing_1 as journal', 'journal.id', '=', 'accounttransaction.journal_id');
        }
            $query->leftJoin('car_total_cost', 'car_total_cost.car_id', '=', 'accounttransaction.car_id')
            ->where('accounttransaction.AccountID', $account_id)
            ->where('accounttransaction.car_id', $car_id)
            ->where('accounttransaction.deleted', '0')
            ->whereIn('accounttransaction.car_step', $car_steps)
            ->select(DB::raw($select));

        if($date_from){
            $query->whereRaw("IF(car_total_cost.car_id and car_total_cost.create_date >='$date_from',true,journal.date >='$date_from')");
        }

        if($date_to){
            $query->where('journal.date', '<=', $date_to);
        }

        return $query->first()->totalCredit;
    }
    public static function getPreviousBalanceCars($args)
    {
        $customer_account_id = $args['customer_account_id'];
        $customer_id = $args['customer_id'];
        $arrived_status = $args['arrived_status'];
        $date_from = $args['date_from'];

        $query = DB::Table('accounttransaction')
        ->join('car', 'car.id', '=', 'accounttransaction.car_id')
        ->select(DB::raw("GROUP_CONCAT(DISTINCT(accounttransaction.car_id) SEPARATOR  ',') as cars_id"))
        ->leftJoin('receive_car', 'receive_car.car_id','=','car.id')
        ->join('car_total_cost','car_total_cost.car_id','=','accounttransaction.car_id')
        ->where('accounttransaction.AccountID', $customer_account_id)
        ->where('accounttransaction.car_step','!=', '7')
        ->where('car.customer_id','=', $customer_id)
        ->whereRaw('(car.final_payment_status !=1 OR receive_car.deliver_status !=1)')
        ->where('arrive_store', '1')
        ->where('car_total_cost.create_date', '<', $date_from);
        $cars_id = Helpers::getRawSql($query);

        if(empty($args['closingTable'])){
            $query = DB::Table('accounttransaction')
            ->join('journal', 'journal.id', '=','accounttransaction.Journal_id');
        }
        else{
            $query = DB::Table('accounttransaction_closing_1', 'accounttransaction')
            ->join('journal_closing_1 as journal', 'journal.id', '=','accounttransaction.Journal_id');
        }

        $query->select(DB::raw('sum(accounttransaction.debit) as TotalDebit,sum(accounttransaction.credit) as TotalCredit,('.$cars_id.') as cars_id' ))
        ->join('car', 'car.id', '=','accounttransaction.car_id')
        ->where('accounttransaction.AccountID', $customer_account_id)
        ->where('journal.date' ,'<', $date_from);
    switch ($arrived_status) {
      case 0:
            $query->where('journal.date', '<', $date_from);
            break;

      case 1:
            $query->join('car_total_cost', function($join)
            {
                $join->on('car_total_cost.car_id', '=', 'accounttransaction.car_id');
                $join->on('accounttransaction.car_step','!=','7');
            })
            ->where('accounttransaction.car_step', '!=', '1')
            ->where('accounttransaction.type_transaction', '!=', '1')
            ->where('accounttransaction.type_transaction', '!=', '2')
            ->whereRaw("IF(car_total_cost.create_date >= '$date_from','',car_total_cost.create_date < '$date_from')");
            break;

      default:
        $query->join('car_total_cost', 'car_total_cost.car_id','=','accounttransaction.car_id')
        ->where('accounttransaction.deleted', '0')
        ->where('accounttransaction.car_step','!=', '7')
        ->where('accounttransaction.car_step', '!=', '1')
        ->where('accounttransaction.type_transaction', '!=', '1')
        ->where('accounttransaction.type_transaction', '!=', '2')
        ->whereRaw("if(car_total_cost.car_id,car_total_cost.create_date,journal.date) < '$date_from'");

        break;
    }
    return $query->first();
  }
    public static function getPreviousBalanceTransactions($args){

        $customer_account_id = $args['customer_account_id'];
        $customer_id = $args['customer_id'];
        $arrived_status = $args['arrived_status'];
        $date_from = $args['date_from'];
        $date_to = $args['date_to'];

        $select = "SUM(accounttransaction.Debit) as totalDebit,SUM(accounttransaction.Credit) as totalCredit";

        if(empty($args['closingTable'])){
            $query = DB::Table('accounttransaction')
            ->join('journal', 'journal.id', '=', 'accounttransaction.journal_id');
        }
        else{
            $query = DB::Table('accounttransaction_closing_1', 'accounttransaction')
            ->join('journal_closing_1 as journal', 'journal.id', '=', 'accounttransaction.journal_id');
        }

            $query->leftJoin('car_total_cost', 'car_total_cost.car_id', '=', 'accounttransaction.car_id')
            ->where('accounttransaction.AccountID', $customer_account_id)
            ->where('journal.date', '<', $date_from)
            ->where('accounttransaction.deleted', '0')
            ->select(DB::raw($select));

        if($arrived_status == 0){
            $query->join('car', 'car.id', '=', 'accounttransaction.car_id');
            $query->where('accounttransaction.car_step', '!=', '1');
            $query->where('accounttransaction.type_transaction', '!=', '1');
            $query->where('accounttransaction.type_transaction', '!=', '2');
            $query->where('car.deleted', '=', '0');
            $query->whereRaw("IF(car_total_cost.create_date,DATE(car_total_cost.create_date) > '$date_to',journal.date < '$date_from')");
        }
        else if($arrived_status == 1){
            $query->where(function($query){
                $query->where('accounttransaction.car_id', '0')
                ->orWhere('accounttransaction.car_step', '7')
                ->orWhere('accounttransaction.car_step', '1')
                ->orWhere('accounttransaction.type_transaction', '1')
                ->orWhere('accounttransaction.type_transaction', '2')
                ->orWhereRaw("(accounttransaction.type_transaction = '3' and accounttransaction.car_step = '0' and accounttransaction.car_id = '0')")
                ->orWhereRaw("(accounttransaction.type_transaction = '3' and accounttransaction.car_step = '0' and accounttransaction.Debit = '0' and accounttransaction.car_id is null)")
                ->orWhereRaw("(accounttransaction.type_transaction = '3' and accounttransaction.car_step = '0' and accounttransaction.Debit = '0' and accounttransaction.car_id > 0 and DATE(car_total_cost.create_date) > '$date' )");
            });

        }
        else if($arrived_status == 2){
            $query->where(function($query){
                $query->where('accounttransaction.car_id', '0')
                ->orWhere('accounttransaction.car_step', '7')
                ->orWhere('accounttransaction.car_step', '1')
                ->orWhere('accounttransaction.type_transaction', '1')
                ->orWhere('accounttransaction.type_transaction', '2');
            });
        }

        return $query->first();
    }

    public static function getPricesLists($customer_id){
        $file_path = Constants::NEJOUM_CDN . "upload/customer_file/";
        $select = [DB::raw("CONCAT('" . $file_path . "',customer_file.file_name) as file_url"),
                    "customer_file.file_name as name",
                    "site_advertisement.create_date as date",
                    DB::raw("IF(site_advertisement.list_type = 1,'Shipping', IF(site_advertisement.list_type = 2,'Towing',IF(site_advertisement.list_type = 3,'Loading',IF(site_advertisement.list_type = 4,'Clearance',IF(site_advertisement.list_type = 5,'Transportation',''))))) as list_type"),
                    "customer_file.expiry_date",
                    "customer_file.customer_file_id as id"];
        $query = DB::Table('customer_file')
                ->join('site_advertisement', 'site_advertisement.id','=','customer_file.ads_id')
                ->where([
                    ['customer_file.file_tag','=', 'price'],
                    ['customer_file.customer_id','=', $customer_id],
                    ['site_advertisement.deleted','=', '0'],
                    ['site_advertisement.type_id','=',2],
                    ['site_advertisement.expiry_date','>=', date("Y-m-d")],
                    ['customer_file.expiry_date','>=', date("Y-m-d")]
        ]);
        $query->select($select);
        return $query->get()->toArray();
}
    public  static function getStatesCount($customer_id){
        $args['customer_id'] = $customer_id;

        $newCarsNG = "(SELECT  COUNT(DISTINCT(car.id)) as num FROM `car` JOIN auction_location ON car.auction_location_id=auction_location.auction_location_id JOIN region ON auction_location.region_id=region.region_id WHERE `deleted`='0' AND `car`.`status`='1' AND `towingstatus`='0' AND `cancellation`='0' AND `region`.region_id='4'  AND  `customer_id` =".$customer_id." ) AS  newCarsNG, ";
        $newCarsTX = "(SELECT  COUNT(DISTINCT(car.id)) as num FROM `car` JOIN auction_location ON car.auction_location_id=auction_location.auction_location_id JOIN region ON auction_location.region_id=region.region_id WHERE `deleted`='0' AND `car`.`status`='1' AND `towingstatus`='0' AND `cancellation`='0' AND `region`.region_id='2'  AND  `customer_id` =".$customer_id." ) AS  newCarsTX, ";
        $newCarsCA = "(SELECT  COUNT(DISTINCT(car.id)) as num FROM `car` JOIN auction_location ON car.auction_location_id=auction_location.auction_location_id JOIN region ON auction_location.region_id=region.region_id WHERE `deleted`='0' AND `car`.`status`='1' AND `towingstatus`='0' AND `cancellation`='0' AND `region`.region_id='3'  AND  `customer_id` =".$customer_id." ) AS  newCarsCA, ";
        $newCarsWA = "(SELECT  COUNT(DISTINCT(car.id)) as num FROM `car` JOIN auction_location ON car.auction_location_id=auction_location.auction_location_id JOIN region ON auction_location.region_id=region.region_id WHERE `deleted`='0' AND `car`.`status`='1' AND `towingstatus`='0' AND `cancellation`='0' AND `region`.region_id='5'  AND  `customer_id` =".$customer_id." ) AS  newCarsWA, ";
        $newCarsGA = "(SELECT  COUNT(DISTINCT(car.id)) as num FROM `car` JOIN auction_location ON car.auction_location_id=auction_location.auction_location_id JOIN region ON auction_location.region_id=region.region_id WHERE `deleted`='0' AND `car`.`status`='1' AND `towingstatus`='0' AND `cancellation`='0' AND `region`.region_id='1' AND  `customer_id` =".$customer_id." ) AS  newCarsGA, ";

        //****/ warehouse cars
        $query = self::getQueryWarehouseCars(array_merge($args, ['region' => 1]))->selectRaw("COUNT(DISTINCT(car.id))");
        $newWearhouseCarsGA = "(".Helpers::getRawSql($query).") as newWearhouseCarsGA,";

        $query = self::getQueryWarehouseCars(array_merge($args, ['region' => 2]))->selectRaw("COUNT(DISTINCT(car.id))");
        $newWearhouseCarsTX = "(".Helpers::getRawSql($query).") as newWearhouseCarsTX,";

        $query = self::getQueryWarehouseCars(array_merge($args, ['region' => 3]))->selectRaw("COUNT(DISTINCT(car.id))");
        $newWearhouseCarsCA = "(".Helpers::getRawSql($query).") as newWearhouseCarsCA,";

        $query = self::getQueryWarehouseCars(array_merge($args, ['region' => 4]))->selectRaw("COUNT(DISTINCT(car.id))");
        $newWearhouseCarsNG = "(".Helpers::getRawSql($query).") as newWearhouseCarsNG,";

        $query = self::getQueryWarehouseCars(array_merge($args, ['region' => 5]))->selectRaw("COUNT(DISTINCT(car.id))");
        $newWearhouseCarsWA = "(".Helpers::getRawSql($query).") as newWearhouseCarsWA,";


        //****/ towing cars
        $query = self::getQueryTowedCars(array_merge($args, ['region' => 1]))->selectRaw("COUNT(DISTINCT(car.id))");
        $newLeftCarsGA = "(".Helpers::getRawSql($query).") as newLeftCarsGA,";

        $query = self::getQueryTowedCars(array_merge($args, ['region' => 2]))->selectRaw("COUNT(DISTINCT(car.id))");
        $newLeftCarsTX = "(".Helpers::getRawSql($query).") as newLeftCarsTX,";

        $query = self::getQueryTowedCars(array_merge($args, ['region' => 3]))->selectRaw("COUNT(DISTINCT(car.id))");
        $newLeftCarsCA = "(".Helpers::getRawSql($query).") as newLeftCarsCA,";

        $query = self::getQueryTowedCars(array_merge($args, ['region' => 4]))->selectRaw("COUNT(DISTINCT(car.id))");
        $newLeftCarsNG = "(".Helpers::getRawSql($query).") as newLeftCarsNG,";

        $query = self::getQueryTowedCars(array_merge($args, ['region' => 5]))->selectRaw("COUNT(DISTINCT(car.id))");
        $newLeftCarsWA = "(".Helpers::getRawSql($query).") as newLeftCarsWA,";



        $newLoadingCarsNG = "(SELECT  COUNT(DISTINCT(car.id)) as num FROM `car` LEFT JOIN arrived_car ON arrived_car.car_id=car.id LEFT JOIN posted_cars ON posted_cars.car_id=car.id  LEFT JOIN warehouse ON warehouse.warehouse_id=posted_cars.warehouse_id OR warehouse.warehouse_id=arrived_car.warehouse_id  JOIN region ON warehouse.region_id=region.region_id WHERE `car`.`deleted`='0' AND (`loading_status`='1' OR `shipping_status`='1') AND `arrived_port`='0' AND `region`.region_id='4'  AND  `customer_id` =".$customer_id." ) AS  newLoadingCarsNG, ";
        $newLoadingCarsTX = "(SELECT  COUNT(DISTINCT(car.id)) as num FROM `car` LEFT JOIN arrived_car ON arrived_car.car_id=car.id LEFT JOIN posted_cars ON posted_cars.car_id=car.id  LEFT JOIN warehouse ON warehouse.warehouse_id=posted_cars.warehouse_id OR warehouse.warehouse_id=arrived_car.warehouse_id  JOIN region ON warehouse.region_id=region.region_id WHERE `car`.`deleted`='0' AND (`loading_status`='1' OR `shipping_status`='1') AND `arrived_port`='0' AND `region`.region_id='2'  AND  `customer_id` =".$customer_id." ) AS  newLoadingCarsTX, ";
        $newLoadingCarsCA = "(SELECT  COUNT(DISTINCT(car.id)) as num FROM `car` LEFT JOIN arrived_car ON arrived_car.car_id=car.id LEFT JOIN posted_cars ON posted_cars.car_id=car.id  LEFT JOIN warehouse ON warehouse.warehouse_id=posted_cars.warehouse_id OR warehouse.warehouse_id=arrived_car.warehouse_id  JOIN region ON warehouse.region_id=region.region_id WHERE `car`.`deleted`='0' AND (`loading_status`='1' OR `shipping_status`='1') AND `arrived_port`='0' AND `region`.region_id='3'  AND  `customer_id` =".$customer_id." ) AS  newLoadingCarsCA, ";
        $newLoadingCarsWA = "(SELECT  COUNT(DISTINCT(car.id)) as num FROM `car` LEFT JOIN arrived_car ON arrived_car.car_id=car.id LEFT JOIN posted_cars ON posted_cars.car_id=car.id  LEFT JOIN warehouse ON warehouse.warehouse_id=posted_cars.warehouse_id OR warehouse.warehouse_id=arrived_car.warehouse_id  JOIN region ON warehouse.region_id=region.region_id WHERE `car`.`deleted`='0' AND (`loading_status`='1' OR `shipping_status`='1') AND `arrived_port`='0' AND `region`.region_id='5'  AND  `customer_id` =".$customer_id." ) AS  newLoadingCarsWA, ";
        $newLoadingCarsGA = "(SELECT  COUNT(DISTINCT(car.id)) as num FROM `car` LEFT JOIN arrived_car ON arrived_car.car_id=car.id LEFT JOIN posted_cars ON posted_cars.car_id=car.id  LEFT JOIN warehouse ON warehouse.warehouse_id=posted_cars.warehouse_id OR warehouse.warehouse_id=arrived_car.warehouse_id  JOIN region ON warehouse.region_id=region.region_id WHERE `car`.`deleted`='0' AND (`loading_status`='1' OR `shipping_status`='1') AND `arrived_port`='0' AND `region`.region_id='1'  AND  `customer_id` =".$customer_id." ) AS  newLoadingCarsGA, ";


        //****/ arrived port cars
        $query = self::getQueryPortCars(array_merge($args, ['region' => 1]))->selectRaw("COUNT(DISTINCT(car.id))");
        $newArrivePortCarsGA = "(".Helpers::getRawSql($query).") as newArrivePortCarsGA,";

        $query = self::getQueryPortCars(array_merge($args, ['region' => 2]))->selectRaw("COUNT(DISTINCT(car.id))");
        $newArrivePortCarsTX = "(".Helpers::getRawSql($query).") as newArrivePortCarsTX,";

        $query = self::getQueryPortCars(array_merge($args, ['region' => 3]))->selectRaw("COUNT(DISTINCT(car.id))");
        $newArrivePortCarsCA = "(".Helpers::getRawSql($query).") as newArrivePortCarsCA,";

        $query = self::getQueryPortCars(array_merge($args, ['region' => 4]))->selectRaw("COUNT(DISTINCT(car.id))");
        $newArrivePortCarsNG = "(".Helpers::getRawSql($query).") as newArrivePortCarsNG,";

        $query = self::getQueryPortCars(array_merge($args, ['region' => 5]))->selectRaw("COUNT(DISTINCT(car.id))");
        $newArrivePortCarsWA = "(".Helpers::getRawSql($query).") as newArrivePortCarsWA,";


        //****/ arrived port cars
        $query = self::getQueryCarsArrivedStore(array_merge($args, ['region' => 1]))->selectRaw("COUNT(DISTINCT(car.id))");
        $newArriveStoreCarsGA = "(".Helpers::getRawSql($query).") as newArriveStoreCarsGA";

        $query = self::getQueryCarsArrivedStore(array_merge($args, ['region' => 2]))->selectRaw("COUNT(DISTINCT(car.id))");
        $newArriveStoreCarsTX = "(".Helpers::getRawSql($query).") as newArriveStoreCarsTX,";

        $query = self::getQueryCarsArrivedStore(array_merge($args, ['region' => 3]))->selectRaw("COUNT(DISTINCT(car.id))");
        $newArriveStoreCarsCA = "(".Helpers::getRawSql($query).") as newArriveStoreCarsCA,";

        $query = self::getQueryCarsArrivedStore(array_merge($args, ['region' => 4]))->selectRaw("COUNT(DISTINCT(car.id))");
        $newArriveStoreCarsNG = "(".Helpers::getRawSql($query).") as newArriveStoreCarsNG,";

        $query = self::getQueryCarsArrivedStore(array_merge($args, ['region' => 5]))->selectRaw("COUNT(DISTINCT(car.id))");
        $newArriveStoreCarsWA = "(".Helpers::getRawSql($query).") as newArriveStoreCarsWA,";

        $states = DB::Table('car')
        ->select(DB::raw($newCarsNG.$newCarsTX.$newCarsCA.$newCarsWA.$newCarsGA.$newWearhouseCarsNG.$newWearhouseCarsTX.$newWearhouseCarsCA.$newWearhouseCarsWA.$newWearhouseCarsGA.
        $newLeftCarsNG.$newLeftCarsTX.$newLeftCarsCA.$newLeftCarsWA.$newLeftCarsGA.$newLoadingCarsNG.$newLoadingCarsTX.$newLoadingCarsCA.$newLoadingCarsWA.$newLoadingCarsGA.
        $newArrivePortCarsNG.$newArrivePortCarsTX.$newArrivePortCarsCA.$newArrivePortCarsWA.$newArrivePortCarsGA.$newArriveStoreCarsNG.$newArriveStoreCarsTX.$newArriveStoreCarsCA.$newArriveStoreCarsWA.$newArriveStoreCarsGA))
        ->take(1);
        return $states->first();
    }
    public static function getQueryCarsArrivedStore($args){
        $customer_id = $args['customer_id'];
        $search      = $args['search'];

        $query = DB::Table('car')
                ->leftJoin('external_car', 'car.id','=','external_car.car_id')
                ->leftJoin('car_make', 'car.id_car_make','=','car_make.id_car_make')
                ->leftJoin('buyer', 'car.buyer_id', '=', 'buyer.buyer_id')
                ->leftJoin('auction', 'car.auction_id','=','auction.id')
                ->leftJoin('auction_location', 'car.auction_location_id','=','auction_location.auction_location_id')
                ->leftJoin('auction_location as external_auction_location', 'external_car.auction_location_id','=','external_auction_location.auction_location_id')
                ->leftJoin('auction as external_auction', 'external_auction_location.auction_id','=','external_auction.id')
                ->leftJoin('region', 'auction_location.region_id','=','region.region_id')
                ->leftJoin('region as external_car_region', 'external_car.region_id','=','external_car_region.region_id')
                ->leftJoin('customer_appnotes', 'customer_appnotes.car_id','=','car.id')
                ->leftJoin('countries', 'countries.id','=','region.country_id')
                ->leftJoin('port', 'car.destination','=','port.port_id')
                ->leftJoin('car_model', 'car.id_car_model','=','car_model.id_car_model')
                ->leftJoin('vehicle_type', 'car.id_vehicle_type','=','vehicle_type.id_vehicle_type')
                ->leftJoin('customer', 'car.customer_id','=','customer.customer_id')
                ->leftJoin('receive_car', 'car.id','=','receive_car.car_id')
                ->leftJoin('color', 'car.color','=','color.color_id')
                ->leftJoin('container_car', 'container_car.car_id','=','car.id')
                ->leftJoin('container', 'container.container_id','=','container_car.container_id')
                ->leftJoin('booking', 'booking.booking_id','=','container_car.booking_id')
                ->leftJoin('booking_bl_container', 'container.booking_bl_container_id','=','booking_bl_container.booking_bl_container_id')
                ->leftJoin('loaded_status', 'loaded_status.booking_id','=','booking.booking_id')
                ->leftJoin('shipping_status', 'booking.booking_id','=','shipping_status.booking_id')
                ->leftJoin('arrived_car', 'arrived_car.car_id','=','car.id')
                ->leftJoin('car_total_cost', 'car.id','=','car_total_cost.car_id')
                ->leftJoin('towing_status', 'towing_status.car_id','=','car.id')
                ->leftJoin('bill_details', 'bill_details.car_id','=','car.id')
                ->leftJoin('cars_title_status', function ($leftJoin) {
                    $leftJoin->on(function($query){
                        $query->on('cars_title_status.car_id','=','car.id')
                        ->on('cars_title_status.follow_title','=',DB::raw('1'));
                    });
                    })
                ->where([
                    ['car.deleted','=', '0'],
                    ['arrive_store','=', '1'],
                    ['customer.customer_id','=', $customer_id]])
                ->when ($search , function ($query) use($search){
                    $query->where(function($query)  use($search){
                        $query->where('car.vin', 'like','%' . $search . '%')
                            ->orWhere('car.lotnumber', 'like','%' . $search . '%');
                    });
                });

        if($args['container_id']){
            // same api is used in containers page
            $query->where('container.container_id', $args['container_id']);
        }
        else{
            $query->where("deliver_customer", '0');
        }

        if (!empty($args['region'])) {
            $query->where(function($query)  use($args){
                $query->where('region.region_id', $args['region']);
                $query->orWhere('external_car_region.region_id', $args['region']);
            });
        }

        return $query;
    }
    public static function carsArrivedStoreDetailsCount($args){
        $query = self::getQueryCarsArrivedStore($args);
        $query->select(DB::raw('COUNT(DISTINCT(car.id)) as totalRecords'));
        return $query->first()->totalRecords;
    }
    public static function carsArrivedStoreDetailsd($args){
        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;
        $select = "region.short_name, car.* , car_make.name AS carMakerName , car_model.name AS carModelName, vehicle_type.name AS vehicleName,
                auction.title AS aTitle, IF(car.external_car='0', auction_location.auction_location_name, external_auction_location.auction_location_name) auction_location_name,IF(port.tag is not null,'".Constants::JEBEL_ALI_PORT_NAME."',port.port_name) port_name,
                customer.full_name AS CustomerName,countries.id as country,
                CAST(receive_car.create_date AS DATE) AS  receive_date,color.color_code,color.color_name,
                car_total_cost.total_price AS total_price,booking_bl_container.arrival_date as arrival_date,
                color.color_name,towing_status.picked_date,IF(car.external_car='0', region.region_name, external_car_region.region_name) as region,
                CAST(bill_details.create_date AS DATE) as paymentDate,arrived_car.*,cars_title_status.follow_title,
                cars_title_status.follow_car_title_note,booking.eta,booking.etd,
                CAST(cars_title_status.create_date AS DATE) as titleDate,loaded_status.loaded_date,
                booking.booking_number,booking.eta,container.container_number, buyer.buyer_number,
                shipping_status.shipping_date, customer_appnotes.notes as special_notes, IF(port.country_id = 229, 1, 0) isUAEPort,towing_status.picked_car_title_note";

        $query = self::getQueryCarsArrivedStore($args)
        ->groupBy('car.id')
        ->orderBy('car.create_date', 'desc');
        if($limit != 'all'){
            $query->skip($page * $limit)->take($limit);
        }
        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }
    public static function check_customer_have_account($customer_id){
        $query = DB::table('customer')
        ->select('account_id');
        if (!empty($customer_id)) {
            $query->where('customer.customer_id', $customer_id);
        }
        return $query->first()->account_id;
    }
    public static function getArrivedStoreCarsPhoto($car_id){
        $query = DB::table('car')
                ->Join('receive_car_photo', 'receive_car_photo.car_id', '=', 'car.id')
                ->select('receive_car_photo.*')
                ->where('car_id', $car_id);
        return $query->get()->toArray();
    }
    public static function  customer_account_id_by_car($car_id){
        $query = DB::table('car')
        ->select('*');
        if (!empty($car_id)) {
            $query->where('car.id', $car_id);
        }
        $num = $query->get()->toArray();
        foreach($num as $n){
            $q = DB::table('customer')->select('*');
            if (!empty($car_id)) {
                $q->where('customer.customer_id', $n->customer_id);
            }
            $customer = $q->get()->toArray();
        }
        return $customer[0]->account_id;
    }
    public static function getAccountTransaction($car_id, $AccountID){
        $select = "(SELECT SUM(accounttransaction.Debit) as total FROM `accounttransaction` WHERE (car_step = '5' or car_step = '6' or car_step = '103' or car_step = '102'  or car_step = '22222') and  accounttransaction.deleted=0  and  `car_id` =" . $car_id . " and AccountID = " . $AccountID . ") AS  Total, ";
        $select+= "(SELECT SUM(accounttransaction.Credit) as total FROM `accounttransaction` WHERE (car_step = '108') and  accounttransaction.deleted=0 and `car_id` =" . $car_id . " and AccountID = " . $AccountID . ") AS  CaleranceDiscount, ";
        $select+= "(SELECT SUM(accounttransaction.Debit) as total FROM `accounttransaction` WHERE (car_step = '8' or car_step = '10') and  accounttransaction.deleted=0  and  `car_id` =" . $car_id . " and AccountID = " . $AccountID . ") AS  TotalCalerance, ";
        $select+= "(SELECT SUM(accounttransaction.Debit) as TotalStorage FROM `accounttransaction` WHERE (car_step = '20') and  accounttransaction.deleted=0  and  `car_id` =" . $car_id . " and AccountID = " . $AccountID . ") AS  TotalStorage, ";
        $select+= "accounttransaction.*,account_home.AccountName AS AccountName";
        $query = DB::Table('accounttransaction')
                ->leftJoin('account_home', 'accounttransaction.AccountID','=','account_home.ID')
                ->where('AccountID', $AccountID)
                ->where('deleted', '0')
                ->where('car_id', $car_id)
                ->orWhere([
                    ['car_step','!=', '5'],
                    ['car_step','!=', '6'],
                    ['car_step','!=', '8'],
                    ['car_step','!=', '10'],
                    ['car_step','!=', '103'],
                    ['car_step','!=', '102'],
                    ['car_step','!=', '20'],
                    ['car_step','!=', '108'],
                    ['car_step','!=', '22222'],
                    ['car_step','!=', '170'],
                    ['car_step','!=', '171'],
                    ['car_step','!=', '173'],
                    ['car_step','!=', '174'],
                    ['car_step','!=', '1998'],
                    ['car_step','!=', '1999'],
                    ]);
        $query->select(DB::raw($select));
        $result_array = $query->get()->toArray();
        if(empty($result_array)){
            $GetTotal = '(SELECT SUM(accounttransaction.Debit) as total FROM `accounttransaction` WHERE (car_step = 5 or car_step = 6 or car_step = 103 or car_step = 102  or car_step = 22222) and  accounttransaction.deleted=0  and  car_id = '. $car_id .'and AccountID = '.$AccountID;
            $TotalCaleranceDiscount= "SELECT SUM(accounttransaction.Credit) as total FROM `accounttransaction` WHERE (car_step = '108') and  accounttransaction.deleted=0 and car_id = $car_id and AccountID = $AccountID";
            $TotalCalerance= "SELECT SUM(accounttransaction.Debit) as total FROM `accounttransaction` WHERE (car_step = '8' or car_step = '10') and  accounttransaction.deleted=0  and  car_id = $car_id and AccountID = $AccountID";
            $TotalStorage= "SELECT SUM(accounttransaction.Debit) as TotalStorage FROM `accounttransaction` WHERE (car_step = '20') and  accounttransaction.deleted=0  and  car_id = $car_id and AccountID = $AccountID";
            $GetTotal = DB::Table('accounttransaction')->select($GetTotal)->first()->total;
            $TotalCaleranceDiscount = DB::Table('accounttransaction')->select($TotalCaleranceDiscount)->first()->total;
            $TotalCalerance = DB::Table('accounttransaction')->select($TotalCalerance)->first()->total;
            $TotalStorage = DB::Table('accounttransaction')->select($TotalStorage)->first()->TotalStorage;
            $result_array = [
                [
                'Total' => $GetTotal,
                'TotalCalerance' => $TotalCalerance,
                'CaleranceDiscount' => $TotalCaleranceDiscount,
                'TotalStorage' => $TotalStorage,
                ]
                ];
                return $result_array;
        }
        return $result_array;
    }
    public static function getAllTransation2($args){
        $customer_id = $args['customer_id'];
        $arrived_status = $args['arrived_status'];
        $customer_account_id = $args['customer_account_id'];
        $date_from = $args['date_from'];
        $date_to = $args['date_to'];
        $select = "accounttransaction.*,car.lotnumber AS lotnumber";

        if(empty($args['closingTable'])){
            $query = DB::Table('accounttransaction');
        }
        else{
            $query = DB::Table('accounttransaction_closing_1', 'accounttransaction');
        }

        $query->leftJoin('car', 'car.id', '=', 'accounttransaction.car_id')
        ->where('accounttransaction.AccountID', $customer_account_id)
        ->where('accounttransaction.deleted', 0)
        ->orderBy("DateOfTransAction");
        if($arrived_status == 1)
        {
            $query->where(function($query) use ($customer_id){
                $query->whereRaw(" ((type_transaction = 3 and car_step = 0 && Debit = 0) OR (type_transaction = 3 and car_step > 1 and accounttransaction.car_id = 0 && Debit > 0) OR (type_transaction = 3 and car_step = 0 and accounttransaction.car_id = 0) OR (type_transaction = 3 and Debit = 0 and car_step > 0 and car_step  != 106 and car_step  != 300 and car_step  != 301 and car_step  != 302  and car_step  != 303 and car_step  != 304 and car_step  != 305 and car_step  != 306 and car_step  != 307 and car_step  != 308 and car_step  != 309 and car_step  != 310 and car_step  != 108 and car_step  != 116 and car_step  != 110 and  car_step  != 111 and  car_step  != 311  and car_step!= 312 and car_step  != 313 and car_step  != 314 and  car_step  != 173 and car_step  != 15) OR (type_transaction = 3 and Debit = 0 and car_step > 1 and accounttransaction.car_id = 0) OR (type_transaction = 3 and car_step = 1)  OR (type_transaction = 1 or type_transaction = 2))");
                $query->orWhereRaw("(type_transaction = 3 and car_step > 0 and accounttransaction.car_id > 0 and car.customer_id != $customer_id )");
            });
        }
        if($arrived_status == 2){
            $query->where(function($query) use ($customer_id){
                $query->whereRaw(" ((type_transaction = 3 and car_step = 0 && Debit = 0) OR (type_transaction = 3 and car_step > 1 and accounttransaction.car_id = 0 && Debit > 0) OR (type_transaction = 3 and car_step = 0 and accounttransaction.car_id = 0) OR (type_transaction = 3 and Debit = 0 and car_step > 0 and car_step  != 106 and car_step  != 300 and car_step  != 301 and car_step  != 302  and car_step  != 303 and car_step  != 304 and car_step  != 305 and car_step  != 306 and car_step  != 307 and car_step  != 308 and car_step  != 309 and car_step  != 310 and car_step  != 108 and car_step  != 116 and car_step  != 110 and  car_step  != 111 and  car_step  != 311  and car_step!= 312 and car_step  != 313 and car_step  != 314 and  car_step  != 173 and car_step  != 15) OR (type_transaction = 3 and Debit = 0 and car_step > 1 and accounttransaction.car_id = 0) OR (type_transaction = 3 and car_step = 1)  OR (type_transaction = 1 or type_transaction = 2))");
                $query->orWhereRaw("(type_transaction = 3 and car_step > 0 and accounttransaction.car_id > 0 and car.customer_id != $customer_id )");
            });
        }
        if ($date_from) {
            $query->where('DateOfTransAction','>=',$date_from);
        }
        if ($date_to) {
            $query->where('DateOfTransAction','<=',$date_to);
        }

        if(empty($args['closingTable']) && $date_from <= $args['closed_date']){
            $query->where('accounttransaction.car_step', '!=', Constants::CAR_STEPS['CLOSING_CUSTOMER_BALANCE']);
        }

        $query->select(DB::raw($select));
        return collect($query->get())->map(function ($x) {
            return (array) $x;
        })->toArray();
    }
    public static function make_datatablesView($args){
        $arrived_status = $args['arrived_status'];
        $customer_account_id = $args['customer_account_id'];
        $customer_id = $args['customer_id'];
        $date_from = $args['date_from'];
        $date_to = $args['date_to'];

        $select = "car.*,car_total_cost.create_date as received_create_date,car_sell_price.Discount,receive_car.deliver_status,SUM(accounttransaction.Debit) Debit";

        if(empty($args['closingTable'])){
            $query = DB::Table('accounttransaction')
            ->join('journal', 'journal.id', '=', 'accounttransaction.journal_id');
        }
        else{
            $query = DB::Table('accounttransaction_closing_1', 'accounttransaction')
            ->join('journal_closing_1 as journal', 'journal.id', '=', 'accounttransaction.journal_id');
        }

        $query->leftJoin('car', 'car.id', '=', 'accounttransaction.car_id')
        ->join('car_total_cost', 'car_total_cost.car_id', '=', 'accounttransaction.car_id')
        ->leftJoin('car_sell_price', 'car_sell_price.car_id','=','car.id')
        ->leftJoin('receive_car', 'receive_car.car_id','=','car.id')
        ->where('accounttransaction.AccountID', $customer_account_id)
        ->where('accounttransaction.deleted','=', '0')
        ->where('car.deleted','=', '0')
        ->where('accounttransaction.car_step','!=', '1')
        ->where('car.customer_id','=', $customer_id)
        ->where('accounttransaction.type_transaction','!=', '2')
        ->orderBy("car_total_cost.create_date","ASC")
        ->groupBy("accounttransaction.car_id");

        if ($date_from) {
            $query->whereRaw("Date(car_total_cost.create_date) >= '$date_from'");
        }

        if ($date_to) {
            $query->whereRaw("Date(car_total_cost.create_date) <= '$date_to'");
            $query->where('journal.date','<=', $date_to);
        }


        return $query->select(DB::raw($select))->get();
    }
    public static function allCarsDetailsQuery($args) {
        $search = $args['search'];
        $customer_id = $args['customer_id'];
        $query = DB::Table('car')
        ->leftJoin('external_car', 'car.id','=','external_car.car_id')
        ->leftJoin('car_make', 'car.id_car_make', '=', 'car_make.id_car_make')
        ->leftJoin('buyer', 'car.buyer_id', '=', 'buyer.buyer_id')
        ->leftJoin('auction', 'car.auction_id', '=', 'auction.id')
        ->leftJoin('auction_location', 'car.auction_location_id','=','auction_location.auction_location_id')
        ->leftJoin('auction_location as external_auction_location', 'external_car.auction_location_id','=','external_auction_location.auction_location_id')
        ->leftJoin('auction as external_auction', 'external_auction_location.auction_id','=','external_auction.id')
        ->leftJoin('region', 'auction_location.region_id','=','region.region_id')
        ->leftJoin('region as external_car_region', 'external_car.region_id','=','external_car_region.region_id')
        ->leftJoin('countries', 'countries.id','=','region.country_id')
        ->leftJoin('port', 'car.destination','=','port.port_id')
        ->leftJoin('customer_appnotes', 'customer_appnotes.car_id','=','car.id')
        ->leftJoin('car_model', 'car.id_car_model','=','car_model.id_car_model')
        ->leftJoin('vehicle_type', 'car.id_vehicle_type','=','vehicle_type.id_vehicle_type')
        ->leftJoin('customer', 'car.customer_id','=','customer.customer_id')
        ->leftJoin('arrived_car', 'arrived_car.car_id','=','car.id')
        ->leftJoin('receive_car', 'car.id','=','receive_car.car_id')
        ->leftJoin('shipping_order_car', 'car.id','=','shipping_order_car.car_id')
        ->leftJoin('shipping_order', 'shipping_order.shipping_order_id','=','shipping_order_car.shipping_order_id')
        ->leftJoin('port as port_departure', 'port_departure.port_id','=','shipping_order.take_off_port_id')
        ->leftJoin('container_car', 'container_car.car_id','=','car.id')
        ->leftJoin('container', 'container.container_id','=','container_car.container_id')
        ->leftJoin('booking', 'booking.booking_id','=','container_car.booking_id')
        ->leftJoin('booking_bl_container', 'booking_bl_container.booking_id','=','booking.booking_id')
        ->leftJoin('customer_file', 'customer_file.customer_file_id','=','booking_bl_container.bl_attach_id')
        ->leftJoin('loaded_status', 'loaded_status.booking_id','=','booking.booking_id')
        ->leftJoin('shipping_status', 'booking.booking_id','=','shipping_status.booking_id')
        ->leftJoin('color', 'car.color','=','color.color_id')
        ->leftJoin('towing_status', 'towing_status.car_id','=','car.id')
        ->leftJoin('bill_details', 'bill_details.car_id','=','car.id')
        ->leftJoin('special_port_receivable_info', 'special_port_receivable_info.car_id','=','car.id')
        ->leftJoin('cars_title_status', function ($join) {
            $join->on('cars_title_status.car_id', '=', 'car.id')
                 ->where('cars_title_status.follow_title', '=', 1);
        })
        ->where('car.deleted','=', '0')
        ->where('car.cancellation','=', '0')
        ->where('car.customer_id','=', $customer_id)
        ->when ($search , function ($query) use($search){
            $query->where(function($query)  use($search){
                $query->where('car.vin', $search)
                    ->orWhere('car.lotnumber', $search);
            });
        });

        if (!empty($args['region'])) {
            $query->where(function($query)  use($args){
                $query->where('region.region_id', $args['region']);
                $query->orWhere('external_car_region.region_id', $args['region']);
            });
        }

        return $query;
    }
    public static function allCarsDetails($args)
    {
        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;
        $order = !empty($args['order']) ? $args['order'] : '';
        $customer_id = $args['customer_id'];
        $query = self::allCarsDetailsQuery($args)
                        ->groupBy('car.id');

        if ($order) {
            $order = Helpers::getOrder($order);
            if ($order) {
                if($order['col'] == 'delivered_car_key'){
                    $query->orderBy(DB::raw('FIELD(delivered_car_key, "1", "0", "2")'), $order['dir']);
                }else{
                    $query->orderBy($order['col'], $order['dir']);
                }
            }else{
                $query->orderBy('purchasedate', 'desc');
            }
        }else{
            $query->orderBy('purchasedate', 'asc');
        }
        $select = 'car.for_sell,car.totalcarcost,car.carcost, car.lotnumber,car.vin,car.year, car.purchasedate,car.auction_location_id,car.photo , car_make.name AS carMakerName , car_model.name AS carModelName, vehicle_type.name AS vehicleName, '
        . "auction.title AS aTitle, IF(car.external_car='0', auction_location.auction_location_name, external_auction_location.auction_location_name) auction_location_name,region.region_name ,IF(port.tag is not null,'".Constants::JEBEL_ALI_PORT_NAME."',port.port_name) port_name,color.color_name,CAST(bill_details.create_date AS DATE) as paymentDate,towing_status.picked_date,towing_status.picked_car_title_note,arrived_car.delivered_date,arrived_car.delivered_title,arrived_car.delivered_car_key,arrived_car.car_id,CAST(cars_title_status.create_date AS DATE) as titleDate,cars_title_status.follow_car_title_note,
            loaded_status.loaded_date,container.container_number,customer_appnotes.notes as special_notes,booking.booking_number,booking.eta,booking.etd, shipping_status.shipping_date,
            port_departure.port_name as departurePort,CAST(receive_car.create_date AS DATE) AS  receive_date,car.final_payment_status,customer_file.file_name,car.invoice_file_auction,
            buyer.buyer_number,car.loading_status as car_loading_status,car.shipping_status as car_shipping_status, port.port_id as port_id,car.id as carId, car.arrivedstatus,
            special_port_receivable_info.customer_name port_receiver_customer_name, IF(port.country_id = 229, 1, 0) isUAEPort,
            CASE 
            WHEN car.deliver_customer = '1' THEN 'Delivered'
            WHEN car.arrive_store = '1' THEN 'Store'
            WHEN car.arrived_port = '1' THEN 'Port'
            WHEN car.shipping_status = '1' THEN 'Shipping'
            WHEN car.loading_status = '1' THEN 'Loading'
            END AS tracking_stage
            ";


        if($limit != 'all'){
            $query->skip($page * $limit)->take($limit);
        }

        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }
    public static function allCarsDetailsCount($args)
    {
        $query = self::allCarsDetailsQuery($args);
        $query->select(DB::raw('COUNT(DISTINCT(car.id)) as totalRecords'));
        return ($query->first()->totalRecords);
    }

    public static function getCarsWeight($cars_list){
        $cars_list = explode(',', $cars_list);
        return DB::connection('mysql2')->table('from_warehouse_cars_status_update')
        ->whereIn('vin', $cars_list)
        ->sum('vehicle_weight');
    }

    public static function getCronStorageFines($args){
        $query = DB::table('car_storage_fine')
        ->select(DB::raw('car_storage_fine.car_id, car_storage_fine.amount'))
        ->where('car_storage_fine.amount', '>', '0');

        if($args['customer_id']){
            $query->join('car', 'car_storage_fine.car_id', 'car.id')
            ->where('car.customer_id', $args['customer_id']);
        }

        $carStorageFinesDB = $query->get()->toArray();
        $carStorageFines = [];
        foreach($carStorageFinesDB as $key=>$row){
            $row = (array)$row;
            $carStorageFines[ $row['car_id'] ] = $row['amount'];
        }
        return $carStorageFines;
    }

    public static function getCarsClosingBalance($args){
        $query = DB::table('cars_closing_balance')
        ->select(DB::raw('cars_closing_balance.*'));

        if($args['customer_id']){
            $query->join('car', 'cars_closing_balance.car_id', 'car.id')
            ->where('car.customer_id', $args['customer_id']);
        }

        $carsClosingBalanceDB = $query->get()->toArray();
        $carsClosingBalance = [];
        foreach($carsClosingBalanceDB as $row){
            $row = (array)$row;
            $carsClosingBalance[ $row['car_id'] ] = $row;
        }
        return $carsClosingBalance;
    }

    public static function getTotalPreviousStorage($args){
        $query = DB::table('car_storage_fine')
        ->select(DB::raw('SUM(car_storage_fine.amount) totalFine'))
        ->join('car', 'car_storage_fine.car_id', 'car.id')
        ->join('car_total_cost', 'car_total_cost.car_id', 'car.id')
        ->leftJoin('receive_car', 'receive_car.car_id', 'car.id')
        ->where('car.customer_id', $args['customer_id'])
        ->whereRaw("DATE(car_total_cost.create_date) <= '{$args['date_to']}'");

        if($args['showen_cars_id']){
            $query->whereNotIn('car_storage_fine.car_id', $args['showen_cars_id']);
        }

        return $query->get()->first()->totalFine;
    }

    public static function carsInfoClosingRemaining($cars){
        if(empty($cars)) return[];

        $select = "car.*,DATE(car_total_cost.create_date) as display_car_date,receive_car.deliver_status,
        car_make.name AS carMakerName , car_model.name AS carModelName,vehicle_type.name AS vehicleName,color.color_code, container.container_number";

        $query = DB::table('car')
        ->join('car_total_cost', 'car_total_cost.car_id', '=', 'car.id')
        ->leftJoin('car_make', 'car.id_car_make', '=', 'car_make.id_car_make')
        ->leftJoin('car_model', 'car.id_car_model', '=', 'car_model.id_car_model')
        ->leftJoin('vehicle_type', 'car.id_vehicle_type', '=', 'vehicle_type.id_vehicle_type')
        ->leftJoin('color', 'color.color_id', '=', 'car.color')
        ->leftJoin('receive_car', 'receive_car.car_id', '=', 'car.id')
        ->leftJoin('container_car', 'container_car.car_id','=','car.id')
        ->leftJoin('container', 'container.container_id','=','container_car.container_id')
        ->whereIn('car.id', $cars)
        ->where('car.final_payment_status', '0')
        ->groupBy("car.id")
        ->orderBy("car_total_cost.create_date");

        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }

    public static function carsInfoStorageFineRemaining($customer_id, $date_from, $date_to, $showen_cars=[]){

        $select = "car.*,DATE(car_total_cost.create_date) as display_car_date,receive_car.deliver_status,car_make.name AS carMakerName , car_model.name AS carModelName,vehicle_type.name AS vehicleName,color.color_code,
        car_storage_fine.amount as fine_value, container.container_number";

        $query = DB::table('car')
        ->join('car_total_cost', 'car_total_cost.car_id', '=', 'car.id')
        ->join('car_storage_fine', 'car_storage_fine.car_id', '=', 'car.id')
        ->leftJoin('car_make', 'car.id_car_make', '=', 'car_make.id_car_make')
        ->leftJoin('car_model', 'car.id_car_model', '=', 'car_model.id_car_model')
        ->leftJoin('vehicle_type', 'car.id_vehicle_type', '=', 'vehicle_type.id_vehicle_type')
        ->leftJoin('color', 'color.color_id', '=', 'car.color')
        ->leftJoin('receive_car', 'receive_car.car_id', '=', 'car.id')
        ->leftJoin('container_car', 'container_car.car_id','=','car.id')
        ->leftJoin('container', 'container.container_id','=','container_car.container_id')
        ->where('car.customer_id', $customer_id)
        ->where('car_storage_fine.amount', '>', '0')
        ->groupBy("car.id")
        ->orderBy("car_total_cost.create_date");

        if(!empty($showen_cars)){
            $query->whereNotIn('car.id', $showen_cars);
        }

        if($date_from){
            $query->whereDate('car_total_cost.create_date', '>=', $date_from);
        }

        if($date_to){
            $query->whereDate('car_total_cost.create_date', '<=', $date_to);
        }

        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }

    public static function getCarContract($car_id){
        $query = DB::table('car')
        ->select('*')
        ->join('customer_contract', function($join) {
            $join->on('customer_contract.customer_id', '=', 'car.customer_id');
            $join->where('customer_contract.status', '=', '1');
            $join->where(function ($q) {
                $q->whereRaw("(car.purchasedate AND customer_contract.start_date <= car.purchasedate AND (customer_contract.end_date >= car.purchasedate  OR  customer_contract.end_date IS NULL))");
                $q->orWhereRaw("(NULLIF(car.purchasedate, ' ') IS NULL AND customer_contract.start_date <= DATE(car.create_date) AND (customer_contract.end_date >= DATE(car.create_date) OR customer_contract.end_date IS NULL))");
            });
        })
        ->where('car.id',$car_id);
        return $query->get()->first();
    }

}
