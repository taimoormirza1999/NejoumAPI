<?php

namespace App\Helpers\MaerskApi;

use Carbon\Carbon;

class DeadLineHelper
{
    public static function hasDeadlines($deadlines) {
        if (isset($deadlines[0]['shipmentDeadlines']) && isset($deadlines[0]['shipmentDeadlines']['deadlines']) && sizeof($deadlines[0]['shipmentDeadlines']['deadlines']) > 0) {
            return true;
        }
        return false;
    }

    public static function formatDate($deadlines) {
        $shippingInstructionsDeadline = collect($deadlines[0]['shipmentDeadlines']['deadlines'])->first(function ($item) {
            return $item['deadlineName'] === 'Shipping Instructions Deadline';
        });

        return Carbon::createFromFormat("Y-m-d\TH:i:s", $shippingInstructionsDeadline['deadlineLocal'])->format('Y-m-d H:i:s');
    }
}