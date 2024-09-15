@extends('layouts.print')

@section('page_title')
General Entries
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

<h3 class="print-title">{{__('general entries')}}</h3>
<br>

<table class="table table-bordered">
    <thead>
        <tr>
            <th>#</th>
            <th>Date</th>
            <th>Reference No</th>
            <th>Description</th>
            <th>Debit</th>
            <th>Credit</th>
            <th>Remaining</th>
            <th>Balance</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($transactions as $row)
        <tr>
            <td>{{$row['index_no']}}</td>
            <td>{{$row['date']}}</td>
            <td>{{$row['reference_no']}}</td>
            <td>{{$row['description']}}</td>
            <td>{{$row['debit']}}</td>
            <td>{{$row['credit']}}</td>
            <td>{{$row['remaining']}}</td>
            <td>{{$row['balance']}}</td>
        </tr>
        @endforeach
    </tbody>
</table>

@stop