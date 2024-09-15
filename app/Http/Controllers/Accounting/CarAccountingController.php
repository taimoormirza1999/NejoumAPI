<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CarAccounting;
use App\Libraries\Helpers;
use App\Libraries\Constants;
use App\Services\CarService;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
class CarAccountingController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function getExtraDetail($car_id)
    {
        $args = ['car_id' => $car_id];
        $extraDetail = CarAccounting::getCarExtraDetail($args);
        $extraDetail->totalExtra =  $extraDetail->general_extra + $extraDetail->sale_vat
            + $extraDetail->shipping_commission + $extraDetail->towing_fine + $extraDetail->recovery_price + $extraDetail->auto_extra;

        $data = Helpers::getExtraDetailLabels($extraDetail);
        return response()->json([
            'total' => $extraDetail->totalExtra,
            'data' => $data
        ], Response::HTTP_OK);
    }

    public function storageFine($car_id){
        $storageFine =  \App\Services\CarService::storageFine($car_id);
        return response()->json([
            'data' => $storageFine
        ], Response::HTTP_OK);
    }

    public function storageFinePerDay($car_id){
        $args = ['car_id' => $car_id];
        $finePerDay = CarAccounting::storageFinePerDay($args);

        return response()->json([
            'data' => $finePerDay
        ], Response::HTTP_OK);

    }

    private function mergeCarStepsTransactions($carTransactions){
        $extraTowing = [];
        foreach($carTransactions as $key => $transaction){
            if($transaction->car_step == 101){ // Extra Towing
                if(empty($extraTowing)){
                $extraTowing['Debit'] = $transaction->Debit;
                $extraTowing['Credit'] = $transaction->Credit;
                } else {
                    $extraTowing['Debit'] += $transaction->Debit;
                    $extraTowing['Credit'] += $transaction->Credit;  
                }
                unset($carTransactions[$key]);
            }
        }
        foreach($carTransactions as $key => $transaction){
            if($transaction->car_step == 2){ // Towing
                $transaction->Debit += $extraTowing['Debit'];
                $transaction->Credit += $extraTowing['Credit'];
            }
            $carTransactions[$key] = $transaction;
        }

        // combine invoice entries by car step
        $combineTransactionsSteps = [1112];
        foreach($carTransactions as $key => $transaction){
            if(in_array($transaction->car_step, $combineTransactionsSteps)){
                
                foreach($carTransactions as $subKey => $subRow){
                    if($subKey <= $key) continue;

                    if($transaction->car_step == $subRow->car_step){
                        $transaction->Debit += $subRow->Debit;
                        $transaction->Credit += $subRow->Credit;
                        $carTransactions[$key] = $transaction;
                        unset($carTransactions[$subKey]);
                    }
                }
            }
        }

        return $carTransactions;
    }

    public function shippingBillDetail($car_id, Request $request){
        $currency = empty($request->currency) ? 'aed' : $request->currency;
        $exchange_rate = $currency == 'usd' ? Helpers::get_usd_rate() : 1;

        $customer_account_id = Helpers::customer_account_id_by_car($car_id);
        if(Auth::user()->customer_id && $customer_account_id != Helpers::get_customer_account_id(Auth::user()->customer_id)){
            return response()->json([
                'data' => [],
                'total' => '',
            ], Response::HTTP_OK);
        }
        $args = [
            'car_id' => $car_id,
            'account_id' => $customer_account_id,
        ];
        $carTransactions = CarAccounting::getCarAccountTransactions($args);
        $carTransactions = $this->mergeCarStepsTransactions($carTransactions);

        if($carTransactions){
            $carTransactions[0]->storageAfterPayment = CarService::storageFine($car_id)['fine'];
            $carTransactions[0]->carAccountingNotes = CarAccounting::getCarAccountingNotes($args);
        }

        $transactionLabels = Helpers::getCarTransactionLabels($carTransactions, $exchange_rate);
        $totalAmount = array_sum(array_column($transactionLabels, 'debit')) - array_sum(array_column($transactionLabels, 'credit'));
        $totalAmount = Helpers::format_money($totalAmount);

        return response()->json([
            'data' => $transactionLabels,
            'total' => $totalAmount,
        ], Response::HTTP_OK);

    }

    public function shippingBillDetailPrint($car_id, Request $request){

        $currency = empty($request->currency) ? 'aed' : $request->currency;
        $endpoint = Constants::NEJOUM_SYSTEM."Nejoum_App/printShippingBill/$car_id";
        $client = new \GuzzleHttp\Client();

        $response = $client->request('get', $endpoint, ['query' => [
            'requested_by' => 'api', 
            'currency' => $currency,
        ]]);

        $content = $response->getBody()->getContents();
        return response()->json([
            'html' => $content,
        ], Response::HTTP_OK);
    }
}
