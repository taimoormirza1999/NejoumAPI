<?php

namespace App\Services\System;

use App\Models\Booking;
use Carbon\Carbon;

class DashboardService
{
    public function getBookingsUnderDemurrage()
    {

        return Booking::WhereNotNull('charges_amount')
            ->count();
    }


}
