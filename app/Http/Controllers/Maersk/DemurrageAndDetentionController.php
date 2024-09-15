<?php

namespace App\Http\Controllers\Maersk;

use App\Helpers\Constants\Messages;
use App\Helpers\MaerskApi\DemurrageHelper;
use App\Http\Controllers\Controller;
use App\Services\BookingService;
use App\Services\MaerskApiService;

class DemurrageAndDetentionController extends Controller
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

            $bookings = $this->bookingService->getBookingsForDemurrage();

            $updatedBookings = false;

            foreach ($bookings as $booking)
            {

                $charges_end_date = DemurrageHelper::formatChargesEndDate($booking->return_pickup_date);

                $demurrage = $this->maerskApiService->getDemurrage($booking->lading_bill_number, $booking->carrier_customer_code, $booking->carrier_code, $charges_end_date);

                if (DemurrageHelper::hasCharges($demurrage)) {

                    $free_period = DemurrageHelper::getFreePeriod($demurrage);

                    $chargeable_period = DemurrageHelper::getChargeablePeriod($demurrage);

                    $updateQuery = DemurrageHelper::prepareUpdateFields($free_period, $chargeable_period);

                    if (DemurrageHelper::hasUpdates($booking, $updateQuery)) {

                        $updatedBookings = true;

                        // $booking->update($updateQuery);

                    }

                }

            }

            $message = $updatedBookings ? Messages::DEMURRAGE_UPDATED : Messages::DEMURRAGE_UP_TO_DATE;

            return response()->json(['message' => $message], 200);

        } catch (\Exception $e) {

            return response()->json(['error' => $e->getMessage()], 500);

        }

    }


}
