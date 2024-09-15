@extends('layouts.print')

@section('page_title')
Estimated Car Shipping Details : - {{date('Y-m-d')}}
@stop

@section('styles')

<style>

    .print-title {
        border: 1px solid #357ebd;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        font-size: 22px;
        color: #000
    }
    .customer-detail{
        border: 1px solid #357ebd;
        border-radius: 8px;
        padding: 8px;
        max-width: 320px;
    }

</style>

@stop

@section('content')

<php ?>

<br><br><br>

<div class="print-content {{app('translator')->getLocale()}}">

    <h3 class="print-title">{{__('estimated car shipping details')}}</h3>
    <br>

    <div class="customer-detail">
        <table>
            <tr>
                <td>
                    <p><b>To</b></p>
                    <div>{{__('name')}}: {{$shippingDetail->customer_name}}<br>{{$shippingDetail->customer_name_ar}}</div>
                    <div>{{__('customer type')}}: {{$shippingDetail->customer_type}}</div>
                    <div>{{__('membership id')}}: {{$shippingDetail->membership_id}}</div>
                </td>
            </tr>
        </table>
    </div>

    <br><br>
    <table class="table-bordered transactions">
        <tr class="text-center">
            <th>{{__('services')}}</th>
            <th>{{__('price')}} <small>(AED)</small></th>
        </tr>

        <tr>
            <td>{{__('towing')}}</td>
            <td class="text-center">
                {{Helpers::format_money( $shippingDetail->towing_cost)}}
                <small class="text-danger">{{empty($shippingDetail->towing_cost) ? 'Missing Prices' : ''}}</small>
            </td>
        </tr>

        <tr>
            <td>{{__('shipping')}}</td>
            <td class="text-center">
                {{Helpers::format_money( $shippingDetail->shipping_cost)}}
                <small class="text-danger">{{empty($shippingDetail->shipping_cost) ? 'Missing Prices' : ''}}</small>
            </td>
        </tr>

        <tr>
            <td>{{__('clearance')}}</td>
            <td class="text-center">
                {{Helpers::format_money( $shippingDetail->clearance_cost)}}
                <small class="text-danger">{{empty($shippingDetail->clearance_cost) ? 'Missing Prices' : ''}}</small>
            </td>
        </tr>

        <tr>
            <td>{{__('shipping commission')}}</td>
            <td class="text-center">{{Helpers::format_money( $shippingDetail->shipping_commission)}}</td>
        </tr>

        <tr>
            <td>
                {{__('total in words')}}<br>
                ({{Helpers::get_number_in_words($shippingDetail->totalAED)}})
            </td>
            <td class="text-center"><b>{{Helpers::format_money( $shippingDetail->totalAED)}}</b></td>
        </tr>

    </table>

    <br><br><br>
    <table>
        <tr>
            <td><b>Recieved By Name & Signature</b><br><br></td>
            <td class="{{Helpers::is_arabic() ? 'text-left' : 'text-right'}}"><b>For Nejoum Al Jazeera</b></td>
        </tr>
    </table>

</div>

@stop