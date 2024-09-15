<?php

namespace App\Http\Controllers\Maersk;

use App\Helpers\Constants\Messages;
use App\Helpers\MaerskApi\TrackAndTraceHelper;
use App\Http\Controllers\Controller;
use App\Services\BookingService;
use App\Services\MaerskApiService;

class TrackAndtraceController extends Controller
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

            $bookings = $this->bookingService->getBookingsForTracking();

            $updated_bookings = false;

            foreach ($bookings as $booking) {

                $events = $this->maerskApiService->getEvents($booking->booking_number)['events'];

                if (TrackAndTraceHelper::hasEvents($events)) {

                    foreach($events as $event) {

                        if (TrackAndTraceHelper::isEquipmentEvent($event) && TrackAndTraceHelper::hasUpdates($booking, $event)) {

                           //  $booking->update(TrackAndTraceHelper::getUpdates($booking, $event));

                            $updated_bookings = true;

                        }

                    }

                }

            }

            $message = $updated_bookings ? Messages::TRACKING_UPDATED : Messages::TRACKING_UP_TO_DATE;


            return response()->json(['message' => $message], 200);

        } catch (\Exception $e) {

            return response()->json(['error' => $e->getMessage()], 500);

        }

    }

    public function getEventsForBooking(string $booking_number) {

        try {

            $response = [];

            // $events = $this->maerskApiService->getEvents($booking_number)['events'];

            $events = '{
                "events": [
                  {
                    "eventID": "6832920321",
                    "eventType": "SHIPMENT",
                    "eventDateTime": "2019-11-12T07:41:00+08:00",
                    "eventCreatedDateTime": "2021-01-09T14:12:56Z",
                    "eventClassifierCode": "ACT",
                    "shipmentEventTypeCode": "DRFT",
                    "documentTypeCode": "SHI",
                    "documentID": "205284917"
                  },
                  {
                    "eventID": "6832920321",
                    "eventType": "TRANSPORT",
                    "eventDateTime": "2019-11-12T07:41:00+08:00",
                    "eventCreatedDateTime": "2021-01-09T14:12:56Z",
                    "eventClassifierCode": "ACT",
                    "transportEventTypeCode": "ARRI",
                    "documentReferences": [
                      {
                        "documentReferenceType": "BKG",
                        "documentReferenceValue": "ABC123123123"
                      },
                      {
                        "documentReferenceType": "TRD",
                        "documentReferenceValue": "85943567-eedb-98d3-f4ed-aed697474ed4"
                      }
                    ],
                    "transportCall": {
                      "transportCallID": "123e4567-e89b-12d3-a456-426614174000",
                      "carrierServiceCode": "FE1",
                      "exportVoyageNumber": "2103S",
                      "importVoyageNumber": "2103N",
                      "transportCallSequenceNumber": 2,
                      "UNLocationCode": "USNYC",
                      "facilityCode": "ADT",
                      "facilityCodeListProvider": "SMDG",
                      "facilityTypeCode": "POTE",
                      "otherFacility": "Balboa Port Terminal, Avenida Balboa Panama",
                      "modeOfTransport": "VESSEL",
                      "location": {
                        "locationName": "Eiffel Tower",
                        "latitude": "48.8585500",
                        "longitude": "2.294492036",
                        "UNLocationCode": "USNYC",
                        "facilityCode": "ADT",
                        "facilityCodeListProvider": "SMDG"
                      },
                      "vessel": {
                        "vesselIMONumber": 1801323,
                        "vesselName": "King of the Seas",
                        "vesselFlag": "DE",
                        "vesselCallSignNumber": "NCVV",
                        "vesselOperatorCarrierCode": "MAEU",
                        "vesselOperatorCarrierCodeListProvider": "NMFTA"
                      }
                    }
                  },
                  {
                    "eventID": "6832920321",
                    "eventType": "EQUIPMENT",
                    "eventDateTime": "2019-11-12T07:41:00+08:00",
                    "eventCreatedDateTime": "2021-01-09T14:12:56Z",
                    "eventClassifierCode": "ACT",
                    "equipmentEventTypeCode": "LOAD",
                    "equipmentReference": "APZU4812090",
                    "ISOEquipmentCode": "42G1",
                    "emptyIndicatorCode": "EMPTY",
                    "documentReferences": [
                      {
                        "documentReferenceType": "BKG",
                        "documentReferenceValue": "ABC123123123"
                      },
                      {
                        "documentReferenceType": "TRD",
                        "documentReferenceValue": "85943567-eedb-98d3-f4ed-aed697474ed4"
                      }
                    ],
                    "eventLocation": {
                      "locationName": "Eiffel Tower",
                      "latitude": "48.8585500",
                      "longitude": "2.294492036",
                      "UNLocationCode": "USNYC",
                      "facilityCode": "ADT",
                      "facilityCodeListProvider": "SMDG"
                    },
                    "transportCall": {
                      "transportCallID": "123e4567-e89b-12d3-a456-426614174000",
                      "carrierServiceCode": "FE1",
                      "exportVoyageNumber": "2103S",
                      "importVoyageNumber": "2103N",
                      "transportCallSequenceNumber": 2,
                      "UNLocationCode": "USNYC",
                      "facilityCode": "ADT",
                      "facilityCodeListProvider": "SMDG",
                      "facilityTypeCode": "POTE",
                      "otherFacility": "Balboa Port Terminal, Avenida Balboa Panama",
                      "modeOfTransport": "VESSEL",
                      "location": {
                        "locationName": "Eiffel Tower",
                        "latitude": "48.8585500",
                        "longitude": "2.294492036",
                        "UNLocationCode": "USNYC",
                        "facilityCode": "ADT",
                        "facilityCodeListProvider": "SMDG"
                      },
                      "vessel": {
                        "vesselIMONumber": 1801323,
                        "vesselName": "King of the Seas",
                        "vesselFlag": "DE",
                        "vesselCallSignNumber": "NCVV",
                        "vesselOperatorCarrierCode": "MAEU",
                        "vesselOperatorCarrierCodeListProvider": "NMFTA"
                      }
                    }
                  }
                ]
              }';
              $events = json_decode($events, true)['events'];

                if (TrackAndTraceHelper::hasEvents($events)) {

                    foreach($events as $key => $event) {

                        if (TrackAndTraceHelper::isTransportEvent($event)) {
                            array_push($response, [
                                'event_name' => TrackAndTraceHelper::getTransportEventName($event),
                                'location' => TrackAndTraceHelper::getTransportEventLocation($event),
                                'event_date' => TrackAndTraceHelper::getEventDate($event),
                                'port' => TrackAndTraceHelper::getEventFacility($event),
                                'status' => TrackAndTraceHelper::getEventStatus($event, $key, sizeof($events))
                            ]);
                        }

                        if (TrackAndTraceHelper::isEquipmentEvent($event)) {
                            array_push($response, [
                                'event_name' => TrackAndTraceHelper::getEquipmentEventName($event),
                                'location' => TrackAndTraceHelper::getEquipmentEventLocation($event),
                                'event_date' => TrackAndTraceHelper::getEventDate($event),
                                'port' => TrackAndTraceHelper::getEventFacility($event),
                                'status' => TrackAndTraceHelper::getEventStatus($event, $key, sizeof($events))
                            ]);
                        }

                    }

                }

            return response()->json(['events' => $response], 200);

        } catch (\Exception $e) {

            return response()->json(['error' => $e->getMessage()], 500);

        }

    }
}
