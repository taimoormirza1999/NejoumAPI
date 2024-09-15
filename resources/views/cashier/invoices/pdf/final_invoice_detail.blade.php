@extends('layouts.print')

@section('page_title')
Car statement: {{$invoiceData->final_invoice_number}} - {{date('Y-m-d')}}
@stop

@section('styles')
<style>
    .print-title{
        text-decoration: underline;
    }
    table.table-bordered,
    table.table-bordered tr,
    table.table-bordered th,
    table.table-bordered td {
        padding: 6px;
    }
</style>
@stop

@section('content')

<br><br>
<div class="print-content {{app('translator')->getLocale()}}">

    <h3 class="print-title">{{__('car statement')}}</h3>
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
                <td>{{__('order')}}: {{$invoiceData->final_invoice_number}}</td>
                <td>{{__('name')}}: {{$invoiceData->customer_name}}</td>
            </tr>
            <tr>
                <td>{{__('date')}}: {{date('j F, Y h:i:sa', strtotime($invoiceData->create_date))}}</td>
                <td>{{$invoiceData->customer_name_ar}}</td>
            </tr>
            <tr>
                <td>{{__('customer type')}}: {{$invoiceData->customer_type}}</td>
                <td>{{__('status')}}: Paid</td>
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
                {{__('auction')}}: {{$invoiceData->auction_location_name}}<br>
            </td>
            <td>
                <img src='{{Constants::NEJOUM_IMAGE . "uploads/{$invoiceData->photo}"}}' width="120" height="120" />
            </td>
        </tr>
    </table>


    <br><br>
    <h4 class="text-center">{{__('payment details')}}</h4>

    <?php
    $service_label = 'service_label_en';
    if(Helpers::is_Arabic()){
        $service_label = 'service_label_ar';
    }
    ?>

    <table class="table-bordered">
        <tr class="text-center" style="background-color: #9cc2e5;">
            <th>{{__('description')}}</th>
            <th>{{__('amount')}}</th>
        </tr>

        @foreach($transactionLabels as $row)
        <tr>
            <td>{{$row[$service_label]}}</td>
            <td class="text-center">{{Helpers::format_money( max($row['debit'], $row['credit']) )}}</td>
        </tr>
        @endforeach

        <tr style="background-color: #9cc2e5;">
            <td>{{__('total amount')}}</td>
            <td class="text-center">{{Helpers::format_money( $invoiceData->total_amount )}}</td>
        </tr>
        @if($invoiceData->previous_amount_paid > 0)
        <tr style="background-color: #9cc2e5;">
            <td>{{__('previous amount')}}</td>
            <td class="text-center">{{Helpers::format_money( $invoiceData->previous_amount_paid )}}</td>
        </tr>
        @endif
        <tr style="background-color: #9cc2e5;">
            <td>{{__('paid')}}</td>
            <td class="text-center">{{Helpers::format_money( $invoiceData->amount_paid )}}</td>
        </tr>
        <tr style="background-color: #9cc2e5;">
            <td>{{__('balance')}}</td>
            <td class="text-center">{{Helpers::format_money( $invoiceData->total_amount - ($invoiceData->amount_paid + $invoiceData->previous_amount_paid) )}}</td>
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