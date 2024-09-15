<?php


namespace App\Http\Controllers\System;


use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\System\DashboardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DashboardController extends Controller
{

    protected $internal_dashboard_service;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(DashboardService $internal_dashboard_service)
    {
        $this->internal_dashboard_service = $internal_dashboard_service;
    }

    public function getBookingsUnderDemurrage() {
        return response()->json(
            [
                'count' => $this->internal_dashboard_service->getBookingsUnderDemurrage(),
                'amount' => $this->internal_dashboard_service->getDemurrageCharges(),
                'list' => $this->internal_dashboard_service->getDemurrageList()
            ],
             200);

        return $this->internal_dashboard_service->getBookingsUnderDemurrage();
    }
    

    public function getLoadedBookings() {
        return response()->json(
            [
                'total' => $this->internal_dashboard_service->getTotalLoadedBookings(),
                'yesterday' => $this->internal_dashboard_service->getBookingsLoadedYesterday(),
                'last_week' => $this->internal_dashboard_service->getBookingsLoadedLastWeek(),
                'last_fifteen_days' => $this->internal_dashboard_service->getBookingsLoadedLastFifteenDays(),
                'last_month' => $this->internal_dashboard_service->getBookingsLoadedLastMonth()

            ],
             200);
    }

    public function getDischargedBookings() {
        return $this->internal_dashboard_service->getDishchargedBookings();
    }

    public function getBookingsArrival() {
        return response()->json(
            [
                'today' => $this->internal_dashboard_service->getBookingsArrivingToday(),
                'next_week' => $this->internal_dashboard_service->getBookingsArrivingNextWeek(),
                'yesterday' => $this->internal_dashboard_service->getBookingsArrivedYesterday(),
                'last_week' => $this->internal_dashboard_service->getBookingsArrivedLastWeek()
            ],
             200);
    }

    public function getBookingsArrivingSoon() {
        return $this->internal_dashboard_service->getBookingsArrivingSoon();
    }


}
