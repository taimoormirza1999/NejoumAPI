<?php


namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Libraries\Constants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\DatabaseManager;
use App\Models\Accounting;
use App\Libraries\Helpers;
use App\Services\CarService;
use App\Services\AccountingService;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class StatementController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */



    public function __construct()
    {
        $this->customer_id = Auth::user()->customer_id;
    }

    public function getCarReportPreviousBalance($args)
    {
        $customer_id = $args['customer_id'];
        $customer_account_id = $args['customer_account_id'];
        $arrived_status = $args['arrived_status'];
        $date_from = $args['date_from'];
        $date_to = $args['date_to'];
        $remaining_status = $args['remaining_status'];
        $paid_status = $args['paid_status'];
        $showen_cars_id = $args['showen_cars_id'];
        $closed_date = Helpers::get_closed_date();

        if ($date_from <= '2020-01-01') {
            return [];
        }

        if ($arrived_status == 0) {
            $previousBalance = CarService::getPreviousBalanceTransactions($args);
            $previousBalanceClosing = CarService::getPreviousBalanceTransactions(array_merge($args, ['closingTable' => 1]));
            $previousBalance->totalDebit += $previousBalanceClosing->totalDebit;
            $previousBalance->totalCredit += $previousBalanceClosing->totalCredit;
            $totalDebit = $previousBalance->totalDebit;
            $totalCredit = $previousBalance->totalCredit;
            $Previous_Balance = $totalDebit - $totalCredit;

            $one_row = array();
            $one_row['index'] = '#';
            $one_row['date'] = '#';
            $one_row['lotnumber'] = '#';
            $one_row['details'] = 'Previous Balance';
            $one_row['storage_fine'] = 0;
            $one_row['car_price'] = '#';
            $one_row['shipping_amount'] = '#';
            $one_row['debit'] = $totalDebit;
            $one_row['credit'] = $totalCredit;
            $one_row['balance'] = $Previous_Balance;
            $one_row['remaining'] = $Previous_Balance;
            $previousBalances['completedCars'] = $one_row;

            return $previousBalances;
        }

        $date_to = date('Y-m-d', strtotime('-1 day', strtotime($date_from)));
        $date_from = '2020-01-01';
        $args['date_from'] = $date_from;
        $args['date_to'] = $date_to;
        $args['closed_date'] = $closed_date;
        $args['previous_balance_function'] = true;


        $fetch_data = CarService::shippedCarsData($args);
        $transactionAfterCompleted = CarService::transactionAfterCompleted($args);
        $getAllTransation = CarService::getAllTransation2CarsStatement(array_merge($args, ['excludeOpeningJournal' => 1]));

        if($date_to < $closed_date){
            $args_closing = $args;
            $args_closing['closingTable'] = 1;
            $args_closing['excludeOpeningJournal'] = 1;
            $fetch_data_closing = CarService::shippedCarsData($args_closing);
            $transactionAfterCompleted_closing = CarService::transactionAfterCompleted($args_closing);
            $getAllTransation_closing = CarService::getAllTransation2CarsStatement($args_closing);
            $fetch_data = array_merge($fetch_data, $fetch_data_closing);
            $transactionAfterCompleted = array_merge($transactionAfterCompleted, $transactionAfterCompleted_closing);
            $getAllTransation = array_merge($getAllTransation, $getAllTransation_closing);
        }

        $fetch_data = array_merge($fetch_data, $transactionAfterCompleted);
        if($date_to < $closed_date){
            // if car has transaction in both closing & active tables
            // it will come 2 times: merge the values
            $unset_array_keys = [];
            foreach($fetch_data as $key => $row){
                foreach($fetch_data as $subKey => $subRow){
                    if($row->id == $subRow->id && $subKey > $key){
                        $row->Debit += $subRow->Debit;
                        $row->Credit += $subRow->Credit;
                        $unset_array_keys [] = $subKey;
                    }
                }
                $fetch_data[$key] = $row;
            }
            foreach($unset_array_keys as $key){
                unset($fetch_data[$key]);
            }
        }

        if ($arrived_status == 0) {
            $getAllTransation = [];
        }

        $carsCompletedLotMapping = [];
        foreach ($fetch_data as $key => $row) {
            $carsCompletedLotMapping[$row->lotnumber] = $key;
            if (empty($row->Credit)) {
                $fetch_data[$key]->Credit = 0;
            }
        }

        $transferTransactionsCompleted = [];
        $transferTransactionsInAuction = [];

        foreach ($getAllTransation as $key => $row) {
            if ((($row['type_transaction'] == 1 && $row['car_step'] == 0 && $row['car_id'] > 0) || ($row['car_step'] == 1 && $row['car_id'] > 0)) && $row['car_customer_id'] == $customer_id) {
                $lotnumber = $row['lotnumber'];
                if (!empty($row['completed_date']) && $row['completed_date'] < $date_to) {
                    $transferTransactionsCompleted[$key] = $row;
                } else {
                    if (empty($transferTransactionsInAuction[$lotnumber])) {
                        $transferTransactionsInAuction[$lotnumber] = $row;
                    } else {
                        $transferTransactionsInAuction[$lotnumber]['Debit'] += $row['Debit'];
                        $transferTransactionsInAuction[$lotnumber]['Credit'] += $row['Credit'];
                    }
                }
                unset($getAllTransation[$key]);
            }
        }

        foreach ($transferTransactionsCompleted as $key => $row) {
            $carIndex = $carsCompletedLotMapping[$row['lotnumber']];
            if(empty($fetch_data[$carIndex])){
                $fetch_data[$carIndex] = new stdClass();
                $fetch_data[$carIndex]->Debit = 0;
                $fetch_data[$carIndex]->Credit = 0;
            }
            $fetch_data[$carIndex]->Debit += $row['Debit'];
            $fetch_data[$carIndex]->Credit += $row['Credit'];
        }

        $previousBalances = [];
        $totalDebit = $totalCredit = $storagePrevious = 0;
        foreach ($fetch_data as $key => $row) {
            $row->Discount = CarService::getDiscountFromTransaction($row->id, $customer_account_id, $date_from, $date_to);
            if($date_to < $closed_date){
                $row->Discount += CarService::getDiscountFromTransaction($row->id, $customer_account_id, $date_from, $date_to, ['closingTable' => 1]);
            }
            $row->Debit -= $row->Discount;

            $totalDebit += $row->Debit;
            $totalCredit += $row->Credit;
        }

        $storagePrevious += CarService::getTotalPreviousStorage(['customer_id' => $customer_id, 'date_from' => $date_from, 'date_to' => $date_to, 'showen_cars_id' => $showen_cars_id]);

        $totalDebit += $storagePrevious;
        if ($totalDebit || $totalCredit) {
            $Previous_Balance = $totalDebit - $totalCredit;
            $one_row = array();
            $one_row['index'] = '#';
            $one_row['date'] = '#';
            $one_row['lotnumber'] = '#';
            $one_row['description'] = 'Previous Balance';
            $one_row['storage_fine'] = $storagePrevious;
            $one_row['car_price'] = '#';
            $one_row['shipping_amount'] = '#';
            $one_row['debit'] = $totalDebit;
            $one_row['credit'] = $totalCredit;
            $one_row['balance'] = $Previous_Balance;
            $one_row['remaining'] = $Previous_Balance;
            $previousBalances['completedCars'] = $one_row;
        }

        if ($arrived_status != 0 && !empty($transferTransactionsInAuction)) {
            $totalDebit = $totalCredit = 0;
            foreach ($transferTransactionsInAuction as $key => $value) {
                $totalDebit += $value['Debit'];
                $totalCredit += $value['Credit'];
            }

            if ($totalDebit || $totalCredit) {
                $Previous_Balance = $totalDebit - $totalCredit;
                $one_row = array();
                $one_row['index'] = '#';
                $one_row['date'] = '#';
                $one_row['lotnumber'] = '#';
                $one_row['ref'] = '#';
                $one_row['details'] = 'Previous Balance';
                $one_row['debit'] = $totalDebit;
                $one_row['credit'] = $totalCredit;
                $one_row['balance'] = $Previous_Balance;
                $one_row['remaining'] = $Previous_Balance;
                $previousBalances['inAuctions'] = $one_row;
            }

        }

        if ($arrived_status  != -1) {
            $totalDebit = $totalCredit = 0;
            foreach ($getAllTransation as $key => $value) {
                $totalDebit += $value['Debit'];
                $totalCredit += $value['Credit'];
            }

            if ($totalDebit || $totalCredit) {
                $Previous_Balance = $totalDebit - $totalCredit;
                $one_row = array();
                $one_row['index'] = '#';
                $one_row['date'] = '#';
                $one_row['lotnumber'] = '#';
                $one_row['ref'] = '#';
                $one_row['details'] = 'Previous Balance';
                $one_row['debit'] = $totalDebit;
                $one_row['credit'] = $totalCredit;
                $one_row['balance'] = $Previous_Balance;
                $one_row['remaining'] = $Previous_Balance;
                $previousBalances['generalEntries'] = $one_row;
            }

        }

        return $previousBalances;
    }

    public function carStatementShippedCars(Request $request)
    {
        $customer_id = $this->customer_id;
        $arrived_status = $request->arrived_status;
        $date_from = $request->date_from;
        $date_to = $request->date_to;
        $remaining_status = $request->remaining_status;
        $transfer_status = $request->transfer_status;
        $paid_status = $request->paid_status;
        $search = $request->search;
        $date_from = empty($request->date_from) ? date('Y-01-01') : $request->date_from;
        $date_from = '2020-01-01';
        $date_to = empty($request->date_to) ? date('Y-m-d') : $request->date_to;
        $currency = empty($request->currency) ? 'aed' : $request->currency;
        $exchange_rate = $currency == 'usd' ? Helpers::get_usd_rate() : 1;
        $user_language = empty($request->user_language) ? 'en' : $request->user_language;
        $closed_date = Helpers::get_closed_date();
        $customer_account_id = Helpers::get_customer_account_id($customer_id);

        $args = [
            'customer_id' => $customer_id,
            'customer_account_id' => $customer_account_id,
            'arrived_status' => $arrived_status,
            'paid_status' => $paid_status,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'search' => $search,
            'closed_date' => $closed_date,
        ];

        $carStorageFines = CarService::getCronStorageFines(['customer_id' => $customer_id]);
        $carsClosingBalance = [];
        $carsClosingBalanceRemaining = [];
        if($date_from > $closed_date){
            $carsClosingBalance = CarService::getCarsClosingBalance(['customer_id' => $customer_id]);
        }

        if($date_from == date('Y-m-d', strtotime($closed_date.' +1 day'))){
            foreach($carsClosingBalance as $key => $row){
                if(strval($row['total_debit']) != strval($row['total_credit'])){
                    $carsClosingBalanceRemaining[$key] = $row;
                }
            }
        }

        $fetch_data = CarService::shippedCarsData($args);
        $transactionAfterCompleted = CarService::transactionAfterCompleted($args);
        $getAllTransation = CarService::getAllTransation2CarsStatement($args);

        if($date_from <= $closed_date){
            $args_closing = $args;
            $args_closing['closingTable'] = 1;
            $args_closing['excludeOpeningJournal'] = 1;
            $fetch_data_closing = CarService::shippedCarsData($args_closing);
            $transactionAfterCompleted_closing = CarService::transactionAfterCompleted($args_closing);
            $getAllTransation_closing = CarService::getAllTransation2CarsStatement($args_closing);
            $fetch_data = array_merge($fetch_data, $fetch_data_closing);
            $transactionAfterCompleted = array_merge($transactionAfterCompleted, $transactionAfterCompleted_closing);
            $getAllTransation = array_merge($getAllTransation, $getAllTransation_closing);
        }

        $fetch_data = array_merge($fetch_data, $transactionAfterCompleted);
        // if car has transaction in both closing & active tables
        // it will come 2 times: merge the values
        $unset_array_keys = [];
        foreach($fetch_data as $key => $row){
            foreach($fetch_data as $subKey => $subRow){
                if($row->id == $subRow->id && $subKey > $key){
                    $row->Debit += $subRow->Debit;
                    $row->Credit += $subRow->Credit;
                    $unset_array_keys [] = $subKey;
                }
            }
            $fetch_data[$key] = $row;
        }
        foreach($unset_array_keys as $key){
            unset($fetch_data[$key]);
        }
        $showen_cars_id = array_column($fetch_data, 'id');
        $showen_cars_id = array_merge($showen_cars_id, array_keys($carsClosingBalanceRemaining));
        $showen_cars_id = array_unique($showen_cars_id);
        $fetch_data_storage_remaining = CarService::carsInfoStorageFineRemaining($customer_id, $date_from, $date_to, $showen_cars_id);

        if ($arrived_status == 0) {
            $getAllTransation = [];
        }

        $args['showen_cars_id'] = $showen_cars_id;
        $previousBalances = $this->getCarReportPreviousBalance($args);

        $carsCompletedLotMapping = [];
        foreach ($fetch_data as $key => $row) {
            $carsCompletedLotMapping[$row->id] = $key;
            if (empty($row->Credit)) {
                $fetch_data[$key]->Credit = 0;
            }
        }

        $transferTransactionsCompleted = [];
        $transferTransactionsInAuction = [];

        foreach ($getAllTransation as $key => $row) {
            if ((($row['type_transaction'] == 1 && $row['car_step'] == 0 && $row['car_id'] > 0) || ($row['car_step'] == 1 && $row['car_id'] > 0)) && $row['car_customer_id'] == $customer_id) {
                $rowKey = $row['car_id'];
                if (!empty($row['completed_date']) && $row['completed_date'] <= $date_to) {
                    $transferTransactionsCompleted[$key] = $row;
                } else {
                    if (empty($transferTransactionsInAuction[$rowKey])) {
                        $transferTransactionsInAuction[$rowKey] = $row;
                    } else {
                        $transferTransactionsInAuction[$rowKey]['Debit'] += $row['Debit'];
                        $transferTransactionsInAuction[$rowKey]['Credit'] += $row['Credit'];
                    }
                }
                unset($getAllTransation[$key]);
            } else if (($row['type_transaction'] == 3 && $row['car_id'] > 0) || ($row['car_step'] > 0 && $row['car_id'] > 0 && $row['Credit'] > 0)) {
                $transferTransactionsCompleted[$key] = $row;
                unset($getAllTransation[$key]);
            } else if ($row['type_transaction'] == 2 && $row['car_id'] > 0 && !empty($row['completed_date']) && $row['completed_date'] <= $date_to) {
                $transferTransactionsCompleted[$key] = $row;
                unset($getAllTransation[$key]);
            }
        }

        // if exit car without any shipping transactions, it will not come in fetch_data
        // add from general entries
        $lastFetchDataKey = end(array_keys($fetch_data));
        foreach ($transferTransactionsCompleted as $key => $row) {
            if (!isset($carsCompletedLotMapping[$row['car_id']]) && $row['car_status'] == 4 && $row['customer_id'] == $customer_id) {
                $lastFetchDataKey++;
                $fetch_data[$lastFetchDataKey] = (object)$row;
                $carsCompletedLotMapping[$row['car_id']] = $lastFetchDataKey;
                unset($transferTransactionsCompleted[$key]);
            }
        }

        foreach ($transferTransactionsCompleted as $key => $row) {
            if (!isset($carsCompletedLotMapping[$row['car_id']])) {
                // show again in general entries, if car is not in shipped table
                $getAllTransation[$key] = $row;
                continue;
            }
            $carIndex = $carsCompletedLotMapping[$row['car_id']];
            $fetch_data[$carIndex]->Debit += $row['Debit'];
            $fetch_data[$carIndex]->Credit += $row['Credit'];
        }

        $totaldebit = $totalcredit = $sumBalance = $total = 0;
        $totalCarPrice = $totalShippingAmount = 0;
        $grandTotalDebit = $grandTotalCredit = 0;
        $completedCarsTransactions = $inAuctionTransactions = $generalTransactions = [];

        if (!empty($previousBalances['completedCars'])) {
            $storagePrevious = $previousBalances['completedCars']['storage_fine'];
            $debitPrevious = $previousBalances['completedCars']['debit'];
            $creditPrevious = $previousBalances['completedCars']['credit'];
            $Previous_Balance = $previousBalances['completedCars']['balance'];
            $Previous_Balance = $previousBalances['completedCars']['remaining'];
            $one_row = array();
            $one_row['index'] = '#';
            $one_row['date'] = '#';
            $one_row['lotnumber'] = '#';
            $one_row['description'] = 'Previous Balance';
            $one_row['storage_fine'] = Helpers::format_money($storagePrevious / $exchange_rate);
            $one_row['car_price'] = '#';
            $one_row['shipping_amount'] = '#';
            $one_row['debit'] = Helpers::format_money($debitPrevious / $exchange_rate);
            $one_row['credit'] = Helpers::format_money($creditPrevious / $exchange_rate);
            $one_row['balance'] = Helpers::format_money($Previous_Balance / $exchange_rate);
            $one_row['remaining'] = Helpers::format_money($Previous_Balance / $exchange_rate);
            $completedCarsTransactions[] = $one_row;
            $sumBalance = $Previous_Balance;
        }

        foreach ($fetch_data as $key => $row) {
            if ($arrived_status == 1) {
                $row->display_car_date =  date('Y-m-d', strtotime($row->received_create_date));
            } else {
                $row->display_car_date =  $row->purchasedate;
            }
        }

        foreach($fetch_data as $row){
            unset($carsClosingBalanceRemaining[$row->id]);
        }

        $indexCounter = 1;
        $totalStorage = $storagePrevious;
        $totalDebitClosingBalance = $totalCreditClosingBalance = 0;

        $fetch_data_closing_remaining = CarService::carsInfoClosingRemaining(array_keys($carsClosingBalanceRemaining));
        foreach ($fetch_data_closing_remaining as $key => $row) {
            $car_id = $row->id;

            if ($remaining_status != 1) {
                $fine = isset($carStorageFines[$row->id]) ? $carStorageFines[$row->id] : 0;
                $row->fine_value = $fine;
                $totalStorage += $row->fine_value;
            }

            $debit = 0;
            $credit = 0;
            if ($row->fine_value) {
                $debit = $debit + $row->fine_value;
            }

            if(isset($carsClosingBalance[$row->id])){
                $closingBalance = $carsClosingBalance[$row->id];
                $debit += $closingBalance['total_debit'];
                $credit += $closingBalance['total_credit'];
                $totalDebitClosingBalance += $closingBalance['total_debit'];
                $totalCreditClosingBalance += $closingBalance['total_credit'];
            }

            $balance = round($debit - $credit, 2);
            if ($debit == 0 && $credit == 0) {
                continue;
            }

            if ($balance == 0 && $remaining_status == 2) {
                unset($fetch_data[$key]);
                continue;
            } else if ($balance > 0 && $remaining_status == 1) {
                unset($fetch_data[$key]);
                continue;
            }

            $car_price = CarService::getCarCost($car_id, $customer_account_id);
            $totalCarPrice += $car_price;

            // if transfer transactions are included then subtract car cost else show all debit as shipping amount
            $shipping_amount = $debit > $car_price ? $debit - $car_price : $debit;
            $totalShippingAmount += $shipping_amount;

            $totaldebit += $debit;
            $totalcredit += $credit;
            $one_row = array();
            $one_row['index'] = $indexCounter++;
            $one_row['date'] = date('j-n-y', strtotime($row->display_car_date));
            $one_row['date_timestamp'] = strtotime($row->display_car_date);
            $one_row['description'] = $row->lotnumber;
            $one_row['car_id'] = $car_id;
            $one_row['car'] = [
                'lotnumber' => $row->lotnumber,
                'vin' => $row->vin,
                'year' => $row->year,
                'cancellation' => $row->cancellation,
                'carMakerName' => $row->carMakerName,
                'carModelName' => $row->carModelName,
                'vehicleName' => $row->vehicleName,
                'container_number' => $row->container_number,
            ];

            if ($row->fine_value > 0) {
                $one_row['storage_fine'] = Helpers::format_money($row->fine_value / $exchange_rate);
            } else {
                $one_row['storage_fine'] = '';
            }

            $one_row['debit_value'] = $debit;
            $one_row['credit_value'] = $credit;
            $one_row['car_price'] = Helpers::format_money($car_price / $exchange_rate);
            $one_row['shipping_amount'] = Helpers::format_money($shipping_amount / $exchange_rate);
            $one_row['debit'] = Helpers::format_money($debit / $exchange_rate);
            $one_row['credit'] = Helpers::format_money($credit / $exchange_rate);
            $one_row['remaining'] = Helpers::format_money($balance / $exchange_rate);
            $completedCarsTransactions[] = $one_row;
        }
        foreach ($fetch_data_storage_remaining as $key => $row) {
            $car_id = $row->id;

            if ($remaining_status != 1) {
                $totalStorage += $row->fine_value;
            }

            $debit = 0;
            $credit = 0;
            if ($row->fine_value) {
                $debit = $debit + $row->fine_value;
            }

            $balance = round($debit - $credit, 2);
            if ($debit == 0 && $credit == 0) {
                continue;
            }

            if ($balance == 0 && $remaining_status == 2) {
                unset($fetch_data[$key]);
                continue;
            } else if ($balance > 0 && $remaining_status == 1) {
                unset($fetch_data[$key]);
                continue;
            }

            $car_price = 0;
            $totalCarPrice += $car_price;

            $shipping_amount = 0;
            $totalShippingAmount += $shipping_amount;

            $totaldebit += $debit;
            $totalcredit += $credit;
            $one_row = array();
            $one_row['index'] = $indexCounter++;
            $one_row['date'] = date('j-n-y', strtotime($row->display_car_date));
            $one_row['date_timestamp'] = strtotime($row->display_car_date);
            $one_row['description'] = $row->lotnumber;
            $one_row['car_id'] = $car_id;
            $one_row['car'] = [
                'lotnumber' => $row->lotnumber,
                'vin' => $row->vin,
                'year' => $row->year,
                'cancellation' => $row->cancellation,
                'carMakerName' => $row->carMakerName,
                'carModelName' => $row->carModelName,
                'vehicleName' => $row->vehicleName,
                'container_number' => $row->container_number,
            ];

            if ($row->fine_value > 0) {
                $one_row['storage_fine'] = Helpers::format_money($row->fine_value / $exchange_rate);
            } else {
                $one_row['storage_fine'] = '';
            }

            $one_row['debit_value'] = $debit;
            $one_row['credit_value'] = $credit;
            $one_row['car_price'] = Helpers::format_money($car_price / $exchange_rate);
            $one_row['shipping_amount'] = Helpers::format_money($shipping_amount / $exchange_rate);
            $one_row['debit'] = Helpers::format_money($debit / $exchange_rate);
            $one_row['credit'] = Helpers::format_money($credit / $exchange_rate);
            $one_row['remaining'] = Helpers::format_money($balance / $exchange_rate);
            $completedCarsTransactions[] = $one_row;
        }
        foreach ($fetch_data as $key => $row) {
            $car_id = $row->id;

            if ($remaining_status != 1) {
                $fine = isset($carStorageFines[$row->id]) ? $carStorageFines[$row->id] : 0;
                $row->fine_value = $fine;
                $totalStorage += $row->fine_value;
            }

            $row->Discount = CarService::getDiscountFromTransaction($car_id, $customer_account_id, $date_from, $date_to);
            if($date_from <= $closed_date){
                $row->Discount += CarService::getDiscountFromTransaction($car_id, $customer_account_id, $date_from, $date_to, ['closingTable' => 1]);
            }

            $debit = $row->Debit - $row->Discount;
            $credit = $row->Credit;

            if ($row->fine_value) {
                $debit = $debit + $row->fine_value;
            }

            if(isset($carsClosingBalance[$row->id])){
                $closingBalance = $carsClosingBalance[$row->id];
                $debit += $closingBalance['total_debit'];
                $credit += $closingBalance['total_credit'];
                $totalDebitClosingBalance += $closingBalance['total_debit'];
                $totalCreditClosingBalance += $closingBalance['total_credit'];
            }

            $balance = round($debit - $credit, 2);
            if ($debit == 0 && $credit == 0) {
                continue;
            }

            if ($balance == 0 && $remaining_status == 2) {
                unset($fetch_data[$key]);
                continue;
            } else if ($balance > 0 && $remaining_status == 1) {
                unset($fetch_data[$key]);
                continue;
            }

            $car_price = CarService::getCarCost($car_id, $customer_account_id);
            $totalCarPrice += $car_price;

            // if transfer transactions are included then subtract car cost else show all debit as shipping amount
            $shipping_amount = $debit > $car_price ? $debit - $car_price : $debit;
            $totalShippingAmount += $shipping_amount;

            $totaldebit += $debit;
            $totalcredit += $credit;
            $one_row = array();
            $one_row['index'] = $indexCounter++;
            $one_row['date'] = date('j-n-y', strtotime($row->display_car_date));
            $one_row['date_timestamp'] = strtotime($row->display_car_date);
            $one_row['description'] = $row->lotnumber;
            $one_row['car_id'] = $car_id;
            $one_row['car'] = [
                'lotnumber' => $row->lotnumber,
                'vin' => $row->vin,
                'year' => $row->year,
                'cancellation' => $row->cancellation,
                'carMakerName' => $row->carMakerName,
                'carModelName' => $row->carModelName,
                'vehicleName' => $row->vehicleName,
                'container_number' => $row->container_number,
            ];

            if ($row->fine_value > 0) {
                $one_row['storage_fine'] = Helpers::format_money($row->fine_value / $exchange_rate);
            } else {
                $one_row['storage_fine'] = '';
            }

            $one_row['debit_value'] = $debit;
            $one_row['credit_value'] = $credit;
            $one_row['car_price'] = Helpers::format_money($car_price / $exchange_rate);
            $one_row['shipping_amount'] = Helpers::format_money($shipping_amount / $exchange_rate);
            $one_row['debit'] = Helpers::format_money($debit / $exchange_rate);
            $one_row['credit'] = Helpers::format_money($credit / $exchange_rate);
            $one_row['remaining'] = Helpers::format_money($balance / $exchange_rate);
            $completedCarsTransactions[] = $one_row;
        }

        usort($completedCarsTransactions, function($a, $b){
            if(empty($a['date_timestamp'])) return true;
            return $a['date_timestamp'] - $b['date_timestamp'];
        });

        $sumBalance = 0;
        foreach ($completedCarsTransactions as $key => $row) {
            $row['index'] = $row['index'] == '#' ? '#' : $key + 1;
            $sumBalance += $row['debit_value'] - $row['credit_value'];
            $row['balance'] = Helpers::format_money($sumBalance / $exchange_rate);
            $completedCarsTransactions[$key] = $row;
        }
        $one_row['car_id'] = $car_id;
        $one_row['index'] = '';
        $one_row['date'] = '';
        $one_row['lotnumber'] = '';
        $one_row['description'] = 'Total = ';
        $one_row['storage_fine'] = Helpers::format_money($totalStorage / $exchange_rate);
        $one_row['car_price'] = Helpers::format_money($totalCarPrice / $exchange_rate);
        $one_row['shipping_amount'] = Helpers::format_money($totalShippingAmount / $exchange_rate);
        $one_row['debit'] = Helpers::format_money(($totaldebit + $debitPrevious) / $exchange_rate);
        $one_row['credit'] = Helpers::format_money(($totalcredit + $creditPrevious) / $exchange_rate);
        $one_row['remaining'] = Helpers::format_money($sumBalance / $exchange_rate);
        $one_row['balance'] = Helpers::format_money($sumBalance / $exchange_rate);
        $grandTotalDebit += ($totaldebit + $debitPrevious);
        $grandTotalCredit += ($totalcredit + $creditPrevious);
        $completedCarsTransactions[] = $one_row;

        $totaldebit = $totalcredit = $sumBalance = $debitPrevious = $creditPrevious = 0;
        if ($arrived_status != 0 && (!empty($transferTransactionsInAuction) || !empty($previousBalances['inAuctions'])) ) {
            if (!empty($previousBalances['inAuctions'])) {
                $debitPrevious = $previousBalances['inAuctions']['debit'];
                $creditPrevious = $previousBalances['inAuctions']['credit'];
                $Previous_Balance = $previousBalances['inAuctions']['balance'];
                $Previous_Balance = $previousBalances['inAuctions']['remaining'];
                $one_row = array();
                $one_row['index'] = '#';
                $one_row['date'] = '#';
                $one_row['lotnumber'] = '#';
                $one_row['ref'] = '#';
                $one_row['description'] = 'Previous Balance';
                $one_row['debit'] = Helpers::format_money($debitPrevious / $exchange_rate);
                $one_row['credit'] = Helpers::format_money($creditPrevious / $exchange_rate);
                $one_row['balance'] = Helpers::format_money($Previous_Balance / $exchange_rate);
                $one_row['remaining'] = Helpers::format_money($Previous_Balance / $exchange_rate);
                $inAuctionTransactions[] = $one_row;
                $sumBalance = $Previous_Balance;
            }

            usort($transferTransactionsInAuction, function($a, $b){
                return strtotime($a['DateOfTransAction']) - strtotime($b['DateOfTransAction']);
            });
      

            foreach ($transferTransactionsInAuction as $key => $value) {

                if(isset($carsClosingBalance[ $value['car_id'] ])){
                    $closingBalance = $carsClosingBalance[ $value['car_id'] ];
                    $value['Debit'] += $closingBalance['total_debit'];
                    $value['Credit'] += $closingBalance['total_credit'];
                    $totalDebitClosingBalance += $closingBalance['total_debit'];
                    $totalCreditClosingBalance += $closingBalance['total_credit'];
                }

                $transferRow = AccountingService::getRemainingCarTransfer($customer_account_id, $value['car_id']);
                $remaining_balance = number_format($transferRow->totalDebit - $transferRow->totalCredit, 2);

                if (($transfer_status == '1' && $remaining_balance <= 0) || ($transfer_status == '2' && $remaining_balance > 0) || ($transfer_status == '0')) {
                    $remaining_balance = $value['Debit'] - $value['Credit'];
                    $totaldebit += $value['Debit'];
                    $totalcredit += $value['Credit'];

                    $sumBalance += $remaining_balance;
                    $one_row = [];
                    $one_row['index_no'] = '>';
                    $one_row['date'] = date('j-n-y', strtotime($value['DateOfTransAction']));
                    $one_row['reference_no'] = '';
                    $one_row['lotnumber'] = $value['lotnumber'];

                    if ($user_language == 'en') {
                        $customDescription = "The total Debit : {$value['Debit']} and the total Credit : {$value['Credit']} for the Car with the lot number vehicle : {$value['lotnumber']}";
                    } else {
                        $customDescription = "The total Debit : {$value['Debit']} and the total Credit : {$value['Credit']} for the Car with the lot number vehicle : {$value['lotnumber']}";
                    }

                    $one_row['description'] = $customDescription;
                    $one_row['debit'] = Helpers::format_money($value['Debit'] / $exchange_rate);
                    $one_row['credit'] = Helpers::format_money($value['Credit'] / $exchange_rate);
                    $one_row['remaining'] = Helpers::format_money($remaining_balance / $exchange_rate);
                    $one_row['balance'] = Helpers::format_money($sumBalance / $exchange_rate);

                    $inAuctionTransactions[] = $one_row;
                }
            }  
           
      
            $inAuctionTransactions[] = [
                'index_no' => '',
                'date' => '',
                'reference_no' => '',
                'lotnumber' => '',
                'description' => 'Total',
                'debit' => Helpers::format_money($totaldebit / $exchange_rate),
                'credit' => Helpers::format_money($totalcredit / $exchange_rate),
                'remaining' => Helpers::format_money($sumBalance / $exchange_rate),
                'balance' => Helpers::format_money($sumBalance / $exchange_rate),
            ];

            
        }

        $customer_opening_entry_exist = false;
        foreach ($getAllTransation as $key => $value) {
            if($value['car_step'] == Constants::CAR_STEPS['CLOSING_CUSTOMER_BALANCE']){
                $net_opening_balance = $value['Debit'] - $value['Credit'];
                $net_opening_balance -= $totalDebitClosingBalance - $totalCreditClosingBalance;
                $value['Debit'] = $net_opening_balance > 0 ? $net_opening_balance : 0;
                $value['Credit'] = $net_opening_balance < 0 ? abs($net_opening_balance) : 0;
                $getAllTransation[$key] = $value;
                $customer_opening_entry_exist = true;
                break;
            }
        }

        if(!$customer_opening_entry_exist && empty($previousBalances['generalEntries']) && ($totalDebitClosingBalance || $totalCreditClosingBalance) ){
            $previousBalances['generalEntries'] = [
                'debit' => 0,
                'credit' => 0,
                'balance' => 0,
                'remaining' => 0,
            ];
        }

        $debitPrevious = $creditPrevious = $Previous_Balance =$sumBalance=  0;
        if($arrived_status != -1){

            if (!empty($previousBalances['generalEntries'])) {
                $debitPrevious = $previousBalances['generalEntries']['debit'];
                $creditPrevious = $previousBalances['generalEntries']['credit'];
                $Previous_Balance = $previousBalances['generalEntries']['balance'];
                $Previous_Balance = $previousBalances['generalEntries']['remaining'];

                if(!$customer_opening_entry_exist){
                    $net_opening_balance = $totalDebitClosingBalance - $totalCreditClosingBalance;
                    $debitPrevious -= $net_opening_balance > 0 ? $net_opening_balance : 0;
                    $creditPrevious -= $net_opening_balance < 0 ? $net_opening_balance : 0;

                    if($debitPrevious < 0){
                        $creditPrevious += abs($debitPrevious);
                        $debitPrevious = 0;
                    }
                    if($creditPrevious < 0){
                        $debitPrevious += abs($creditPrevious);
                        $creditPrevious = 0;
                    }

                    $Previous_Balance = $debitPrevious - $creditPrevious;
                }

                $one_row = [];
                $one_row['index'] = '#';
                $one_row['date'] = '#';
                $one_row['lotnumber'] = '#';
                $one_row['ref'] = '#';
                $one_row['description'] = 'Previous Balance';
                $one_row['debit'] = Helpers::format_money($debitPrevious / $exchange_rate);
                $one_row['credit'] = Helpers::format_money($creditPrevious / $exchange_rate);
                $one_row['remaining'] = Helpers::format_money($Previous_Balance / $exchange_rate);
                $one_row['balance'] = Helpers::format_money($Previous_Balance / $exchange_rate);
                $generalTransactions[] = $one_row;
                $sumBalance = $Previous_Balance;
            }

            usort($getAllTransation, function($a, $b){
                return strtotime($a['DateOfTransAction']) - strtotime($b['DateOfTransAction']);
            });

            $indexCounter = 1;
            $totaldebit = $totalcredit = 0;
            foreach ($getAllTransation as $key => $value) {
                $totaldebit += $value['Debit'];
                $totalcredit += $value['Credit'];
                $balance = $value['Debit'] - $value['Credit'];
                $sumBalance += $balance;

                $one_row = array();
                $one_row['index'] = $indexCounter++;
                $one_row['date'] = date('j-n-y', strtotime($value['DateOfTransAction']));

                if ($user_language == 'en') {
                    $one_row['description'] = $value['Description'];
                } else {
                    $one_row['description'] = !empty($value['DescriptionAR']) ? $value['DescriptionAR'] : $value['Description'];
                }
                
                $one_row['debit'] = Helpers::format_money($value['Debit'] / $exchange_rate);
                $one_row['credit'] = Helpers::format_money($value['Credit'] / $exchange_rate);
                $one_row['remaining'] = Helpers::format_money($balance / $exchange_rate);
                $one_row['balance'] = Helpers::format_money($sumBalance / $exchange_rate);
                $generalTransactions[] = $one_row;
            }

            $generalTransactions[] = [
                'index_no' => '',
                'date' => '',
                'reference_no' => '',
                'description' => 'Total',
                'debit' => Helpers::format_money( ($totaldebit + $debitPrevious) / $exchange_rate),
                'credit' => Helpers::format_money( ($totalcredit + $creditPrevious) / $exchange_rate),
                'remaining' => Helpers::format_money($sumBalance / $exchange_rate),
                'balance' => Helpers::format_money($sumBalance / $exchange_rate),
            ];

        }

        $data = [
            'shippedCars' => $completedCarsTransactions,
            'inAuctionTransactions' => $inAuctionTransactions,
            'generalTransactions' => $generalTransactions,
        ];

        if($request->partialStatementOnly){
            return $data[$request->partialStatementOnly];
        }

        return response()->json($data, Response::HTTP_OK);

    }

    public function carStatementShippedCarsPDF(Request $request)
    {
        $request['customer_id'] = $request->user()->customer_id;
        $response = $this->carStatementShippedCars($request);
        $data = ['transactions' => $response->original['data']];

        $html = view('accounting.reports.pdf.statement_shipped_cars', $data)->render();
        $pdf = Helpers::getPDF($html);
        return $pdf->inline();
    }

    function carStatementShippedCarsNoAuth(Request $request){
        $this->customer_id = $request->customer_id;
        $request->partialStatementOnly = 'shippedCars';
        $closed_date = Helpers::get_closed_date();
        $request->date_from = !empty($request->date_from) ? $request->date_from : date('Y-m-d', strtotime("$closed_date +1 day"));
        $responseData = $this->carStatementShippedCars($request);
        $data = [
            'shippedCars' => $responseData,
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    function carStatementInAuctionCarsNoAuth(Request $request){
        $this->customer_id = $request->customer_id;
        $request->partialStatementOnly = 'inAuctionTransactions';
        $closed_date = Helpers::get_closed_date();
        $request->date_from = !empty($request->date_from) ? $request->date_from : date('Y-m-d', strtotime("$closed_date +1 day"));
        $responseData = $this->carStatementShippedCars($request);
        $data = [
            'data' => $responseData,
            'totalRecords' => count($responseData),
        ];

        return response()->json($data, Response::HTTP_OK);
    }
    
    function carStatementGeneralEntriesNoAuth(Request $request){
        $this->customer_id = $request->customer_id;
        $request->partialStatementOnly = 'generalTransactions';
        $closed_date = Helpers::get_closed_date();
        $request->date_from = !empty($request->date_from) ? $request->date_from : date('Y-m-d', strtotime("$closed_date +1 day"));
        $responseData = $this->carStatementShippedCars($request);
        $data = [
            'data' => $responseData,
            'totalRecords' => count($responseData),
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function carStatementInAuctionCars(Request $request)
    {
        $customer_id = $request->user()->customer_id;
        $arrived_status = $request->arrived_status;
        $date_from = $request->date_from;
        $date_to = $request->date_to;
        $remaining_status = $request->remaining_status;
        $paid_status = $request->paid_status;
        $search = $request->search;
        $transfer_status = empty($request->transfer_status) ? 0 : $request->transfer_status;
        $date_from = empty($request->date_from) ? date('Y-01-01') : $request->date_from;
        $date_from = '2020-01-01';
        $date_to = empty($request->date_to) ? date('Y-m-d') : $request->date_to;
        $currency = empty($request->currency) ? 'aed' : $request->currency;
        $exchange_rate = $currency == 'usd' ? Helpers::get_usd_rate() : 1;
        $user_language = empty($request->user_language) ? 'en' : $request->user_language;

        $customer_account_id = Helpers::get_customer_account_id($customer_id);

        $args = [
            'customer_id' => $customer_id,
            'customer_account_id' => $customer_account_id,
            'arrived_status' => $arrived_status,
            'remaining_status' => $remaining_status,
            'paid_status' => $paid_status,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'search' => $search,
        ];
        $carTransactions = CarService::getCarsInAuctionTransactions($args);

        $previousBalance = $previousDebit = $previousCredit = 0;
        if (!empty($date_from) && $arrived_status == 1) {
            $previousBalanceCars = CarService::getCarsInAuctionPreviousBalance($args);
            $previousDebit = $previousBalanceCars->totalDebit;
            $previousCredit = $previousBalanceCars->totalCredit;
            $previousBalance = $previousDebit - $previousCredit;
        }

        $tableData = [];

        if ($previousDebit || $previousCredit) {
            $tableData[] = [
                'index_no' => '#',
                'date' => '#',
                'reference_no' => '#',
                'lotnumber' => '#',
                'description' => 'Previous Balance',
                'debit' => Helpers::format_money($previousDebit),
                'credit' => Helpers::format_money($previousCredit),
                'remaining' => Helpers::format_money($previousBalance),
                'balance' => Helpers::format_money($previousBalance),
            ];
        }

        $sumBalance = $previousBalance;
        $totalDebit = $totalCredit = 0;
        if ($arrived_status != 0) {
            foreach ($carTransactions as $key => $row) {
                $totalDebit += $row->Debit;
                $totalCredit += $row->Credit;
                $transfer_balance = round($row->Debit - $row->Credit, 2);

                if (($transfer_status == '1' && $transfer_balance <= 0) || ($transfer_status == '2' && $transfer_balance > 0) || ($transfer_status == '0')) {
                    $sumBalance += $transfer_balance;
                    $tableRow = [];
                    $tableRow['index_no'] = '>';
                    $tableRow['date'] = date('j-n-y', strtotime($row->DateOfTransAction));

                    $tableRow['reference_no'] = '';
                    $tableRow['lotnumber'] = $row->lotnumber;

                    if ($user_language == 'en') {
                        $customDescription = "The total Debit : {$row->Debit} and the total Credit : {$row->Credit} for the Car with the lot number vehicle : {$row->lotnumber}";
                    } else {
                        $customDescription = "The total Debit : {$row->Debit} and the total Credit : {$row->Credit} for the Car with the lot number vehicle : {$row->lotnumber}";
                    }
                    $tableRow['description'] = $customDescription;
                    $tableRow['debit'] = Helpers::format_money($row->Debit / $exchange_rate);
                    $tableRow['credit'] = Helpers::format_money($row->Credit / $exchange_rate);
                    $tableRow['remaining'] = Helpers::format_money($transfer_balance / $exchange_rate);
                    $tableRow['balance'] = Helpers::format_money($sumBalance / $exchange_rate);

                    $tableData[] = $tableRow;
                }
            }

            $tableData[] = [
                'index_no' => '',
                'date' => '',
                'reference_no' => '',
                'lotnumber' => '',
                'description' => 'Total',
                'debit' => Helpers::format_money($totalDebit / $exchange_rate),
                'credit' => Helpers::format_money($totalCredit / $exchange_rate),
                'remaining' => Helpers::format_money($sumBalance / $exchange_rate),
                'balance' => Helpers::format_money($sumBalance / $exchange_rate),
            ];
        }

        $data = [
            'data' => $tableData,
            'totalRecords' => count($tableData)
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function carStatementInAuctionCarsPDF(Request $request)
    {
        $request['customer_id'] = $request->user()->customer_id;
        $response = $this->carStatementInAuctionCars($request);
        $data = ['transactions' => $response->original['data']];

        $html = view('accounting.reports.pdf.statement_inauction_cars', $data)->render();
        $pdf = Helpers::getPDF($html);
        return $pdf->inline();
    }

    public function carStatementGeneralEntries(Request $request)
    {
        $customer_id = $request->user()->customer_id;
        $arrived_status = $request->arrived_status;
        $date_from = $request->date_from;
        $date_to = $request->date_to;
        $search = $request->search;
        $date_from = empty($request->date_from) ? date('Y-01-01') : $request->date_from;
        $date_from = '2020-01-01';
        $date_to = empty($request->date_to) ? date('Y-m-d') : $request->date_to;
        $currency = empty($request->currency) ? 'aed' : $request->currency;
        $exchange_rate = $currency == 'usd' ? Helpers::get_usd_rate() : 1;
        $user_language = empty($request->user_language) ? 'en' : $request->user_language;

        $customer_account_id = Helpers::get_customer_account_id($customer_id);

        $args = [
            'customer_id' => $customer_id,
            'customer_account_id' => $customer_account_id,
            'arrived_status' => $arrived_status,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'search' => $search,
        ];
        $generalTransactions = CarService::getGeneralTransactions($args);

        $previousBalance = $previousDebit = $previousCredit = 0;
        if (!empty($date_from) && $arrived_status == 1) {
            $previousBalanceCars = CarService::getGeneralTransactionsPreviousBalance($args);
            $previousDebit = $previousBalanceCars->totalDebit;
            $previousCredit = $previousBalanceCars->totalCredit;
            $previousBalance = $previousDebit - $previousCredit;
        }

        $tableData = [];

        if ($previousDebit || $previousCredit) {
            $tableData[] = [
                'index_no' => '#',
                'date' => '#',
                'reference_no' => '#',
                'description' => 'Previous Balance',
                'debit' => Helpers::format_money($previousDebit),
                'credit' => Helpers::format_money($previousCredit),
                'remaining' => Helpers::format_money($previousBalance),
                'balance' => Helpers::format_money($previousBalance),
            ];
        }

        $sumBalance = $previousBalance;
        $totalDebit = $totalCredit = 0;
        if ($arrived_status != 0) {

            foreach ($generalTransactions as $key => $row) {
                $totalDebit += $row->Debit;
                $totalCredit += $row->Credit;

                $sumBalance += $balance = $row->Debit - $row->Credit;

                $tableRow = [];
                $tableRow['index_no'] = $key + 1;
                $tableRow['date'] = date('j-n-y', strtotime($row->DateOfTransAction));

                $journalNoData = [
                    'id' => $row->Journal_id,
                    'serial_no' => $row->serial_no,
                    'rec_type' => $row->type_transaction,
                    'typePay' => $row->typePay,
                    'car_step' => $row->car_step,
                    'create_date' => $row->create_date,
                ];
                $tableRow['reference_no'] = Helpers::generataTransactionNo($journalNoData);
                $tableRow['lotnumber'] = '';

                if ($user_language == 'en') {
                    $tableRow['description'] = $row->Description;
                } else {
                    $tableRow['description'] = !empty($row->DescriptionAR) ? $row->DescriptionAR : $row->Description;
                }

                $tableRow['debit'] = Helpers::format_money($row->Debit / $exchange_rate);
                $tableRow['credit'] = Helpers::format_money($row->Credit / $exchange_rate);
                $tableRow['remaining'] = Helpers::format_money($balance / $exchange_rate);
                $tableRow['balance'] = Helpers::format_money($sumBalance / $exchange_rate);
                $tableData[] = $tableRow;
            }

            $tableData[] = [
                'index_no' => '',
                'date' => '',
                'reference_no' => '',
                'description' => 'Total',
                'debit' => Helpers::format_money( ($totalDebit + $previousDebit) / $exchange_rate),
                'credit' => Helpers::format_money( ($totalCredit + $previousCredit) / $exchange_rate),
                'remaining' => Helpers::format_money($sumBalance / $exchange_rate),
                'balance' => Helpers::format_money($sumBalance / $exchange_rate),
            ];
        }
        $data = [
            'data' => $tableData,
            'totalRecords' => count($tableData)
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function carStatementGeneralEntriesPDF(Request $request)
    {
        $request['customer_id'] = $request->user()->customer_id;
        $response = $this->carStatementGeneralEntries($request);
        $data = ['transactions' => $response->original['data']];

        $html = view('accounting.reports.pdf.statement_general_entries', $data)->render();
        $pdf = Helpers::getPDF($html);
        return $pdf->inline();
    }

    public function carStatementDeposits(Request $request){
        $args = [
            'customer_id' => $request->user()->customer_id
        ];
        $deposits = AccountingService::getCustomerDeposits($args);
        foreach($deposits as $key => $row){
            $row->date = date('j-n-y', strtotime($row->create_date));
        }

        $data = [
            'data' => $deposits,
            'totalRecords' => count($deposits)
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function shippedCarsnoAuth(Request $request)
    {
        $customer_id = $request->customer_id;
        $arrived_status = $request->arrived_status;
        $date_from = $request->date_from;
        $date_to = $request->date_to;
       
        $remaining_status = $request->remaining_status;
        $transfer_status = $request->transfer_status;
        $paid_status = $request->paid_status;
        $search = $request->search;
        $date_from = empty($request->date_from) ? date('Y-01-01') : $request->date_from;
        $date_to = empty($request->date_to) ? date('Y-m-d') : $request->date_to;
        $currency = empty($request->currency) ? 'aed' : $request->currency;
        $exchange_rate = $currency == 'usd' ? Helpers::get_usd_rate() : 1;
        $user_language = empty($request->user_language) ? 'en' : $request->user_language;

        $customer_account_id = Helpers::get_customer_account_id($customer_id);

        $args = [
            'customer_id' => $customer_id,
            'customer_account_id' => $customer_account_id,
            'arrived_status' => $arrived_status,
            'paid_status' => $remaining_status,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'search' => $search,
        ];
        $fetch_data = CarService::shippedCarsData($args);
        $transactionAfterCompleted = CarService::transactionAfterCompleted($args);
        $getAllTransation = CarService::getAllTransation2CarsStatement($args);
        $fetch_data = array_merge($fetch_data, $transactionAfterCompleted);
        $showen_cars_id = array_column($fetch_data, 'id');

        if ($arrived_status == 0) {
            $getAllTransation = [];
        }

        $args['showen_cars_id'] = $showen_cars_id;
        $previousBalances = $this->getCarReportPreviousBalance($args);

        $carsCompletedLotMapping = [];
        foreach ($fetch_data as $key => $row) {
            $carsCompletedLotMapping[$row->id] = $key;
            if (empty($row->Credit)) {
                $fetch_data[$key]->Credit = 0;
            }
        }

        $transferTransactionsCompleted = [];
        $transferTransactionsInAuction = [];

        foreach ($getAllTransation as $key => $row) {
            if ((($row['type_transaction'] == 1 && $row['car_step'] == 0 && $row['car_id'] > 0) || ($row['car_step'] == 1 && $row['car_id'] > 0)) && $row['car_customer_id'] == $customer_id) {
                $rowKey = $row['car_id'];
                if (!empty($row['completed_date']) && $row['completed_date'] <= $date_to) {
                    $transferTransactionsCompleted[$key] = $row;
                } else {
                    if (empty($transferTransactionsInAuction[$rowKey])) {
                        $transferTransactionsInAuction[$rowKey] = $row;
                    } else {
                        $transferTransactionsInAuction[$rowKey]['Debit'] += $row['Debit'];
                        $transferTransactionsInAuction[$rowKey]['Credit'] += $row['Credit'];
                    }
                }
                unset($getAllTransation[$key]);
            } else if (($row['type_transaction'] == 3 && $row['car_id'] > 0) || ($row['car_step'] > 0 && $row['car_id'] > 0 && $row['Credit'] > 0)) {
                $transferTransactionsCompleted[$key] = $row;
                unset($getAllTransation[$key]);
            } else if ($row['type_transaction'] == 2 && $row['car_id'] > 0 && !empty($row['completed_date']) && $row['completed_date'] <= $date_to) {
                $transferTransactionsCompleted[$key] = $row;
                unset($getAllTransation[$key]);
            }
        }

        // if exit car without any shipping transactions, it will not come in fetch_data
        // add from general entries
        $lastFetchDataKey = end(array_keys($fetch_data));
        foreach ($transferTransactionsCompleted as $key => $row) {
            if (!isset($carsCompletedLotMapping[$row['car_id']]) && $row['car_status'] == 4 && $row['customer_id'] == $customer_id) {
                $lastFetchDataKey++;
                $fetch_data[$lastFetchDataKey] = (object)$row;
                $carsCompletedLotMapping[$row['car_id']] = $lastFetchDataKey;
                unset($transferTransactionsCompleted[$key]);
            }
        }

        foreach ($transferTransactionsCompleted as $key => $row) {
            if (!isset($carsCompletedLotMapping[$row['car_id']])) {
                // show again in general entries, if car is not in shipped table
                $getAllTransation[$key] = $row;
                continue;
            }
            $carIndex = $carsCompletedLotMapping[$row['car_id']];
            $fetch_data[$carIndex]->Debit += $row['Debit'];
            $fetch_data[$carIndex]->Credit += $row['Credit'];
        }

        $totaldebit = $totalcredit = $sumBalance = $total = 0;
        $totalCarPrice = $totalShippingAmount = 0;
        $grandTotalDebit = $grandTotalCredit = 0;
        $completedCarsTransactions = $inAuctionTransactions = $generalTransactions = [];

        if (!empty($previousBalances['completedCars'])) {
            $storagePrevious = $previousBalances['completedCars']['storage_fine'];
            $debitPrevious = $previousBalances['completedCars']['debit'];
            $creditPrevious = $previousBalances['completedCars']['credit'];
            $Previous_Balance = $previousBalances['completedCars']['balance'];
            $Previous_Balance = $previousBalances['completedCars']['remaining'];
            $one_row = array();
            $one_row['index'] = '#';
            $one_row['date'] = '#';
            $one_row['lotnumber'] = '#';
            $one_row['description'] = 'Previous Balance';
            $one_row['storage_fine'] = Helpers::format_money($storagePrevious / $exchange_rate);
            $one_row['car_price'] = '#';
            $one_row['shipping_amount'] = '#';
            $one_row['debit'] = Helpers::format_money($debitPrevious / $exchange_rate);
            $one_row['credit'] = Helpers::format_money($creditPrevious / $exchange_rate);
            $one_row['balance'] = Helpers::format_money($Previous_Balance / $exchange_rate);
            $one_row['remaining'] = Helpers::format_money($Previous_Balance / $exchange_rate);
            $completedCarsTransactions[] = $one_row;
            $sumBalance = $Previous_Balance;
        }

        foreach ($fetch_data as $key => $row) {
            if ($arrived_status > 0) {
                $row->display_car_date =  date('Y-m-d', strtotime($row->received_create_date));
            } else {
                $row->display_car_date =  $row->purchasedate;
            }
        }

        usort($fetch_data, function ($a, $b) {
            return strtotime($a->display_car_date) - strtotime($b->display_car_date);
        });

        $indexCounter = 1;
        $totalStorage = $storagePrevious;
        foreach ($fetch_data as $key => $row) {
            $car_id = $row->id;

            if ($remaining_status != 1) {
                $calculateStorage = CarService::storageFine($car_id);
                if ($row->deliver_status == 1 && $row->final_payment_status == 1) {
                    $calculateStorage = 0;
                }
                if ($calculateStorage) {
                    $row->fine_value = $calculateStorage['fine'];
                    if (strpos($row->fine_value, 'fa-ban') !== false) {
                        $row->fine_value = '';
                    } else {
                        $totalStorage += $row->fine_value;
                    }
                }
            }

            $row->Discount = CarService::getDiscountFromTransaction($car_id, $customer_account_id, $date_from, $date_to);

            $debit = $row->Debit - $row->Discount;
            $credit = $row->Credit;

            if ($row->fine_value) {
                $debit = $debit + $row->fine_value;
            }

            $balance = round($debit - $credit, 2);
            if ($debit == 0 && $credit == 0) {
                continue;
            }

            if ($balance == 0 && $remaining_status == 2) {
                unset($fetch_data[$key]);
                continue;
            } else if ($balance > 0 && $remaining_status == 1) {
                unset($fetch_data[$key]);
                continue;
            }

            $car_price = CarService::getCarCost($car_id, $customer_account_id);
            $totalCarPrice += $car_price;

            // if transfer transactions are included then subtract car cost else show all debit as shipping amount
            $shipping_amount = $debit > $car_price ? $debit - $car_price : $debit;
            $totalShippingAmount += $shipping_amount;

            $totaldebit += $debit;
            $totalcredit += $credit;
            $sumBalance += $balance;
            $total = $total + $sumBalance;
            $one_row = array();
            $one_row['index'] = $indexCounter++;
            $one_row['date'] = date('Y-m-d', strtotime($row->display_car_date));
            $one_row['description'] = $row->lotnumber;

            $one_row['car'] = [
                'lotnumber' => $row->lotnumber,
                'vin' => $row->vin,
                'year' => $row->year,
                'cancellation' => $row->cancellation,
                'carMakerName' => $row->carMakerName,
                'carModelName' => $row->carModelName,
                'vehicleName' => $row->vehicleName,
                'container_number' => $row->container_number,
            ];

            if ($row->fine_value > 0) {
                $one_row['storage_fine'] = Helpers::format_money($row->fine_value / $exchange_rate);
            } else {
                $one_row['storage_fine'] = '';
            }

            $one_row['car_price'] = Helpers::format_money($car_price / $exchange_rate);
            $one_row['shipping_amount'] = Helpers::format_money($shipping_amount / $exchange_rate);
            $one_row['debit'] = Helpers::format_money($debit / $exchange_rate);
            $one_row['credit'] = Helpers::format_money($credit / $exchange_rate);
            $one_row['remaining'] = Helpers::format_money($balance / $exchange_rate);
            $one_row['balance'] = Helpers::format_money($sumBalance / $exchange_rate);
            $completedCarsTransactions[] = $one_row;
        }

        foreach ($completedCarsTransactions as $key => $row) {
            $row['index'] = $row['index'] == '#' ? '#' : $key + 1;
            $completedCarsTransactions[$key] = $row;
        }

        $one_row['index'] = '';
        $one_row['date'] = '';
        $one_row['lotnumber'] = '';
        $one_row['description'] = 'Total = ';
        $one_row['storage_fine'] = Helpers::format_money($totalStorage / $exchange_rate);
        $one_row['car_price'] = Helpers::format_money($totalCarPrice / $exchange_rate);
        $one_row['shipping_amount'] = Helpers::format_money($totalShippingAmount / $exchange_rate);
        $one_row['debit'] = Helpers::format_money(($totaldebit + $debitPrevious) / $exchange_rate);
        $one_row['credit'] = Helpers::format_money(($totalcredit + $creditPrevious) / $exchange_rate);
        $one_row['remaining'] = Helpers::format_money($sumBalance / $exchange_rate);
        $one_row['balance'] = Helpers::format_money($sumBalance / $exchange_rate);
        $grandTotalDebit += ($totaldebit + $debitPrevious);
        $grandTotalCredit += ($totalcredit + $creditPrevious);
        $completedCarsTransactions[] = $one_row;

        $totaldebit = $totalcredit = $sumBalance = $debitPrevious = $creditPrevious = 0;
        if ($arrived_status != 0 && (!empty($transferTransactionsInAuction) || !empty($previousBalances['inAuctions'])) ) {
            if (!empty($previousBalances['inAuctions'])) {
                $debitPrevious = $previousBalances['inAuctions']['debit'];
                $creditPrevious = $previousBalances['inAuctions']['credit'];
                $Previous_Balance = $previousBalances['inAuctions']['balance'];
                $Previous_Balance = $previousBalances['inAuctions']['remaining'];
                $one_row = array();
                $one_row['index'] = '#';
                $one_row['date'] = '#';
                $one_row['lotnumber'] = '#';
                $one_row['ref'] = '#';
                $one_row['description'] = 'Previous Balance';
                $one_row['debit'] = Helpers::format_money($debitPrevious / $exchange_rate);
                $one_row['credit'] = Helpers::format_money($creditPrevious / $exchange_rate);
                $one_row['balance'] = Helpers::format_money($Previous_Balance / $exchange_rate);
                $one_row['remaining'] = Helpers::format_money($Previous_Balance / $exchange_rate);
                $inAuctionTransactions[] = $one_row;
                $sumBalance = $Previous_Balance;
            }

            foreach ($transferTransactionsInAuction as $key => $value) {
                $transferRow = AccountingService::getRemainingCarTransfer($customer_account_id, $value['car_id']);
                $remaining_balance = number_format($transferRow->totalDebit - $transferRow->totalCredit, 2);

                if (($transfer_status == '1' && $remaining_balance <= 0) || ($transfer_status == '2' && $remaining_balance > 0) || ($transfer_status == '0')) {
                    $remaining_balance = $value['Debit'] - $value['Credit'];
                    $totaldebit += $value['Debit'];
                    $totalcredit += $value['Credit'];

                    $sumBalance += $remaining_balance;
                    $one_row = [];
                    $one_row['index_no'] = '>';
                    $one_row['date'] = date('j-n-y', strtotime($row['DateOfTransAction']));
                    $one_row['reference_no'] = '';
                    $one_row['lotnumber'] = $value['lotnumber'];

                    if ($user_language == 'en') {
                        $customDescription = "The total Debit : {$value['Debit']} and the total Credit : {$value['Credit']} for the Car with the lot number vehicle : {$value['lotnumber']}";
                    } else {
                        $customDescription = "The total Debit : {$value['Debit']} and the total Credit : {$value['Credit']} for the Car with the lot number vehicle : {$value['lotnumber']}";
                    }

                    $one_row['description'] = $customDescription;
                    $one_row['debit'] = Helpers::format_money($value['Debit'] / $exchange_rate);
                    $one_row['credit'] = Helpers::format_money($value['Credit'] / $exchange_rate);
                    $one_row['remaining'] = Helpers::format_money($remaining_balance / $exchange_rate);
                    $one_row['balance'] = Helpers::format_money($sumBalance / $exchange_rate);

                    $inAuctionTransactions[] = $one_row;
                }
            }

            $inAuctionTransactions[] = [
                'index_no' => '',
                'date' => '',
                'reference_no' => '',
                'lotnumber' => '',
                'description' => 'Total',
                'debit' => Helpers::format_money($totaldebit / $exchange_rate),
                'credit' => Helpers::format_money($totalcredit / $exchange_rate),
                'remaining' => Helpers::format_money($sumBalance / $exchange_rate),
                'balance' => Helpers::format_money($sumBalance / $exchange_rate),
            ];

            usort($inAuctionTransactions, function($a, $b){
                return strtotime($a['date']) - strtotime($b['date']);
            });
        }

        $debitPrevious = $creditPrevious = $Previous_Balance =$sumBalance=  0;
        if($arrived_status != -1){

            if (!empty($previousBalances['generalEntries'])) {
                $debitPrevious = $previousBalances['generalEntries']['debit'];
                $creditPrevious = $previousBalances['generalEntries']['credit'];
                $Previous_Balance = $previousBalances['generalEntries']['balance'];
                $Previous_Balance = $previousBalances['generalEntries']['remaining'];
                $one_row = [];
                $one_row['index'] = '#';
                $one_row['date'] = '#';
                $one_row['lotnumber'] = '#';
                $one_row['ref'] = '#';
                $one_row['description'] = 'Previous Balance';
                $one_row['debit'] = Helpers::format_money($debitPrevious / $exchange_rate);
                $one_row['credit'] = Helpers::format_money($creditPrevious / $exchange_rate);
                $one_row['remaining'] = Helpers::format_money($Previous_Balance / $exchange_rate);
                $one_row['balance'] = Helpers::format_money($Previous_Balance / $exchange_rate);
                $generalTransactions[] = $one_row;
                $sumBalance = $Previous_Balance;
            }

            usort($getAllTransation, function($a, $b){
                return strtotime($a['DateOfTransAction']) - strtotime($b['DateOfTransAction']);
            });

            $indexCounter = 1;
            $totaldebit = $totalcredit = 0;
            foreach ($getAllTransation as $key => $value) {
                $totaldebit += $value['Debit'];
                $totalcredit += $value['Credit'];
                $balance = $value['Debit'] - $value['Credit'];
                $sumBalance += $balance;

                $one_row = array();
                $one_row['index'] = $indexCounter++;
                $one_row['date'] = date('j-n-y', strtotime($value['DateOfTransAction']));

                if ($user_language == 'en') {
                    $one_row['description'] = $value['Description'];
                } else {
                    $one_row['description'] = !empty($value['DescriptionAR']) ? $value['DescriptionAR'] : $value['Description'];
                }
                
                $one_row['debit'] = Helpers::format_money($value['Debit'] / $exchange_rate);
                $one_row['credit'] = Helpers::format_money($value['Credit'] / $exchange_rate);
                $one_row['remaining'] = Helpers::format_money($balance / $exchange_rate);
                $one_row['balance'] = Helpers::format_money($sumBalance / $exchange_rate);
                $generalTransactions[] = $one_row;
            }

            $generalTransactions[] = [
                'index_no' => '',
                'date' => '',
                'reference_no' => '',
                'description' => 'Total',
                'debit' => Helpers::format_money( ($totaldebit + $debitPrevious) / $exchange_rate),
                'credit' => Helpers::format_money( ($totalcredit + $creditPrevious) / $exchange_rate),
                'remaining' => Helpers::format_money($sumBalance / $exchange_rate),
                'balance' => Helpers::format_money($sumBalance / $exchange_rate),
            ];

        }

        $data = [
            'shippedCars' => $completedCarsTransactions,
            'inAuctionTransactions' => $inAuctionTransactions,
            'generalTransactions' => $generalTransactions,
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function InAuctionCarsnoAuth(Request $request)
    {
        $customer_id = $request->customer_id;
        $arrived_status = $request->arrived_status;
        $date_from = $request->date_from;
        $date_to = $request->date_to;
        $remaining_status = $request->remaining_status;
        $paid_status = $request->paid_status;
        $search = $request->search;
        $transfer_status = empty($request->transfer_status) ? 0 : $request->transfer_status;
        $date_from = empty($request->date_from) ? date('Y-01-01') : $request->date_from;
        $date_to = empty($request->date_to) ? date('Y-m-d') : $request->date_to;
        $currency = empty($request->currency) ? 'aed' : $request->currency;
        $exchange_rate = $currency == 'usd' ? Helpers::get_usd_rate() : 1;
        $user_language = empty($request->user_language) ? 'en' : $request->user_language;

        $customer_account_id = Helpers::get_customer_account_id($customer_id);

        $args = [
            'customer_id' => $customer_id,
            'customer_account_id' => $customer_account_id,
            'arrived_status' => $arrived_status,
            'remaining_status' => $remaining_status,
            'paid_status' => $paid_status,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'search' => $search,
        ];
        $carTransactions = CarService::getCarsInAuctionTransactions($args);
        $previousBalance = $previousDebit = $previousCredit = 0;
        if (!empty($date_from) && $arrived_status == 1) {
            $previousBalanceCars = CarService::getCarsInAuctionPreviousBalance($args);
            $previousDebit = $previousBalanceCars->totalDebit;
            $previousCredit = $previousBalanceCars->totalCredit;
            $previousBalance = $previousDebit - $previousCredit;
        }

        $tableData = [];

        if ($previousDebit || $previousCredit) {
            $tableData[] = [
                'index_no' => '#',
                'date' => '#',
                'reference_no' => '#',
                'lotnumber' => '#',
                'description' => 'Previous Balance',
                'debit' => Helpers::format_money($previousDebit),
                'credit' => Helpers::format_money($previousCredit),
                'remaining' => Helpers::format_money($previousBalance),
                'balance' => Helpers::format_money($previousBalance),
            ];
        }

        $sumBalance = $previousBalance;
        $totalDebit = $totalCredit = 0;
        if ($arrived_status != 0) {
            foreach ($carTransactions as $key => $row) {
                $totalDebit += $row->Debit;
                $totalCredit += $row->Credit;
                $transfer_balance = round($row->Debit - $row->Credit, 2);

                if (($transfer_status == '1' && $transfer_balance <= 0) || ($transfer_status == '2' && $transfer_balance > 0) || ($transfer_status == '0')) {
                    $sumBalance += $transfer_balance;
                    $tableRow = [];
                    $tableRow['index_no'] = '>';
                    $tableRow['date'] = date('Y-m-d', strtotime($row->DateOfTransAction));

                    $tableRow['reference_no'] = '';
                    $tableRow['lotnumber'] = $row->lotnumber;

                    if ($user_language == 'en') {
                        $customDescription = "The total Debit : {$row->Debit} and the total Credit : {$row->Credit} for the Car with the lot number vehicle : {$row->lotnumber}";
                    } else {
                        $customDescription = "The total Debit : {$row->Debit} and the total Credit : {$row->Credit} for the Car with the lot number vehicle : {$row->lotnumber}";
                    }
                    $tableRow['description'] = $customDescription;
                    $tableRow['debit'] = Helpers::format_money($row->Debit / $exchange_rate);
                    $tableRow['credit'] = Helpers::format_money($row->Credit / $exchange_rate);
                    $tableRow['remaining'] = Helpers::format_money($transfer_balance / $exchange_rate);
                    $tableRow['balance'] = Helpers::format_money($sumBalance / $exchange_rate);

                    $tableData[] = $tableRow;
                }
            }

            $tableData[] = [
                'index_no' => '',
                'date' => '',
                'reference_no' => '',
                'lotnumber' => '',
                'description' => 'Total',
                'debit' => Helpers::format_money($totalDebit / $exchange_rate),
                'credit' => Helpers::format_money($totalCredit / $exchange_rate),
                'remaining' => Helpers::format_money($sumBalance / $exchange_rate),
                'balance' => Helpers::format_money($sumBalance / $exchange_rate),
            ];
        }

        $data = [
            'data' => $tableData,
            'totalRecords' => count($tableData)
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function carStatementGeneralEntriesnoAuth_(Request $request)
    {
        $customer_id = $request->customer_id;
        $arrived_status = $request->arrived_status;
        $date_from = $request->date_from;
        $date_to = $request->date_to;
        $search = $request->search;
        $date_from = empty($request->date_from) ? date('Y-01-01') : $request->date_from;
        $date_to = empty($request->date_to) ? date('Y-m-d') : $request->date_to;
        $currency = empty($request->currency) ? 'aed' : $request->currency;
        $exchange_rate = $currency == 'usd' ? Helpers::get_usd_rate() : 1;
        $user_language = empty($request->user_language) ? 'en' : $request->user_language;

        $customer_account_id = Helpers::get_customer_account_id($customer_id);

        $args = [
            'customer_id' => $customer_id,
            'customer_account_id' => $customer_account_id,
            'arrived_status' => $arrived_status,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'search' => $search,
        ];
        $generalTransactions = CarService::getGeneralTransactions($args);

        $previousBalance = $previousDebit = $previousCredit = 0;
        if (!empty($date_from) && $arrived_status == 1) {
            $previousBalanceCars = CarService::getGeneralTransactionsPreviousBalance($args);
            $previousDebit = $previousBalanceCars->totalDebit;
            $previousCredit = $previousBalanceCars->totalCredit;
            $previousBalance = $previousDebit - $previousCredit;
        }

        $tableData = [];

        if ($previousDebit || $previousCredit) {
            $tableData[] = [
                'index_no' => '#',
                'date' => '#',
                'reference_no' => '#',
                'description' => 'Previous Balance',
                'debit' => Helpers::format_money($previousDebit),
                'credit' => Helpers::format_money($previousCredit),
                'remaining' => Helpers::format_money($previousBalance),
                'balance' => Helpers::format_money($previousBalance),
            ];
        }

        $sumBalance = $previousBalance;
        $totalDebit = $totalCredit = 0;
        if ($arrived_status != 0) {

            foreach ($generalTransactions as $key => $row) {
                $totalDebit += $row->Debit;
                $totalCredit += $row->Credit;

                $sumBalance += $balance = $row->Debit - $row->Credit;

                $tableRow = [];
                $tableRow['index_no'] = $key + 1;
                $tableRow['date'] = date('Y-m-d', strtotime($row->DateOfTransAction));

                $journalNoData = [
                    'id' => $row->Journal_id,
                    'serial_no' => $row->serial_no,
                    'rec_type' => $row->type_transaction,
                    'typePay' => $row->typePay,
                    'car_step' => $row->car_step,
                    'create_date' => $row->create_date,
                ];
                $tableRow['reference_no'] = Helpers::generataTransactionNo($journalNoData);
                $tableRow['lotnumber'] = '';

                if ($user_language == 'en') {
                    $tableRow['description'] = $row->Description;
                } else {
                    $tableRow['description'] = !empty($row->DescriptionAR) ? $row->DescriptionAR : $row->Description;
                }

                $tableRow['debit'] = Helpers::format_money($row->Debit / $exchange_rate);
                $tableRow['credit'] = Helpers::format_money($row->Credit / $exchange_rate);
                $tableRow['remaining'] = Helpers::format_money($balance / $exchange_rate);
                $tableRow['balance'] = Helpers::format_money($sumBalance / $exchange_rate);
                $tableData[] = $tableRow;
            }

            $tableData[] = [
                'index_no' => '',
                'date' => '',
                'reference_no' => '',
                'description' => 'Total',
                'debit' => Helpers::format_money( ($totalDebit + $previousDebit) / $exchange_rate),
                'credit' => Helpers::format_money( ($totalCredit + $previousCredit) / $exchange_rate),
                'remaining' => Helpers::format_money($sumBalance / $exchange_rate),
                'balance' => Helpers::format_money($sumBalance / $exchange_rate),
            ];
        }
        $data = [
            'data' => $tableData,
            'totalRecords' => count($tableData)
        ];

        return response()->json($data, Response::HTTP_OK);
    }


    
public function carStatementShippedCarsTemp(Request $request)
{
    $customer_id = $this->customer_id;
    $arrived_status = $request->arrived_status;
    $date_from = $request->date_from;
    $date_to = $request->date_to;
    $remaining_status = $request->remaining_status;
    $transfer_status = $request->transfer_status;
    $paid_status = $request->paid_status;
    $search = $request->search;
    $date_from = empty($request->date_from) ? date('Y-01-01') : $request->date_from;
    $date_from = '2020-01-01';
    $date_to = empty($request->date_to) ? date('Y-m-d') : $request->date_to;
    $currency = empty($request->currency) ? 'aed' : $request->currency;
    $exchange_rate = $currency == 'usd' ? Helpers::get_usd_rate() : 1;
    $user_language = empty($request->user_language) ? 'en' : $request->user_language;
    $closed_date = Helpers::get_closed_date();
    $customer_account_id = Helpers::get_customer_account_id($customer_id);

    $args = [
        'customer_id' => $customer_id,
        'customer_account_id' => $customer_account_id,
        'arrived_status' => $arrived_status,
        'paid_status' => $paid_status,
        'date_from' => $date_from,
        'date_to' => $date_to,
        'search' => $search,
        'closed_date' => $closed_date,
    ];

    $carStorageFines = CarService::getCronStorageFines(['customer_id' => $customer_id]);
    $carsClosingBalance = [];
    $carsClosingBalanceRemaining = [];
    if($date_from > $closed_date){
        $carsClosingBalance = CarService::getCarsClosingBalance(['customer_id' => $customer_id]);
    }

    if($date_from == date('Y-m-d', strtotime($closed_date.' +1 day'))){
        foreach($carsClosingBalance as $key => $row){
            if(strval($row['total_debit']) != strval($row['total_credit'])){
                $carsClosingBalanceRemaining[$key] = $row;
            }
        }
    }

    $fetch_data = CarService::shippedCarsData($args);
    $transactionAfterCompleted = CarService::transactionAfterCompleted($args);
    $getAllTransation = CarService::getAllTransation2CarsStatement($args);

    if($date_from <= $closed_date){
        $args_closing = $args;
        $args_closing['closingTable'] = 1;
        $args_closing['excludeOpeningJournal'] = 1;
        $fetch_data_closing = CarService::shippedCarsData($args_closing);
        $transactionAfterCompleted_closing = CarService::transactionAfterCompleted($args_closing);
        $getAllTransation_closing = CarService::getAllTransation2CarsStatement($args_closing);
        $fetch_data = array_merge($fetch_data, $fetch_data_closing);
        $transactionAfterCompleted = array_merge($transactionAfterCompleted, $transactionAfterCompleted_closing);
        $getAllTransation = array_merge($getAllTransation, $getAllTransation_closing);
    }

    $fetch_data = array_merge($fetch_data, $transactionAfterCompleted);
    // if car has transaction in both closing & active tables
    // it will come 2 times: merge the values
    $unset_array_keys = [];
    foreach($fetch_data as $key => $row){
        foreach($fetch_data as $subKey => $subRow){
            if($row->id == $subRow->id && $subKey > $key){
                $row->Debit += $subRow->Debit;
                $row->Credit += $subRow->Credit;
                $unset_array_keys [] = $subKey;
            }
        }
        $fetch_data[$key] = $row;
    }
    foreach($unset_array_keys as $key){
        unset($fetch_data[$key]);
    }
    $showen_cars_id = array_column($fetch_data, 'id');
    $showen_cars_id = array_merge($showen_cars_id, array_keys($carsClosingBalanceRemaining));
    $showen_cars_id = array_unique($showen_cars_id);
    $fetch_data_storage_remaining = CarService::carsInfoStorageFineRemaining($customer_id, $date_from, $date_to, $showen_cars_id);

    if ($arrived_status == 0) {
        $getAllTransation = [];
    }

    $args['showen_cars_id'] = $showen_cars_id;
    $previousBalances = $this->getCarReportPreviousBalance($args);

    $carsCompletedLotMapping = [];
    foreach ($fetch_data as $key => $row) {
        $carsCompletedLotMapping[$row->id] = $key;
        if (empty($row->Credit)) {
            $fetch_data[$key]->Credit = 0;
        }
    }

    $transferTransactionsCompleted = [];
    $transferTransactionsInAuction = [];

    foreach ($getAllTransation as $key => $row) {
        if ((($row['type_transaction'] == 1 && $row['car_step'] == 0 && $row['car_id'] > 0) || ($row['car_step'] == 1 && $row['car_id'] > 0)) && $row['car_customer_id'] == $customer_id) {
            $rowKey = $row['car_id'];
            if (!empty($row['completed_date']) && $row['completed_date'] <= $date_to) {
                $transferTransactionsCompleted[$key] = $row;
            } else {
                if (empty($transferTransactionsInAuction[$rowKey])) {
                    $transferTransactionsInAuction[$rowKey] = $row;
                } else {
                    $transferTransactionsInAuction[$rowKey]['Debit'] += $row['Debit'];
                    $transferTransactionsInAuction[$rowKey]['Credit'] += $row['Credit'];
                }
            }
            unset($getAllTransation[$key]);
        } else if (($row['type_transaction'] == 3 && $row['car_id'] > 0) || ($row['car_step'] > 0 && $row['car_id'] > 0 && $row['Credit'] > 0)) {
            $transferTransactionsCompleted[$key] = $row;
            unset($getAllTransation[$key]);
        } else if ($row['type_transaction'] == 2 && $row['car_id'] > 0 && !empty($row['completed_date']) && $row['completed_date'] <= $date_to) {
            $transferTransactionsCompleted[$key] = $row;
            unset($getAllTransation[$key]);
        }
    }

    // if exit car without any shipping transactions, it will not come in fetch_data
    // add from general entries
    $lastFetchDataKey = end(array_keys($fetch_data));
    foreach ($transferTransactionsCompleted as $key => $row) {
        if (!isset($carsCompletedLotMapping[$row['car_id']]) && $row['car_status'] == 4 && $row['customer_id'] == $customer_id) {
            $lastFetchDataKey++;
            $fetch_data[$lastFetchDataKey] = (object)$row;
            $carsCompletedLotMapping[$row['car_id']] = $lastFetchDataKey;
            unset($transferTransactionsCompleted[$key]);
        }
    }

    foreach ($transferTransactionsCompleted as $key => $row) {
        if (!isset($carsCompletedLotMapping[$row['car_id']])) {
            // show again in general entries, if car is not in shipped table
            $getAllTransation[$key] = $row;
            continue;
        }
        $carIndex = $carsCompletedLotMapping[$row['car_id']];
        $fetch_data[$carIndex]->Debit += $row['Debit'];
        $fetch_data[$carIndex]->Credit += $row['Credit'];
    }

    $totaldebit = $totalcredit = $sumBalance = $total = 0;
    $totalCarPrice = $totalShippingAmount = 0;
    $grandTotalDebit = $grandTotalCredit = 0;
    $completedCarsTransactions = $inAuctionTransactions = $generalTransactions = [];

    if (!empty($previousBalances['completedCars'])) {
        $storagePrevious = $previousBalances['completedCars']['storage_fine'];
        $debitPrevious = $previousBalances['completedCars']['debit'];
        $creditPrevious = $previousBalances['completedCars']['credit'];
        $Previous_Balance = $previousBalances['completedCars']['balance'];
        $Previous_Balance = $previousBalances['completedCars']['remaining'];
        $one_row = array();
        $one_row['index'] = '#';
        $one_row['date'] = '#';
        $one_row['lotnumber'] = '#';
        $one_row['description'] = 'Previous Balance';
        $one_row['storage_fine'] = Helpers::format_money($storagePrevious / $exchange_rate);
        $one_row['car_price'] = '#';
        $one_row['shipping_amount'] = '#';
        $one_row['debit'] = Helpers::format_money($debitPrevious / $exchange_rate);
        $one_row['credit'] = Helpers::format_money($creditPrevious / $exchange_rate);
        $one_row['balance'] = Helpers::format_money($Previous_Balance / $exchange_rate);
        $one_row['remaining'] = Helpers::format_money($Previous_Balance / $exchange_rate);
        $completedCarsTransactions[] = $one_row;
        $sumBalance = $Previous_Balance;
    }

    foreach ($fetch_data as $key => $row) {
        if ($arrived_status == 1) {
            $row->display_car_date =  date('Y-m-d', strtotime($row->received_create_date));
        } else {
            $row->display_car_date =  $row->purchasedate;
        }
    }

    foreach($fetch_data as $row){
        unset($carsClosingBalanceRemaining[$row->id]);
    }

    $indexCounter = 1;
    $totalStorage = $storagePrevious;
    $totalDebitClosingBalance = $totalCreditClosingBalance = 0;

    $fetch_data_closing_remaining = CarService::carsInfoClosingRemaining(array_keys($carsClosingBalanceRemaining));
    foreach ($fetch_data_closing_remaining as $key => $row) {
        $car_id = $row->id;

        if ($remaining_status != 1) {
            $fine = isset($carStorageFines[$row->id]) ? $carStorageFines[$row->id] : 0;
            $row->fine_value = $fine;
            $totalStorage += $row->fine_value;
        }

        $debit = 0;
        $credit = 0;
        if ($row->fine_value) {
            $debit = $debit + $row->fine_value;
        }

        if(isset($carsClosingBalance[$row->id])){
            $closingBalance = $carsClosingBalance[$row->id];
            $debit += $closingBalance['total_debit'];
            $credit += $closingBalance['total_credit'];
            $totalDebitClosingBalance += $closingBalance['total_debit'];
            $totalCreditClosingBalance += $closingBalance['total_credit'];
        }

        $balance = round($debit - $credit, 2);
        if ($debit == 0 && $credit == 0) {
            continue;
        }

        if ($balance == 0 && $remaining_status == 2) {
            unset($fetch_data[$key]);
            continue;
        } else if ($balance > 0 && $remaining_status == 1) {
            unset($fetch_data[$key]);
            continue;
        }

        $car_price = CarService::getCarCost($car_id, $customer_account_id);
        $totalCarPrice += $car_price;

        // if transfer transactions are included then subtract car cost else show all debit as shipping amount
        $shipping_amount = $debit > $car_price ? $debit - $car_price : $debit;
        $totalShippingAmount += $shipping_amount;

        $totaldebit += $debit;
        $totalcredit += $credit;
        $one_row = array();
        $one_row['index'] = $indexCounter++;
        $one_row['date'] = date('j-n-y', strtotime($row->display_car_date));
        $one_row['date_timestamp'] = strtotime($row->display_car_date);
        $one_row['description'] = $row->lotnumber;
        $one_row['car_id'] = $car_id;
        $one_row['car'] = [
            'lotnumber' => $row->lotnumber,
            'vin' => $row->vin,
            'year' => $row->year,
            'cancellation' => $row->cancellation,
            'carMakerName' => $row->carMakerName,
            'carModelName' => $row->carModelName,
            'vehicleName' => $row->vehicleName,
            'container_number' => $row->container_number,
        ];

        if ($row->fine_value > 0) {
            $one_row['storage_fine'] = Helpers::format_money($row->fine_value / $exchange_rate);
        } else {
            $one_row['storage_fine'] = '';
        }

        $one_row['debit_value'] = $debit;
        $one_row['credit_value'] = $credit;
        $one_row['car_price'] = Helpers::format_money($car_price / $exchange_rate);
        $one_row['shipping_amount'] = Helpers::format_money($shipping_amount / $exchange_rate);
        $one_row['debit'] = Helpers::format_money($debit / $exchange_rate);
        $one_row['credit'] = Helpers::format_money($credit / $exchange_rate);
        $one_row['remaining'] = Helpers::format_money($balance / $exchange_rate);
        $completedCarsTransactions[] = $one_row;
    }
    foreach ($fetch_data_storage_remaining as $key => $row) {
        $car_id = $row->id;

        if ($remaining_status != 1) {
            $totalStorage += $row->fine_value;
        }

        $debit = 0;
        $credit = 0;
        if ($row->fine_value) {
            $debit = $debit + $row->fine_value;
        }

        $balance = round($debit - $credit, 2);
        if ($debit == 0 && $credit == 0) {
            continue;
        }

        if ($balance == 0 && $remaining_status == 2) {
            unset($fetch_data[$key]);
            continue;
        } else if ($balance > 0 && $remaining_status == 1) {
            unset($fetch_data[$key]);
            continue;
        }

        $car_price = 0;
        $totalCarPrice += $car_price;

        $shipping_amount = 0;
        $totalShippingAmount += $shipping_amount;

        $totaldebit += $debit;
        $totalcredit += $credit;
        $one_row = array();
        $one_row['index'] = $indexCounter++;
        $one_row['date'] = date('j-n-y', strtotime($row->display_car_date));
        $one_row['date_timestamp'] = strtotime($row->display_car_date);
        $one_row['description'] = $row->lotnumber;
        $one_row['car_id'] = $car_id;
        $one_row['car'] = [
            'lotnumber' => $row->lotnumber,
            'vin' => $row->vin,
            'year' => $row->year,
            'cancellation' => $row->cancellation,
            'carMakerName' => $row->carMakerName,
            'carModelName' => $row->carModelName,
            'vehicleName' => $row->vehicleName,
            'container_number' => $row->container_number,
        ];

        if ($row->fine_value > 0) {
            $one_row['storage_fine'] = Helpers::format_money($row->fine_value / $exchange_rate);
        } else {
            $one_row['storage_fine'] = '';
        }

        $one_row['debit_value'] = $debit;
        $one_row['credit_value'] = $credit;
        $one_row['car_price'] = Helpers::format_money($car_price / $exchange_rate);
        $one_row['shipping_amount'] = Helpers::format_money($shipping_amount / $exchange_rate);
        $one_row['debit'] = Helpers::format_money($debit / $exchange_rate);
        $one_row['credit'] = Helpers::format_money($credit / $exchange_rate);
        $one_row['remaining'] = Helpers::format_money($balance / $exchange_rate);
        $completedCarsTransactions[] = $one_row;
    }
    foreach ($fetch_data as $key => $row) {
        $car_id = $row->id;

        if ($remaining_status != 1) {
            $fine = isset($carStorageFines[$row->id]) ? $carStorageFines[$row->id] : 0;
            $row->fine_value = $fine;
            $totalStorage += $row->fine_value;
        }

        $row->Discount = CarService::getDiscountFromTransaction($car_id, $customer_account_id, $date_from, $date_to);
        if($date_from <= $closed_date){
            $row->Discount += CarService::getDiscountFromTransaction($car_id, $customer_account_id, $date_from, $date_to, ['closingTable' => 1]);
        }

        $debit = $row->Debit - $row->Discount;
        $credit = $row->Credit;

        if ($row->fine_value) {
            $debit = $debit + $row->fine_value;
        }

        if(isset($carsClosingBalance[$row->id])){
            $closingBalance = $carsClosingBalance[$row->id];
            $debit += $closingBalance['total_debit'];
            $credit += $closingBalance['total_credit'];
            $totalDebitClosingBalance += $closingBalance['total_debit'];
            $totalCreditClosingBalance += $closingBalance['total_credit'];
        }

        $balance = round($debit - $credit, 2);
        if ($debit == 0 && $credit == 0) {
            continue;
        }

        if ($balance == 0 && $remaining_status == 2) {
            unset($fetch_data[$key]);
            continue;
        } else if ($balance > 0 && $remaining_status == 1) {
            unset($fetch_data[$key]);
            continue;
        }

        $car_price = CarService::getCarCost($car_id, $customer_account_id);
        $totalCarPrice += $car_price;

        // if transfer transactions are included then subtract car cost else show all debit as shipping amount
        $shipping_amount = $debit > $car_price ? $debit - $car_price : $debit;
        $totalShippingAmount += $shipping_amount;

        $totaldebit += $debit;
        $totalcredit += $credit;
        $one_row = array();
        $one_row['index'] = $indexCounter++;
        $one_row['date'] = date('j-n-y', strtotime($row->display_car_date));
        $one_row['date_timestamp'] = strtotime($row->display_car_date);
        $one_row['description'] = $row->lotnumber;
        $one_row['car_id'] = $car_id;
        $one_row['car'] = [
            'lotnumber' => $row->lotnumber,
            'vin' => $row->vin,
            'year' => $row->year,
            'cancellation' => $row->cancellation,
            'carMakerName' => $row->carMakerName,
            'carModelName' => $row->carModelName,
            'vehicleName' => $row->vehicleName,
            'container_number' => $row->container_number,
        ];

        if ($row->fine_value > 0) {
            $one_row['storage_fine'] = Helpers::format_money($row->fine_value / $exchange_rate);
        } else {
            $one_row['storage_fine'] = '';
        }

        $one_row['debit_value'] = $debit;
        $one_row['credit_value'] = $credit;
        $one_row['car_price'] = Helpers::format_money($car_price / $exchange_rate);
        $one_row['shipping_amount'] = Helpers::format_money($shipping_amount / $exchange_rate);
        $one_row['debit'] = Helpers::format_money($debit / $exchange_rate);
        $one_row['credit'] = Helpers::format_money($credit / $exchange_rate);
        $one_row['remaining'] = Helpers::format_money($balance / $exchange_rate);
        $completedCarsTransactions[] = $one_row;
    }

    usort($completedCarsTransactions, function($a, $b){
        if(empty($a['date_timestamp'])) return true;
        return $a['date_timestamp'] - $b['date_timestamp'];
    });

    $sumBalance = 0;
    foreach ($completedCarsTransactions as $key => $row) {
        $row['index'] = $row['index'] == '#' ? '#' : $key + 1;
        $sumBalance += $row['debit_value'] - $row['credit_value'];
        $row['balance'] = Helpers::format_money($sumBalance / $exchange_rate);
        $completedCarsTransactions[$key] = $row;
    }
    $one_row['car_id'] = $car_id;
    $one_row['index'] = '';
    $one_row['date'] = '';
    $one_row['lotnumber'] = '';
    $one_row['description'] = 'Total = ';
    $one_row['storage_fine'] = Helpers::format_money($totalStorage / $exchange_rate);
    $one_row['car_price'] = Helpers::format_money($totalCarPrice / $exchange_rate);
    $one_row['shipping_amount'] = Helpers::format_money($totalShippingAmount / $exchange_rate);
    $one_row['debit'] = Helpers::format_money(($totaldebit + $debitPrevious) / $exchange_rate);
    $one_row['credit'] = Helpers::format_money(($totalcredit + $creditPrevious) / $exchange_rate);
    $one_row['remaining'] = Helpers::format_money($sumBalance / $exchange_rate);
    $one_row['balance'] = Helpers::format_money($sumBalance / $exchange_rate);
    $grandTotalDebit += ($totaldebit + $debitPrevious);
    $grandTotalCredit += ($totalcredit + $creditPrevious);
    $completedCarsTransactions[] = $one_row;

    $totaldebit = $totalcredit = $sumBalance = $debitPrevious = $creditPrevious = 0;
    if ($arrived_status != 0 && (!empty($transferTransactionsInAuction) || !empty($previousBalances['inAuctions'])) ) {
        if (!empty($previousBalances['inAuctions'])) {
            $debitPrevious = $previousBalances['inAuctions']['debit'];
            $creditPrevious = $previousBalances['inAuctions']['credit'];
            $Previous_Balance = $previousBalances['inAuctions']['balance'];
            $Previous_Balance = $previousBalances['inAuctions']['remaining'];
            $one_row = array();
            $one_row['index'] = '#';
            $one_row['date'] = '#';
            $one_row['lotnumber'] = '#';
            $one_row['ref'] = '#';
            $one_row['description'] = 'Previous Balance';
            $one_row['debit'] = Helpers::format_money($debitPrevious / $exchange_rate);
            $one_row['credit'] = Helpers::format_money($creditPrevious / $exchange_rate);
            $one_row['balance'] = Helpers::format_money($Previous_Balance / $exchange_rate);
            $one_row['remaining'] = Helpers::format_money($Previous_Balance / $exchange_rate);
            $inAuctionTransactions[] = $one_row;
            $sumBalance = $Previous_Balance;
        }

        usort($transferTransactionsInAuction, function($a, $b){
            return strtotime($a['DateOfTransAction']) - strtotime($b['DateOfTransAction']);
        });
  

        foreach ($transferTransactionsInAuction as $key => $value) {

            if(isset($carsClosingBalance[ $value['car_id'] ])){
                $closingBalance = $carsClosingBalance[ $value['car_id'] ];
                $value['Debit'] += $closingBalance['total_debit'];
                $value['Credit'] += $closingBalance['total_credit'];
                $totalDebitClosingBalance += $closingBalance['total_debit'];
                $totalCreditClosingBalance += $closingBalance['total_credit'];
            }

            $transferRow = AccountingService::getRemainingCarTransfer($customer_account_id, $value['car_id']);
            $remaining_balance = number_format($transferRow->totalDebit - $transferRow->totalCredit, 2);

            if (($transfer_status == '1' && $remaining_balance <= 0) || ($transfer_status == '2' && $remaining_balance > 0) || ($transfer_status == '0')) {
                $remaining_balance = $value['Debit'] - $value['Credit'];
                $totaldebit += $value['Debit'];
                $totalcredit += $value['Credit'];

                $sumBalance += $remaining_balance;
                $one_row = [];
                $one_row['index_no'] = '>';
                $one_row['date'] = date('j-n-y', strtotime($value['DateOfTransAction']));
                $one_row['reference_no'] = '';
                $one_row['lotnumber'] = $value['lotnumber'];

                if ($user_language == 'en') {
                    $customDescription = "The total Debit : {$value['Debit']} and the total Credit : {$value['Credit']} for the Car with the lot number vehicle : {$value['lotnumber']}";
                } else {
                    $customDescription = "The total Debit : {$value['Debit']} and the total Credit : {$value['Credit']} for the Car with the lot number vehicle : {$value['lotnumber']}";
                }

                $one_row['description'] = $customDescription;
                $one_row['debit'] = Helpers::format_money($value['Debit'] / $exchange_rate);
                $one_row['credit'] = Helpers::format_money($value['Credit'] / $exchange_rate);
                $one_row['remaining'] = Helpers::format_money($remaining_balance / $exchange_rate);
                $one_row['balance'] = Helpers::format_money($sumBalance / $exchange_rate);

                $inAuctionTransactions[] = $one_row;
            }
        }  
       
  
        $inAuctionTransactions[] = [
            'index_no' => '',
            'date' => '',
            'reference_no' => '',
            'lotnumber' => '',
            'description' => 'Total',
            'debit' => Helpers::format_money($totaldebit / $exchange_rate),
            'credit' => Helpers::format_money($totalcredit / $exchange_rate),
            'remaining' => Helpers::format_money($sumBalance / $exchange_rate),
            'balance' => Helpers::format_money($sumBalance / $exchange_rate),
        ];

        
    }

    $customer_opening_entry_exist = false;
    foreach ($getAllTransation as $key => $value) {
        if($value['car_step'] == Constants::CAR_STEPS['CLOSING_CUSTOMER_BALANCE']){
            $net_opening_balance = $value['Debit'] - $value['Credit'];
            $net_opening_balance -= $totalDebitClosingBalance - $totalCreditClosingBalance;
            $value['Debit'] = $net_opening_balance > 0 ? $net_opening_balance : 0;
            $value['Credit'] = $net_opening_balance < 0 ? abs($net_opening_balance) : 0;
            $getAllTransation[$key] = $value;
            $customer_opening_entry_exist = true;
            break;
        }
    }

    if(!$customer_opening_entry_exist && empty($previousBalances['generalEntries']) && ($totalDebitClosingBalance || $totalCreditClosingBalance) ){
        $previousBalances['generalEntries'] = [
            'debit' => 0,
            'credit' => 0,
            'balance' => 0,
            'remaining' => 0,
        ];
    }

    $debitPrevious = $creditPrevious = $Previous_Balance =$sumBalance=  0;
    if($arrived_status != -1){

        if (!empty($previousBalances['generalEntries'])) {
            $debitPrevious = $previousBalances['generalEntries']['debit'];
            $creditPrevious = $previousBalances['generalEntries']['credit'];
            $Previous_Balance = $previousBalances['generalEntries']['balance'];
            $Previous_Balance = $previousBalances['generalEntries']['remaining'];

            if(!$customer_opening_entry_exist){
                $net_opening_balance = $totalDebitClosingBalance - $totalCreditClosingBalance;
                $debitPrevious -= $net_opening_balance > 0 ? $net_opening_balance : 0;
                $creditPrevious -= $net_opening_balance < 0 ? $net_opening_balance : 0;

                if($debitPrevious < 0){
                    $creditPrevious += abs($debitPrevious);
                    $debitPrevious = 0;
                }
                if($creditPrevious < 0){
                    $debitPrevious += abs($creditPrevious);
                    $creditPrevious = 0;
                }

                $Previous_Balance = $debitPrevious - $creditPrevious;
            }

            $one_row = [];
            $one_row['index'] = '#';
            $one_row['date'] = '#';
            $one_row['lotnumber'] = '#';
            $one_row['ref'] = '#';
            $one_row['description'] = 'Previous Balance';
            $one_row['debit'] = Helpers::format_money($debitPrevious / $exchange_rate);
            $one_row['credit'] = Helpers::format_money($creditPrevious / $exchange_rate);
            $one_row['remaining'] = Helpers::format_money($Previous_Balance / $exchange_rate);
            $one_row['balance'] = Helpers::format_money($Previous_Balance / $exchange_rate);
            $generalTransactions[] = $one_row;
            $sumBalance = $Previous_Balance;
        }

        usort($getAllTransation, function($a, $b){
            return strtotime($a['DateOfTransAction']) - strtotime($b['DateOfTransAction']);
        });

        $indexCounter = 1;
        $totaldebit = $totalcredit = 0;
        foreach ($getAllTransation as $key => $value) {
            $totaldebit += $value['Debit'];
            $totalcredit += $value['Credit'];
            $balance = $value['Debit'] - $value['Credit'];
            $sumBalance += $balance;

            $one_row = array();
            $one_row['index'] = $indexCounter++;
            $one_row['date'] = date('j-n-y', strtotime($value['DateOfTransAction']));

            if ($user_language == 'en') {
                $one_row['description'] = $value['Description'];
            } else {
                $one_row['description'] = !empty($value['DescriptionAR']) ? $value['DescriptionAR'] : $value['Description'];
            }
            
            $one_row['debit'] = Helpers::format_money($value['Debit'] / $exchange_rate);
            $one_row['credit'] = Helpers::format_money($value['Credit'] / $exchange_rate);
            $one_row['remaining'] = Helpers::format_money($balance / $exchange_rate);
            $one_row['balance'] = Helpers::format_money($sumBalance / $exchange_rate);
            $generalTransactions[] = $one_row;
        }

        $generalTransactions[] = [
            'index_no' => '',
            'date' => '',
            'reference_no' => '',
            'description' => 'Total',
            'debit' => Helpers::format_money( ($totaldebit + $debitPrevious) / $exchange_rate),
            'credit' => Helpers::format_money( ($totalcredit + $creditPrevious) / $exchange_rate),
            'remaining' => Helpers::format_money($sumBalance / $exchange_rate),
            'balance' => Helpers::format_money($sumBalance / $exchange_rate),
        ];

    }

    $data = [
        'shippedCars' => $completedCarsTransactions,
        'inAuctionTransactions' => $inAuctionTransactions,
        'generalTransactions' => $generalTransactions,
    ];



    return $data;

}
function getCustomerBalanceTemp(Request $request){

    $this->customer_id = $request->customer_id;
    $request->arrived_status = 1;
    $request->remaining_status = 0;
    $request->transfer_status = 0;
    $request->paid_status = 0;
    $request->transfer_status = 0;

    //$request->partialStatementOnly = 'inAuctionTransactions';
    $responseData = $this->carStatementShippedCarsTemp($request);
    //$parsedData = json_decode($responseData->data, true);
    $amount1 = $responseData['inAuctionTransactions'];
    $amount2 = $responseData['shippedCars'];
    $amount3 = $responseData['generalTransactions'];

    $balance1 = ($amount1)? $amount1[count($amount1)-1]['balance']:0;
    $balance2 = ($amount2)? $amount2[count($amount2)-1]['balance']:0;
    $balance3 = ($amount3)? $amount3[count($amount3)-1]['balance']:0;
    $balance1 = floatval(str_replace(',', '', $balance1));
    $balance2 = floatval(str_replace(',', '', $balance2));
    $balance3 = floatval(str_replace(',', '', $balance3));

    $balance = $balance1 + $balance2 + $balance3;
    $output = array(
        "data"      => number_format($balance,2),
    );
    return response()->json($output, Response::HTTP_OK);
}

function getCustomerBalanceTempAuth(Request $request){

    $request->arrived_status = 1;
    $request->remaining_status = 0;
    $request->transfer_status = 0;
    $request->paid_status = 0;
    $request->transfer_status = 0;

    //$request->partialStatementOnly = 'inAuctionTransactions';
    $responseData = $this->carStatementShippedCarsTemp($request);
    //$parsedData = json_decode($responseData->data, true);
    $amount1 = $responseData['inAuctionTransactions'];
    $amount2 = $responseData['shippedCars'];
    $amount3 = $responseData['generalTransactions'];

    $balance1 = ($amount1)? $amount1[count($amount1)-1]['balance']:0;
    $balance2 = ($amount2)? $amount2[count($amount2)-1]['balance']:0;
    $balance3 = ($amount3)? $amount3[count($amount3)-1]['balance']:0;
    $balance1 = floatval(str_replace(',', '', $balance1));
    $balance2 = floatval(str_replace(',', '', $balance2));
    $balance3 = floatval(str_replace(',', '', $balance3));

    $balance = $balance1 + $balance2 + $balance3;
    $output = array(
        "data"      => round($balance,2),
    );
    return response()->json($output, Response::HTTP_OK);
}

    public function carStatementDepositDetail(Request $request){
        $args = ['deposit_id' => $request->deposit_id];

        try{
            $deposit = AccountingService::getCustomerDeposit($args);
            $shipping = AccountingService::getDepositDetailShipping($args['deposit_id']);
            $receipt = AccountingService::getDepositDetailReceipt($args['deposit_id']);
            $transfer = AccountingService::getDepositDetailTransfer($args['deposit_id']);

            $data = [
                'deposit' => $deposit,
                'shipping' => $shipping,
                'receipt' => $receipt,
                'transfer' => $transfer,
            ];
            return response()->json($data, Response::HTTP_OK);
        }
        catch(\Exception $e){
            $output = array(
                'success'=> false,
                'message' => $e->getMessage()
            );
            return response()->json($output, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
