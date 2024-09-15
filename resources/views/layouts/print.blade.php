<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>@yield('page_title')</title>
    <link rel="stylesheet" href="{{url('public/assets/css/bootstrap.min.css')}}">

    <style>
        table {
            width: 100%;
        }

        @page {
            margin: 35px 25px;
        }

        * {
            font-size: 16px;
        }

        .rtl {
            direction: rtl;
        }

        .ltr {
            direction: ltr;
        }

        table.table-bordered,
        table.table-bordered tr,
        table.table-bordered th,
        table.table-bordered td {
            border: 1px solid black;
            padding: 8px;
        }

        .print-title {
            font-weight: bold;
            font-size: 24px;
            text-align: center;
            margin-bottom: 10px;
        }

        .print-content {
            text-transform: capitalize;
            font-family: sans-serif;
        }

        .print-content.ar {
            direction: rtl;
            text-align: right;
            font-size: 22px;
        }

        .notes li {
            font-size: 12px;
            text-transform: initial;
        }
        .ar .notes li {
            font-size: 16px;
        }
    </style>

    @yield('styles')

</head>

<body>

    @yield('content')

</body>

</html>