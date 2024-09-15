@extends('layouts.print')

@section('page_title')
Shipped Cars
@stop

@section('styles')

<style>
    .print-title {
        text-decoration: underline;
        text-transform: capitalize;
    }

    .print-content.ar table.transactions td:first-child {
        font-size: 20px;
    }
</style>

@stop

@section('content')

<table class="table table-bordered">
    <thead>
        <tr>
            <th>#</th>
            <th>Date</th>
            <th>Description</th>
            <th>Storage Fine</th>
            <th>Car Cost</th>
            <th>Shipping Amount</th>
            <th>Debit</th>
            <th>Credit</th>
            <th>Remaining</th>
            <th>Balance</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($transactions as $row)
        <tr>
            <td>{{$row['index']}}</td>
            <td>{{$row['date']}}</td>
            <td>{{$row['description']}}</td>
            <td>{{$row['storage_fine']}}</td>
            <td>{{$row['car_price']}}</td>
            <td>{{$row['shipping_amount']}}</td>
            <td>{{$row['debit']}}</td>
            <td>{{$row['credit']}}</td>
            <td>{{$row['remaining']}}</td>
            <td>{{$row['balance']}}</td>
        </tr>
        @endforeach
    </tbody>
</table>

@stop