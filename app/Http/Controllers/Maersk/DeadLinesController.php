<?php

namespace App\Http\Controllers\Maersk;

use App\Helpers\Constants\Messages;
use App\Helpers\MaerskApi\DeadLineHelper;
use App\Http\Controllers\Controller;
use App\Services\BookingService;
use App\Services\MaerskApiService;

class DeadLinesController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

     protected $maerskApiService;
     protected $bookingService;

    public function __construct(MaerskApiService $apiService, BookingService $bookingService)
    {
        $this->maerskApiService = $apiService;
        $this->bookingService = $bookingService;
    }

    public function index() {
        
        try {

            $bookings = $this->bookingService->getBookingsForDeadlines();

            $updatedBookings = false;

            foreach ($bookings as $booking) 
            {
                $deadlines = $this->maerskApiService->getDeadLines($booking->iso_country_code, $booking->port_of_load, $booking->vessel_imo_number, $booking->voyage);

                if (sizeof($deadlines) > 0 && DeadLineHelper::hasDeadlines($deadlines)) 
                    {

                        $deadlineDate = DeadLineHelper::formatDate($deadlines);

                        if ($booking->shipping_instructions_cut_off_date  !== $deadlineDate) {
                            
                            /* $booking->update([

                                'shipping_instructions_cut_off_date' => $deadlineDate

                            ]); */

                            $updatedBookings = true;

                        }

                    }

            }

            $message = $updatedBookings ? Messages::DEADLINES_UPDATED : Messages::DEADLINES_UP_TO_DATE;

            return response()->json(['message' => $message], 200);

        } catch (\Exception $e) {

            return response()->json(['error' => $e->getMessage()], 500);

        }

    }


}
