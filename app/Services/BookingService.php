<?php

namespace App\Services;

use App\Models\Booking;

class BookingService
{
    public function getBookingsForDeadlines() {

        return Booking::WhereNotNull('iso_country_code')
                    ->whereNotNull('vessel_imo_number')
                    ->whereNotNull('port_of_load')
                    ->whereNotNull('voyage')
                    ->get();

    }

    public function getBookingsForDemurrage() {

        return Booking::WhereNotNull('lading_bill_number')
                        ->whereNotNull('carrier_customer_code')
                        ->whereNotNull('carrier_code')
                        ->get();

    }

    public function getBookingsForTracking() {

        return Booking::WhereNotNull('booking_number')
                        ->get();

    }



}
