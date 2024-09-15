<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Libraries\Constants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CarAccounting;
use App\Libraries\Helpers;
use App\Services\CarService;
use Symfony\Component\HttpFoundation\Response;

class CashierController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    public function getPaidCarInvoices(Request $request)
    {
        $args = $request->all();
        $invoices = CarAccounting::getPaidCarInvoices($args);

        return response()->json($invoices, Response::HTTP_OK);
    }

    public function getPaidCarInvoiceDetail($bill_id)
    {
        $invoice = CarAccounting::getPaidCarInvoiceDetail(['bill_id' => $bill_id]);
        return response()->json($invoice, Response::HTTP_OK);
    }

    public function getPaidCarInvoicePrintEN($bill_id)
    {
        $invoiceDetail = CarAccounting::getPaidCarInvoiceDetail(['bill_id' => $bill_id]);
        $invoiceData = reset($invoiceDetail);
        $data = ['invoiceDetail' => $invoiceDetail, 'invoiceData' => $invoiceData];

        $html = view('cashier.invoices.pdf.paid_car_invoice', $data)->render();

        $pdf = Helpers::getPDF($html);
        return $pdf->inline();
    }

    public function getPaidCarInvoicePrintAR($bill_id)
    {
        app()->setLocale('ar');
        return $this->getPaidCarInvoicePrintEN($bill_id);
    }

    public function getPaidCarInvoiceDetailPrintEN($bill_id, $car_id)
    {
        $args = [
            'bill_id' => $bill_id,
            'car_id' => $car_id,
        ];
        $invoiceDetail = CarAccounting::getPaidCarInvoiceDetail($args);
        $invoiceData = reset($invoiceDetail);
        $data = ['invoiceData' => $invoiceData];

        $html = view('cashier.invoices.pdf.paid_car_invoice_detail', $data)->render();

        $pdf = Helpers::getPDF($html);
        return $pdf->inline();
    }

    public function getPaidCarInvoiceDetailPrintAR($bill_id, $car_id)
    {
        app()->setLocale('ar');
        return $this->getPaidCarInvoiceDetailPrintEN($bill_id, $car_id);
        //return $pdf->stream("Invoice No: {$invoiceData->Inv_no} - " . date('Y-m-d'));
    }

    public function getCancelledCarInvoices(Request $request)
    {
        $args = $request->all();

        $data = [
            'invoices' => CarAccounting::getCancelledCarInvoices($args),
            'totalRecords' =>  CarAccounting::getCancelledCarInvoicesCount($args)
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function getCancelledCarInvoiceDetail($bill_id)
    {
        $invoice = CarAccounting::getPaidCarInvoiceDetail(['bill_id' => $bill_id]);
        return response()->json($invoice, Response::HTTP_OK);
    }

    public function getCancelledCarInvoiceDetailPrintEN($bill_id, $car_id)
    {
        $args = [
            'bill_id' => $bill_id,
            'car_id' => $car_id,
        ];
        $invoiceDetail = CarAccounting::getPaidCarInvoiceDetail($args);
        $invoiceData = reset($invoiceDetail);
        $data = ['invoiceData' => $invoiceData];

        $html = view('cashier.invoices.pdf.cancelled_car_invoice_detail', $data)->render();

        $pdf = Helpers::getPDF($html);
        return $pdf->inline();
    }

    public function getCancelledCarInvoiceDetailPrintAR($bill_id, $car_id)
    {
        app()->setLocale('ar');
        return $this->getCancelledCarInvoiceDetailPrintEN($bill_id, $car_id);
    }

    public function getPaidByCustomerInvoices(Request $request)
    {
        $args = $request->all();

        $data = [
            'invoices' => CarAccounting::getPaidByCustomerInvoices($args),
            'totalRecords' =>  CarAccounting::getPaidByCustomerInvoicesCount($args)
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function getFinalInvoices(Request $request)
    {
        $args = $request->all();
        $data = [
            'invoices' => CarAccounting::getFinalInvoices($args),
            'totalRecords' =>  CarAccounting::getFinalInvoicesCount($args)
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function getCarsFinalInvoices(Request $request)
    {
        $args = $request->all();
        $customerArrivedCars = CarAccounting::getCarsFinalInvoices($args);

        $arrivedCars    = [];
        $total_paid     = 0;
        foreach ($customerArrivedCars as $key => $value) {
            $dataRow = [
                'car_id'            => $value->carID,
                'lotnumber'         => $value->lotnumber,
                'carMakerName'      => $value->carMakerName,
                'carModelName'      => $value->carModelName,
                'year'              => $value->year,
                'image'             => Constants::NEJOUM_CDN . 'uploads/' . $value->photo,
                'arrival_date'      => $value->arrival_date,
                'storage'           => Helpers::format_money($value->storage_fine),
                'amount_paid'       => Helpers::format_money($value->amount_paid),
                'remaining_total'   => Helpers::format_money($value->remaining_amount),
            ];
            $total_paid += $value->remaining_amount;
            $arrivedCars[] = $dataRow;
        }

        $data = [
            'data'          => $arrivedCars,
            'totalRecords'  => CarAccounting::getCarsFinalInvoicesCount($args), 
            'total_amount'  => $total_paid,
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function getCarsFinalInvoicesCount(Request $request)
    {
        $args = $request->all();
        $args['limit'] = PHP_INT_MAX;
        $customerArrivedCars = CarAccounting::getCarsFinalInvoices($args);

        $arrivedCars    = [];
        $total_paid     = 0;
        foreach ($customerArrivedCars as $key => $value) {
            $dataRow = [
                'car_id'            => $value->carID,
                'lotnumber'         => $value->lotnumber,
                'carMakerName'      => $value->carMakerName,
                'carModelName'      => $value->carModelName,
                'year'              => $value->year,
                'image'             => Constants::NEJOUM_CDN . 'uploads/' . $value->photo,
                'arrival_date'      => $value->arrival_date,
                'storage'           => Helpers::format_money($value->storage_fine),
                'amount_paid'       => Helpers::format_money($value->amount_paid),
                'remaining_total'   => Helpers::format_money($value->remaining_amount),
            ];
            $total_paid += $value->remaining_amount;
            $arrivedCars[] = $dataRow;
        }

        $data = [
            'totalRecords'  => CarAccounting::getCarsFinalInvoicesCount($args), 
            'total_amount'  => Helpers::format_money($total_paid),
        ];

        return response()->json($data, Response::HTTP_OK);
    }


    public function getFinalInvoiceDetail($invoice_id)
    {
        $invoiceDetail = CarAccounting::getFinalInvoiceDetail(['invoice_id' => $invoice_id]);

        foreach ($invoiceDetail as $key => $row) {
            $row->amount_due += $row->storage_fine;
            $invoiceDetail[$key] = $row;
        }

        return response()->json($invoiceDetail, Response::HTTP_OK);
    }

    public function getFinalInvoicePrintEN($invoice_id)
    {
        $args = [
            'invoice_id' => $invoice_id,
        ];
        $invoiceDetail = CarAccounting::getCarFinalInvoiceDetail($args);
        $invoiceData = reset($invoiceDetail);
        $data = ['invoiceDetail' => $invoiceDetail, 'invoiceData' => $invoiceData];

        $html = view('cashier.invoices.pdf.final_invoice', $data)->render();

        $pdf = Helpers::getPDF($html);
        return $pdf->inline();
    }

    public function getFinalInvoicePrintAR($invoice_id)
    {
        app()->setLocale('ar');
        return $this->getFinalInvoicePrintEN($invoice_id);
    }

    public function getFinalInvoiceDetailPrintEN($invoice_id, $car_id)
    {
        $customer_account_id = Helpers::customer_account_id_by_car($car_id);
        $args = [
            'invoice_id' => $invoice_id,
            'car_id' => $car_id,
            'account_id' => $customer_account_id,
        ];
        $invoiceDetail = CarAccounting::getCarFinalInvoiceDetail($args);
        $carTransactions = CarAccounting::getCarAccountTransactions($args);
        $transactionLabels = Helpers::getCarTransactionLabels($carTransactions);
        $invoiceData = reset($invoiceDetail);

        foreach ($transactionLabels as $key => $value) {
            if ($value['car_step'] == 0 || $value['car_step'] == 1) {
                unset($transactionLabels[$key]);
            }
        }

        $invoiceData->total_amount = array_sum(array_column($transactionLabels, 'debit')) - array_sum(array_column($transactionLabels, 'credit'));
        $data = ['invoiceData' => $invoiceData, 'transactionLabels' => $transactionLabels];
        $html = view('cashier.invoices.pdf.final_invoice_detail', $data)->render();

        $pdf = Helpers::getPDF($html);
        return $pdf->inline();
    }

    public function getFinalInvoiceDetailPrintAR($invoice_id, $car_id)
    {
        app()->setLocale('ar');
        return $this->getFinalInvoiceDetailPrintEN($invoice_id, $car_id);
    }

    public function unpaidCarsInAuction(Request $request)
    {
        $args = $request->all();
        $args['limit'] = PHP_INT_MAX;
        $customerUnpaidCars = CarAccounting::getUnpaidCars($args);
        $auction_location_fines = CarService::getAuctionLocationFines($customerUnpaidCars);
        $unpaidCars = [];
        foreach ($customerUnpaidCars as $key => $dbRow) {

            if ($dbRow->country_id == 38) {
                $dollar_price = $dbRow->candian_dollar_rate;
                $dollarType = 'CAD';
            } else {
                $dollar_price = $dbRow->us_dollar_rate;
                $dollar_price = !empty($dbRow->contract_usd_rate) ? $dbRow->contract_usd_rate : $dbRow->us_dollar_rate;
                $dollarType = '$';
            }

            // exception for auction id 7 OR 14
            if ($dbRow->auction_id == 7 || $dbRow->auction_id == 14) {
                $dollar_price = $dbRow->us_dollar_rate;
                $dollar_price = !empty($dbRow->contract_usd_rate) ? $dbRow->contract_usd_rate : $dbRow->us_dollar_rate;
                $dollarType = '$';
            }

            $late_payment_fine = 0;$fineTotalCost = 0;
            if(!empty($auction_location_fines[$dbRow->id]['fineTotalCost'])){
                $fineTotalCost = $auction_location_fines[$dbRow->id]['fineTotalCost'];
            }
            if(!empty($auction_location_fines[$dbRow->id]['late_payment_fine'])){
                $late_payment_fine = $auction_location_fines[$dbRow->id]['late_payment_fine'];
            }

            $total_required = $dbRow->carcost * $dollar_price + $fineTotalCost * $dollar_price + $late_payment_fine * $dollar_price;

            $dataRow = [
                'car_id' => $dbRow->id,
                'lotnumber' => $dbRow->lotnumber,
                'vin' => $dbRow->vin,
                'carMakerName' => $dbRow->carMakerName,
                'carModelName' => $dbRow->carModelName,
                'year' => $dbRow->year,
                'image' => Constants::NEJOUM_CDN . 'uploads/' . $dbRow->photo,
                'purchasedate' => $dbRow->purchasedate,
                'auction_title' => $dbRow->auction_title,
                'auction_location_name' => $dbRow->auction_location_name,
                'currency_rate' => $dollar_price,
                'currency_name' => $dollarType,
                'totalUSD' => Helpers::format_money($dbRow->carcost + $fineTotalCost + $late_payment_fine),
                'totalAED' => Helpers::format_money($total_required),
                'fineTotalCost' => Helpers::format_money($fineTotalCost * $dollar_price + $late_payment_fine * $dollar_price)
            ];

            $unpaidCars[] = $dataRow;
        }

        $data = [
            'data' => $unpaidCars,
            'totalRecords' => CarAccounting::getUnpaidCarsCount($args)
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function arrivedCarsShippingCost(Request $request)
    {
        $args = $request->all();
        $customerArrivedCars = CarAccounting::getArrivedCars($args);
        $customerArrivedCars = $this->processArrivedCarsData($customerArrivedCars);
        $totalArrivedCars = CarAccounting::getArrivedCars(array_merge($args, ['limit' => PHP_INT_MAX, 'page' => 0]));
        $totalArrivedCars = count($this->processArrivedCarsData($totalArrivedCars));

        $total_required = 0;
        foreach($customerArrivedCars as $car){
            $total_required += str_replace(',', '', $car['remaining_total']);
        }

        $data = [
            'data' => $customerArrivedCars,
            'totalRecords' => $totalArrivedCars,
            'total_required' =>  $total_required,           
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function arrivedCarsShippingCostCount(Request $request)
    {
        $args = $request->all();
        $args['limit'] = PHP_INT_MAX;
        $customerArrivedCars = CarAccounting::getArrivedCars($args);
        $customerArrivedCars = $this->processArrivedCarsData($customerArrivedCars);
        $totalArrivedCars = CarAccounting::getArrivedCars(array_merge($args, ['limit' => PHP_INT_MAX, 'page' => 0]));
        $totalArrivedCars = count($this->processArrivedCarsData($totalArrivedCars));

        $total_required = 0;
        foreach($customerArrivedCars as $car){
            $total_required += str_replace(',', '', $car['remaining_total']);
        }

        $data = [
            'totalRecords' => $totalArrivedCars,
            'total_required' =>  number_format($total_required, 2),           
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    private function processArrivedCarsData($customerArrivedCars){
        $arrivedCars = [];
        foreach ($customerArrivedCars as $key => $value) {
            $car_id = $value['carID'];
            $calculateStorage = CarService::storageFine($car_id);
            if ($calculateStorage) {
                $value['fine_value'] = $calculateStorage['fine'];
                $value['enter_date_transport'] = $calculateStorage['enter_date_transport'];
                $value['days_transport'] = $calculateStorage['days_transport'];
                $value['start_transport'] = $calculateStorage['start_transport'];
                $value['end_transport'] = $calculateStorage['end_transport'];
                $value['warehouse_transport'] = $calculateStorage['warehouse_transport'];
                $value['fine_transport'] = $calculateStorage['fine_transport'];
                $value['deliver_create_date'] = $calculateStorage['deliver_create_date'];
                $value['deliver'] = $calculateStorage['deliver'];
                $value['days_transport_allow'] = $calculateStorage['days_transport_allow'];

                if ($calculateStorage['warehouse_warehouse']) {
                    for ($i = 0; $i < COUNT($calculateStorage['warehouse_warehouse']); $i++) {
                        $value['days_warehouse'][$i] = $calculateStorage['days_warehouse'][$i];
                        $value['start_warehouse'][$i] = $calculateStorage['start_warehouse'][$i];
                        $value['end_warehouse'][$i] = $calculateStorage['end_warehouse'][$i];
                        $value['warehouse_warehouse'][$i] = $calculateStorage['warehouse_warehouse'][$i];
                        $value['fine_wearhouse'][$i] = $calculateStorage['fine_wearhouse'][$i];
                        $value['enter_date_warehouse'][$i] = $calculateStorage['enter_date_warehouse'][$i];
                        $value['days_warehouse_allow'][$i] = $calculateStorage['days_warehouse_allow'][$i];
                    }
                }
            }

            if (is_numeric($value['fine_value'])) {
                $value['amount_required'] += $value['fine_value'];
                $value['remaining_amount'] += $value['fine_value'];
            }

            $extraTotal = $value['towing_fine'] + $value['recovery_price'] + $value['sale_vat'] + $value['autoExtra2'] + $value['general_extra_value'] + $value['shipping_commission'];

            if($value['remaining_amount'] <= 0){
                continue;
            }

            $dataRow = [
                'car_id' => $value['carID'],
                'lotnumber' => $value['lotnumber'],
                'vin' => $value['vin'],
                'carMakerName' => $value['carMakerName'],
                'carModelName' => $value['carModelName'],
                'year' => $value['year'],
                'image' => Constants::NEJOUM_CDN . 'uploads/' . $value['photo'],
                'arrival_date' => $value['enter_date_transport'],
                'extra' => Helpers::format_money($extraTotal),
                'storage' => Helpers::format_money($value['storage']),
                'amount_required' => Helpers::format_money($value['amount_required']),
                'remaining_total' => Helpers::format_money($value['remaining_amount']),
            ];
            $arrivedCars[] = $dataRow;
        }
        return $arrivedCars;
    }

    public function balanceOfTransferred(Request $request)
    {
        $args = $request->all();
        $args['customer_account_id']=Helpers::get_customer_account_id($args['customer_id']);
        $customerCars = CarAccounting::getBalanceOfTransferredCars($args);

        $balanceOfTransferredCars = [];
        foreach ($customerCars as $value) {

            $dataRow = [
                'car_id' => $value->id,
                'lotnumber' => $value->lotnumber,
                'vin' => $value->vin,
                'carMakerName' => $value->carMakerName,
                'carModelName' => $value->carModelName,
                'year' => $value->year,
                'image' => Constants::NEJOUM_CDN . 'uploads/' . $value->photo,
                'purchasedDate' => $value->purchasedate,
                'totalDebit' => Helpers::format_money($value->totalDebit),
                'totalCredit' => Helpers::format_money($value->totalCredit),
                'balance' => Helpers::format_money($value->totalDebit - $value->totalCredit)
            ];
            $balanceOfTransferredCars[] = $dataRow;
        }

        $data = [
            'data' => $balanceOfTransferredCars,
            'totalRecords' => count($balanceOfTransferredCars)
        ];

        return response()->json($data, Response::HTTP_OK);
    }
    public function cancelledCarsInAuction(Request $request)
    {
        $args = $request->all();
        
        $customerCancelledCars = CarAccounting::getCancelledCars($args);
        $cancelledCars = [];
        foreach ($customerCancelledCars as $dbRow) {

            if ($dbRow->country_id == 38) {
                $dollarPrice = $dbRow->candian_dollar_rate;
                $dollarType = 'CAD';
            } else {
                $dollarPrice = $dbRow->us_dollar_rate;
                $dollarPrice = !empty($dbRow->contract_usd_rate) ? $dbRow->contract_usd_rate : $dbRow->us_dollar_rate;
                $dollarType = '$';
            }

            // exception for auction id 7 OR 14
            if ($dbRow->auction_id == 7 || $dbRow->auction_id == 14) {
                $dollarPrice = $dbRow->us_dollar_rate;
                $dollarPrice = !empty($dbRow->contract_usd_rate) ? $dbRow->contract_usd_rate : $dbRow->us_dollar_rate;
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
                'car_id' => $dbRow->id,
                'lotnumber' => $dbRow->lotnumber,
                'vin' => $dbRow->vin,
                'carMakerName' => $dbRow->carMakerName,
                'carModelName' => $dbRow->carModelName,
                'year' => $dbRow->year,
                'image' => Constants::NEJOUM_CDN . 'uploads/' . $dbRow->photo,
                'purchaseDate' => $dbRow->purchasedate,
                'auctionTitle' => $dbRow->auction_title,
                'auctionLocationName' => $dbRow->auction_location_name,
                'currencyRate' => $dollarPrice,
                'currencyName' => $dollarType,
                'cancellationDate' => $cancellationDate,
                'totalAED' => Helpers::format_money($cancel_fine_cost_aed)
            ];
            $cancelledCars[] = $dataRow;
        }

        $data = [
            'data' => $cancelledCars,
            'totalRecords' => CarAccounting::getCancelledCarsCount($args)
        ];

        return response()->json($data, Response::HTTP_OK);
    }


}
