<?php

namespace App\Helpers\MaerskApi;

use App\Libraries\Constants;
use Carbon\Carbon;

class TrackAndTraceHelper
{
    public static function hasEvents($events) {
        if (sizeof($events) > 0) {
            return true;
        }
        return false;
    }

    public static function isEquipmentEvent($event) {
        return $event['eventType'] == Constants::MAERSK_EQUIPMENT_EVENT;
    }

    public static function isShippmentEvent($event) {
        return $event['eventType'] == Constants::MAERSK_SHIPMENT_EVENT;
    }

    public static function isTransportEvent($event) {
        return $event['eventType'] == Constants::MAERSK_TRANSPORT_EVENT;
    }

    public static function getTransportEventName($event) {
        $eventName = 'Arrived';
        switch($event['transportEventTypeCode']) {
            case Constants::MAERSK_ARRIVED_EVENT_CODE:
                $eventName = 'Arrived';
                break;
            case Constants::MAERSK_DEPARTED_EVENT_CODE:
                $eventName = 'Departed';
                break;
            default:
                $eventName = '';
                break;

        }

        return $eventName;
    }

    public static function getTransportEventLocation($event) {
        return $event['transportCall']['location']['locationName'];
    }

    public static function getEquipmentEventName($event) {
        $eventName = '';
        switch($event['equipmentEventTypeCode']) {
            case Constants::MAERSK_LOADED_EVENT_CODE:
                $eventName = 'Loaded';
                break;
            case Constants::MAERSK_DISCHARGED_EVENT_CODE:
                $eventName = 'Discharged';
                break;
            case Constants::MAERSK_GATEDIN_EVENT_CODE:
                $eventName = 'Gated In';
                break;
            case Constants::MAERSK_GATEDOUT_EVENT_CODE:
                $eventName = 'Gated out';
                break;
            case Constants::MAERSK_STUFFED_EVENT_CODE:
                $eventName = 'Stuffed';
                break;
            case Constants::MAERSK_STRIPPED_EVENT_CODE:
                $eventName = 'Stripped';
                break;
            case Constants::MAERSK_PICKEDUP_EVENT_CODE:
                $eventName = 'Picked UP';
                break;
            case Constants::MAERSK_DROPOFF_EVENT_CODE:
                $eventName = 'Dropped Off';
                break;
            case Constants::MAERSK_RESEALED_EVENT_CODE:
                $eventName = 'Resealed';
                break;
            case Constants::MAERSK_REMOVED_EVENT_CODE:
                $eventName = 'Removed';
                break;
            case Constants::MAERSK_INSPECTED_EVENT_CODE:
                $eventName = 'Inspected';
                break;
            default:
                break;

        }

        return $eventName;
    }

    public static function getEventFacility($event) {
        return $event['transportCall']['otherFacility'];
    }

    public static function getEquipmentEventLocation($event) {
        return $event['eventLocation']['locationName'];
    }

    public static function getEventDate($event) {
        return $event['eventDateTime'];
    }

    public static function getEventStatus($event, $key, $total_events) {
        $eventDate = Carbon::parse($event['eventDateTime']);
        $status = '';
        if ($eventDate->isPast(Carbon::now()) && $key < $total_events - 1) {
            $status = 'done';
        } elseif ($eventDate->isSameDay(Carbon::today()) || $key === $total_events - 1) {
            $status = 'current';
        } else {
            $status = 'waiting';
        }
        return $status;
    }

    public static function getUpdates($booking, $event) {
        $updated_fields = [];
        switch($event['equipmentEventTypeCode']) {
            case Constants::MAERSK_LOADED_EVENT_CODE:
                if ($booking->loaded_at !== $event['eventCreatedDateTime']) {
                    $updated_fields['loaded_at'] = $event['eventCreatedDateTime'];
                }
                break;
            case Constants::MAERSK_DISCHARGED_EVENT_CODE:
                if ($booking->dischared_at !== $event['eventCreatedDateTime']) {
                    $updated_fields['dischared_at'] = $event['eventCreatedDateTime'];
                }
                break;
            case Constants::MAERSK_GATEDIN_EVENT_CODE:
                if ($booking->gated_in_at !== $event['eventCreatedDateTime']) {
                    $updated_fields['gated_in_at'] = $event['eventCreatedDateTime'];
                }
                break;
            case Constants::MAERSK_GATEDOUT_EVENT_CODE:
                if ($booking->gated_out_at !== $event['eventCreatedDateTime']) {
                    $updated_fields['gated_out_at'] = $event['eventCreatedDateTime'];
                }
                break;
            case Constants::MAERSK_STUFFED_EVENT_CODE:
                if ($booking->stuffed_at !== $event['eventCreatedDateTime']) {
                    $updated_fields['stuffed_at'] = $event['eventCreatedDateTime'];
                }
                break;
            case Constants::MAERSK_STRIPPED_EVENT_CODE:
                if ($booking->stripped_at !== $event['eventCreatedDateTime']){
                    $updated_fields['stripped_at'] = $event['eventCreatedDateTime'];
                }
                break;
            case Constants::MAERSK_PICKEDUP_EVENT_CODE:
                if ($booking->picked_up_at !== $event['eventCreatedDateTime']){
                    $updated_fields['picked_up_at'] = $event['eventCreatedDateTime'];
                }
                break;
            case Constants::MAERSK_DROPOFF_EVENT_CODE:
                if ($booking->dropped_off_at !== $event['eventCreatedDateTime']) {
                    $updated_fields['dropped_off_at'] = $event['eventCreatedDateTime'];
                }
                break;
            case Constants::MAERSK_RESEALED_EVENT_CODE:
                if ($booking->resealed_at !== $event['eventCreatedDateTime']) {
                    $updated_fields['resealed_at'] = $event['eventCreatedDateTime'];
                }
                break;
            case Constants::MAERSK_REMOVED_EVENT_CODE:
                if ($booking->removed_at !== $event['eventCreatedDateTime']) {
                    $updated_fields['removed_at'] = $event['eventCreatedDateTime'];
                }
                break;
            case Constants::MAERSK_INSPECTED_EVENT_CODE:
                if ($booking->inspected_at !== $event['eventCreatedDateTime']) {
                    $updated_fields['inspected_at'] = $event['eventCreatedDateTime'];
                }
                break;
            default:
                break;

        }

        return $updated_fields;
    }
    

    public static function hasUpdates($booking, $event) {
        $updated_bookings = false;
        switch($event['equipmentEventTypeCode']) {
            case Constants::MAERSK_LOADED_EVENT_CODE:
                if ($booking->loaded_at !== $event['eventCreatedDateTime']) {
                    $updated_bookings = true;
                }
                break;
            case Constants::MAERSK_DISCHARGED_EVENT_CODE:
                if ($booking->dischared_at !== $event['eventCreatedDateTime']) {
                    $updated_bookings = true;
                }
                break;
            case Constants::MAERSK_GATEDIN_EVENT_CODE:
                if ($booking->gated_in_at !== $event['eventCreatedDateTime']) {
                    $updated_bookings = true;
                }
                break;
            case Constants::MAERSK_GATEDOUT_EVENT_CODE:
                if ($booking->gated_out_at !== $event['eventCreatedDateTime']) {
                    $updated_bookings = true;
                }
                break;
            case Constants::MAERSK_STUFFED_EVENT_CODE:
                if ($booking->stuffed_at !== $event['eventCreatedDateTime']) {
                    $updated_bookings = true;
                }
                break;
            case Constants::MAERSK_STRIPPED_EVENT_CODE:
                if ($booking->stripped_at !== $event['eventCreatedDateTime']){
                    $updated_bookings = true;
                }
                break;
            case Constants::MAERSK_PICKEDUP_EVENT_CODE:
                if ($booking->picked_up_at !== $event['eventCreatedDateTime']){
                    $updated_bookings = true;
                }
                break;
            case Constants::MAERSK_DROPOFF_EVENT_CODE:
                if ($booking->dropped_off_at !== $event['eventCreatedDateTime']) {
                    $updated_bookings = true;
                }
                break;
            case Constants::MAERSK_RESEALED_EVENT_CODE:
                if ($booking->resealed_at !== $event['eventCreatedDateTime']) {
                    $updated_bookings = true;
                }
                break;
            case Constants::MAERSK_REMOVED_EVENT_CODE:
                if ($booking->removed_at !== $event['eventCreatedDateTime']) {
                    $updated_fields['removed_at'] = $event['eventCreatedDateTime'];
                    $updated_bookings = true;
                }
                break;
            case Constants::MAERSK_INSPECTED_EVENT_CODE:
                if ($booking->inspected_at !== $event['eventCreatedDateTime']) {
                    $updated_bookings = true;
                }
                break;
            default:
                break;

        }

        return $updated_bookings;
    }
}
