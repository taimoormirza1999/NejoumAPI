<?php

namespace App\Services\System;

use App\Models\Booking;
use Carbon\Carbon;

class DashboardService
{
    public function getBookingsUnderDemurrage()
    {

        // TO-DO : remove this once we have access to maersk API
        return 0;
 
    }

    public function getDemurrageCharges()
    {

        // TO-DO : remove this once we have access to maersk API
        return 0;
       
    }

    public function getDemurrageList()
    {
        // free_start_date instead of eta
        $today = Carbon::now()->toDateString();
        
        return Booking::whereNotNull('eta')
            ->whereDate('eta' , '>' , Carbon::today()->subDays(15))
            ->select('*')
            ->selectRaw("DATEDIFF('$today', eta) AS past_days")
            ->limit(10)
            ->get();

    
    }

    public function getTotalLoadedBookings()
    {

        return 0;
    
    }

    public function getBookingsLoadedYesterday()
    {

        return 0;
    }

    public function getBookingsLoadedLastWeek()
    {

        return 0;
    }

    public function getBookingsLoadedLastFifteenDays()
    {

        return 0;
    }

    public function getBookingsLoadedLastMonth()
    {

        return 0;
    }

    public function getDishchargedBookings()
    {

        return 0;
    }

    public function getBookingsArrivingToday()
    {

        return Booking::WhereNotNull('eta')->whereDate('eta', Carbon::today())
            ->count();
    }

    public function getBookingsArrivingNextWeek()
    {

        return Booking::WhereNotNull('eta')->whereDate('eta', '>', Carbon::today())->whereDate('eta' , '<', Carbon::now()->addDays(6))
            ->count();
    }

    public function getBookingsArrivedYesterday()
    {

        return Booking::WhereNotNull('eta')->whereDate('eta', Carbon::yesterday())
            ->count();
    }

    public function getBookingsArrivedLastWeek()
    {

        return Booking::WhereNotNull('eta')->whereDate('eta', '<', Carbon::today())
            ->whereDate('eta', '>=', Carbon::now()->subDays(6))
            ->count();
    }

    public function getBookingsArrivingSoon()
    {
        $today = Carbon::now()->toDateString();

        return Booking::WhereNotNull('eta')
            ->select('*')
            ->selectRaw("DATEDIFF(eta, '$today') AS arriving_days")
            ->orderBy('eta', 'DESC')
            ->limit(10)
            ->get();
    }
}
