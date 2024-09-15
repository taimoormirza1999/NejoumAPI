<?php

namespace App\Helpers\MaerskApi;

use Carbon\Carbon;

class DemurrageHelper
{
    public static function hasCharges($demurrage) {
        if (isset($demurrage['equipmentCharges']) && sizeof($demurrage['equipmentCharges']) > 0) {
            return true;
        }
        return false;
    }

    public static function getFreePeriod($demurrage) {
        return $demurrage['equipmentCharges'][0]['freePeriod'];
    }

    public static function getChargeablePeriod($demurrage) {
        return $demurrage['equipmentCharges'][0]['chargeablePeriod'];
    }

    public static function prepareUpdateFields($free_period, $chargeable_period) {
        return [
            'free_days' => $free_period['freeDays'],
            'free_start_date' => $free_period['actualFreeStartDate'],
            'free_end_date' => $free_period['actualLastFreeDate'],
            'charges_end_date' => $chargeable_period['endDate'],
            'charges_amount' => $chargeable_period['chargesByDates'][0]['amount'],
            'chargeable_days' => $chargeable_period['chargesByDates'][0]['chargeableDays'],
            'charges_date_rate' => $chargeable_period['chargesByDates'][0]['rateTiers'][0]['rate']
        ];
    }

    public static function hasUpdates($booking, $updated_fields) {
        if ($updated_fields['free_days'] !== $booking->free_days || $updated_fields['free_start_date'] !== $booking->free_start_date ||
            $updated_fields['free_end_date'] !== $booking->free_end_date || $updated_fields['charges_amount'] !== $booking->charges_amount ||
            $updated_fields['chargeable_days'] !== $booking->chargeable_days || $updated_fields['charges_date_rate'] !== $booking->charges_date_rate) {
            return true;
        }
        return false;
    }

    public static function formatChargesEndDate($date) {

        return $date ? Carbon::createFromFormat('Y-m-d H:i:s' , $date)->format('Y-m-d') : Carbon::now()->format('Y-m-').'30';

    }
}
