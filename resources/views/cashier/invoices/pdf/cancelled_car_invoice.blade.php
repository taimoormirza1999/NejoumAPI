@extends('layouts.print')

@section('page_title')
Car Order : {{$invoiceData->Inv_no}} - {{date('Y-m-d')}}
@stop


@section('styles')
<style>
    .print-title {
        border: 1px solid #c12f2b;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        font-size: 22px;
        color: #000
    }

    .invoice-headers td>div {
        border: 1px solid #c12f2b;
        border-radius: 8px;
        padding: 10px;
    }

    .invoice-headers td+td>div {
        margin-left: 15%;
        width: 85%
    }

    .print-content.ar .invoice-headers td+td>div {
        margin-left: 0;
        margin-right: 15%;
        width: 85%
    }
</style>
@stop

@section('content')

<?php

if ($invoiceData->invoice_type == 1) {
    $invoiceStatus = 'Paid';
    $invoiceTitle = 'New Car Details';
} else {
    $invoiceStatus = 'Canceled';
    $invoiceTitle = 'Canceled Car Details';
}

?>

<br><br><br><br><br>

<div class="print-content {{app('translator')->getLocale()}}">

    <p>{{__('print date')}}: {{date('d-m-Y')}}</p><br>

    <h3 class="print-title">{{__('car order')}}</h3>

    <table class="invoice-headers">
        <tr>
            <td>
                <div>
                    <?php
                    if(Helpers::is_english()){
                        $customer_name_col = 'customer_name';
                    }
                    else{
                        $customer_name_col = 'customer_name_ar';
                    }
                    ?>

                    <h5 class="mb-0"><b>{{$invoiceData->$customer_name_col}}</b></h5>
                    <div>{{__('customer type')}}: {{$invoiceData->customer_type}}</div>
                    <div>{{__('membership id')}}: {{$invoiceData->membership_id}}</div>
                </div>
            </td>
            <td>
                <div>
                    <div><b>{{__('order')}} #</b>: {{$invoiceData->Inv_no}}</div>
                    <div>{{__('status')}}: {{$invoiceStatus}}</div>
                    <div>{{__('date')}}: {{date('j F, Y h:i:sa', strtotime($invoiceData->create_date))}}</div>
                </div>
            </td>
        </tr>
    </table>

    <br>
    <table class="table-bordered">
        <tr class="text-center">
            <th>#</th>
            <th>{{__('photo')}}</th>
            <th>{{__('vehicle detail')}}</th>
            <th>{{__('price')}}</th>
            <th>{{__('fines')}}</th>
            <th>{{__('transfer')}}</th>
            <th>{{__('advance')}}</th>
            <th>{{__('paid')}}</th>
        </tr>

        <?php
        $subTotal = $subTotalPaid = $subTotalPaidAED = $shipping_price = $subTotalAED = 0;
        $currency_rate = $invoiceData->currency_rate;
        ?>

        @foreach($invoiceDetail as $key=>$row)
        <?php
        $shippingTotal =  $row->towing_cost + $row->loading_cost + $row->shipping_cost + $row->clearance_cost + $row->transportation_cost;
        $amount_paid = $row->bill_details_paid_amount;

        if ($row->invoice_type == 1) {
            $total = $row->car_cost_dallor + $row->late_payment_dallor + $row->fine_total_cost_dallor  + ($row->tax_canada / $currency_rate) + ($row->transfermoney / $currency_rate) + ($row->advanced / $currency_rate);
            $total_fine = $row->fine_total_cost_dallor + $row->late_payment_dallor;
        } else {
            $total = $row->fine_total_cost_dallor  + ($row->transfermoney / $currency_rate);
            $total_fine = $row->fine_total_cost_dallor;
        }
        $subTotal += $total;
        $subTotalAED += $total * $currency_rate;
        $subTotalPaid += $amount_paid / $currency_rate;
        $subTotalPaidAED += $amount_paid;
        ?>
        <tr>
            <td width="25px">{{$key+1}}</td>
            <td width="90px">
                <img src='{{Constants::NEJOUM_IMAGE . "uploads/{$row->photo}"}}' width="80" height="80" /><br>
                <small>{{date('d-m-Y', strtotime($row->purchasedate))}}</small>
            </td>
            <td width="220px">
                <strong>{{__('maker')}}</strong> <?= $row->carMakerName ?> <?= $row->carModelName ?> <?= $row->year ?><br><?= $row->vin ?> <br>
                <strong>{{__('lot')}}</strong> <?= $row->lotnumber ?>-<?= $row->auction_title ?><br>
                <strong>{{__('buyer')}}</strong> <?= $row->buyer_number ?>
            </td>
            <td>
                <div>$ {{Helpers::format_money($row->car_cost_dallor + ($row->tax_canada/$currency_rate))}}</div>
                <div class="text-danger">AED {{Helpers::format_money($row->car_cost_dallor*$currency_rate + $row->tax_canada)}}</div>
            </td>
            <td>
                <div>$ {{Helpers::format_money($total_fine)}}</div>
                <div class="text-danger">AED {{Helpers::format_money($total_fine * $currency_rate)}}</div>
            </td>
            <td width="80px">
                <div>$ {{Helpers::format_money($row->transfermoney/$currency_rate)}}</div>
                <div class="text-danger">AED {{Helpers::format_money($row->transfermoney)}}</div>
            </td>
            <td>
                <div>$ {{Helpers::format_money($row->advanced/$currency_rate)}}</div>
            </td>
            <td width="120px">
                <div>$ {{Helpers::format_money($amount_paid/$currency_rate)}}</div>
                <div class="text-danger">AED {{Helpers::format_money($row->bill_details_paid_amount)}}</div>
            </td>
        </tr>
        @endforeach

        <?php
        $totalDue = $subTotal - $subTotalPaid;
        $totalDueAED = $subTotalAED - $subTotalPaidAED;
        ?>

        <tr>
            <td colspan="5" rowspan="3">
                {{__('notes')}}
                <ol class="notes">
                    <li>{{__('the above amounts do not include customs duties and taxes')}}</li>
                    <li>{{__('the sums related to shipping, transportation and clearance are estimated amounts that will be approved after the car arrives in the company warehouses')}}</li>
                    <li>{{__('customs fees and taxes are calculated according to the laws of the United Arab Emirates after the arrival of the car')}}</li>
                    <li>{{__('the customer shall bear any additional charges of fines and other additions to the vehicle, if any')}}</li>
                    <li>{{__('in the event that the customer does not pay the shipping fees within a month from the date of the cars arrival, the car sales company is entitled to any price, deducting the shipping value, and returning the remaining amount to the customer, if any')}}</li>
                </ol>
            </td>
            <td colspan="2"><b>{{__('total')}}</b></td>
            <td>
                <div>$ {{Helpers::format_money($subTotal)}}</div>
                <div>AED {{Helpers::format_money($subTotalAED)}}</div>
            </td>
        </tr>
        <tr>
            <td colspan="2"><b>{{__('paid')}}</b></td>
            <td>
                <div>$ {{Helpers::format_money($subTotalPaid)}}</div>
                <div>AED {{Helpers::format_money($subTotalPaidAED)}}</div>
            </td>
        </tr>
        <tr>
            <td colspan="2"><b>{{__('balance')}}</b></td>
            <td>
                <div>$ {{Helpers::format_money($totalDue)}}</div>
                <div>AED {{Helpers::format_money($totalDueAED)}}</div>
            </td>
        </tr>

    </table>

    <br><br>
    <table>
        <tr>
            <td><b>Cashier</b><br><br>{{$invoiceData->creator_name}}</td>
            <td class="{{Helpers::is_arabic() ? 'text-left' : 'text-right'}}"><b>Checked By</b></td>
        </tr>
    </table>

</div>

@stop