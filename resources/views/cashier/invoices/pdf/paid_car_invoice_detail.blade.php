@extends('layouts.print')

@section('page_title')
Car Order : {{$invoiceData->Inv_no}} - {{date('Y-m-d')}}
@stop

@section('styles')

<style>
    .print-content.ar table.transactions td:first-child{
        font-size: 20px;
    }
</style>

@stop

@section('content')

<?php

if ($invoiceData->invoice_type == 1) {
    $invoiceStatus = 'Paid';
} else {
    $invoiceStatus = 'Canceled';
}

$storageTotal = $invoiceData->late_payment_aed + $invoiceData->fine_total_cost_aed;
$shippingTotal =  $invoiceData->towing_cost + $invoiceData->loading_cost + $invoiceData->shipping_cost + $invoiceData->clearance_cost + $invoiceData->transportation_cost;
$subTotal =  $invoiceData->car_cost_aed + $invoiceData->tax_canada + $storageTotal + $invoiceData->transfermoney + $invoiceData->advanced + $shippingTotal;
$balance = $subTotal - $invoiceData->bill_details_paid_amount;

?>

<br><br><br>

<div class="print-content {{app('translator')->getLocale()}}">

    <h3 class="print-title">{{__('car order')}}</h3>
    <br>

    <p>{{__('date')}}: {{date('d-m-Y')}}</p>

    <table>
        <tr>
            <th>{{__('customer details')}}</th>
            <th class="text-center">{{__('order details')}}</th>
        </tr>
    </table>

    <div style="border:1px solid">
        <table>
            <tr>
                <td>{{__('order')}}: {{$invoiceData->Inv_no}}</td>
                <td>{{__('name')}}: {{$invoiceData->customer_name}}</td>
            </tr>
            <tr>
                <td>{{__('date')}}: {{date('j F, Y h:i:sa', strtotime($invoiceData->create_date))}}</td>
                <td>{{$invoiceData->customer_name_ar}}</td>
            </tr>
            <tr>
                <td>{{__('customer type')}}: {{$invoiceData->customer_type}}</td>
                <td>{{__('status')}}: {{$invoiceStatus}}</td>
            </tr>
            <tr>
                <td>{{__('membership id')}}: {{$invoiceData->membership_id}}</td>
                <td>{{__('buyer')}}: {{$invoiceData->buyer_number}}</td>
            </tr>
        </table>
    </div>

    <br><b>{{__('vehicle detail')}}</b>
    <table>
        <tr>
            <td>
                {{__('maker')}}: {{$invoiceData->carMakerName}}<br>
                {{__('model')}}: {{$invoiceData->carModelName}}<br>
                {{__('vin')}}: {{$invoiceData->vin}}<br>
                {{__('lot')}}: {{$invoiceData->lotnumber}}<br>
                {{__('auction')}}: {{$invoiceData->auction_title}}<br>
            </td>
            <td>
                <img src='{{Constants::NEJOUM_IMAGE . "uploads/{$invoiceData->photo}"}}' width="120" height="120" />
            </td>
        </tr>
    </table>


    <br><br>
    <h4 class="text-center">{{__('payment details')}}</h4>

    <table class="table-bordered transactions">
        <tr class="text-center">
            <th>{{__('description')}}</th>
            <th>{{__('amount')}}</th>
            <th>{{__('note')}}</th>
        </tr>

        @if($invoiceData->invoice_type == 1)
        <tr>
            <td>{{__('vehicle price')}}</td>
            <td class="text-center">{{Helpers::format_money( $invoiceData->car_cost_aed + $invoiceData->tax_canada )}}</td>
            <td></td>
        </tr>
        @endif

        <tr>
            <td>{{__('storage')}}</td>
            <td class="text-center">{{Helpers::format_money( $storageTotal )}}</td>
            <td></td>
        </tr>

        <tr>
            <td>{{__('transfer')}}</td>
            <td class="text-center">{{Helpers::format_money( $invoiceData->transfermoney )}}</td>
            <td></td>
        </tr>

        <tr>
            <td>{{__('shipment')}}</td>
            <td class="text-center">{{Helpers::format_money( $shippingTotal )}}</td>
            <td>{{__('estimated shipping')}}</td>
        </tr>

        <tr>
            <td>{{__('advance payment')}}</td>
            <td class="text-center">{{Helpers::format_money( $invoiceData->advanced )}}</td>
            <td></td>
        </tr>

        <tr>
            <td>
                <h5 style="text-align: <?=Helpers::is_arabic() ? 'left' : 'right'?>;">{{__('total')}}</h5>
            </td>
            <td class="text-center">{{Helpers::format_money( $subTotal, 'AED' )}}</td>
            <td></td>
        </tr>

        <tr>
            <td>
                <h5 style="text-align: <?=Helpers::is_arabic() ? 'left' : 'right'?>;">{{__('paid')}}</h5>
            </td>
            <td class="text-center">{{Helpers::format_money( $invoiceData->bill_details_paid_amount, 'AED' )}}</td>
            <td></td>
        </tr>

        <tr>
            <td>
                <small>{{__('the above amounts do not include customs duties and taxes')}}</small>
                <h5 style="text-align: <?=Helpers::is_arabic() ? 'left' : 'right'?>;">{{__('balance')}}</h5>
            </td>
            <td class="text-center">{{Helpers::format_money( $balance, 'AED' )}}</td>
            <td></td>
        </tr>

    </table>

    <br><br><b>{{__('notes')}}:</b>
    <ol class="notes">
        <li>{{__('the above amounts do not include customs duties and taxes')}}</li>
        <li>{{__('the sums related to shipping, transportation and clearance are estimated amounts that will be approved after the car arrives in the company warehouses')}}</li>
        <li>{{__('customs fees and taxes are calculated according to the laws of the United Arab Emirates after the arrival of the car')}}</li>
        <li>{{__('the customer shall bear any additional charges of fines and other additions to the vehicle, if any')}}</li>
        <li>{{__('in the event that the customer does not pay the shipping fees within a month from the date of the cars arrival, the car sales company is entitled to any price, deducting the shipping value, and returning the remaining amount to the customer, if any')}}</li>
    </ol>

</div>

@stop