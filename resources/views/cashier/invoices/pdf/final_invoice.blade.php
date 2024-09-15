@extends('layouts.print')

@section('page_title')
Cars Statement : {{$invoiceData->final_invoice_number}} - {{date('Y-m-d')}}
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

<br><br><br><br><br>

<div class="print-content {{app('translator')->getLocale()}}">

    <p>{{__('print date')}}: {{date('d-m-Y')}}</p><br>

    <h3 class="print-title">{{__('car statement')}}</h3>

    <table class="invoice-headers">
        <tr>
            <td>
                <div>
                    <?php
                    if (Helpers::is_english()) {
                        $customer_name_col = 'customer_name';
                    } else {
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
                    <div><b>{{__('order')}} #</b>: {{$invoiceData->final_invoice_number}}</div>
                    <div>{{__('status')}}: Paid</div>
                    <div>{{__('date')}}: {{date('j F, Y h:i:sa', strtotime($invoiceData->final_invoice_date))}}</div>
                </div>
            </td>
        </tr>
    </table>

    <br>
    <table class="table-bordered">
        <tr class="text-center">
            <th>#</th>
            <th>{{__('photo')}}</th>
            <th>{{__('vehicle')}}</th>
            <th>{{__('amount due')}}</th>
            <th>{{__('previous amount')}}</th>
            <th>{{__('paid amount')}}</th>
            <th>{{__('remaining amount')}}</th>
        </tr>

        <?php
        $subTotal = $amount_paid = $balance = 0;
        $currency_rate = $invoiceData->currency_rate;
        ?>

        @foreach($invoiceDetail as $key=>$row)
        <?php
        $amount_due = $row->amount_due + $row->storage_fine;
        $subTotal += $amount_due;
        $amount_paid += $row->amount_paid;
        $balance += $row->remaining_amount;
        ?>
        <tr>
            <td width="25px">{{$key+1}}</td>
            <td width="90px">
                <img src='{{Constants::NEJOUM_IMAGE . "uploads/{$row->photo}"}}' width="80" height="80" /><br>
            </td>
            <td width="220px">
                <strong>{{__('maker')}}</strong> <?= $row->carMakerName ?> <?= $row->carModelName ?> <?= $row->year ?><br><?= $row->vin ?> <br>
                <strong>{{__('lot')}}</strong> <?= $row->lotnumber ?><br>
            </td>
            <td>
                <div>{{Helpers::format_money($amount_due, 'AED')}}</div>
            </td>
            <td>
                <div>{{Helpers::format_money($row->previous_amount_paid, 'AED')}}</div>
            </td>
            <td>
                <div>{{Helpers::format_money($row->amount_paid, 'AED')}}</div>
            </td>
            <td>
                <div>{{Helpers::format_money($row->remaining_amount, 'AED')}}</div>
            </td>
        </tr>
        @endforeach

        <tr>
            <td rowspan="3" colspan="4">{{__('total amount in words')}}: <br>{{Helpers::get_number_in_words($amount_paid + $balance)}}</td>
            <td>{{__('total due')}}: </td>
            <td colspan="3">{{Helpers::format_money($amount_paid + $balance, 'AED')}}</td>
        </tr>
        <tr>
            <td>{{__('total paid')}}: </td>
            <td colspan="2">{{Helpers::format_money($amount_paid, 'AED')}}</td>
        </tr>
        <tr>
            <td>{{__('balance')}}: </td>
            <td colspan="2">{{Helpers::format_money($balance, 'AED')}}</td>
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