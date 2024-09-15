<?php

namespace App\Services;

use App\Enums\InvoiceType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MaerskApiService
{
    public function getDeadLines(string $IsoCountryCode, string $portOfLoad, string $vesselIMONumber, string $voyage)
    {

        $queryParams = [
            'ISOCountryCode' => $IsoCountryCode,
            'portOfLoad' => $portOfLoad,
            'vesselIMONumber' => $vesselIMONumber,
            'voyage' => $voyage
        ];

        return Http::withHeaders($this->headers())
            ->get(env('MAERSK_API_URL') . 'shipment-deadlines', $queryParams)
            ->json();
    }

    public function getEvents(string $bookingReference, int $limit = 100, int $cursor = 1, string $sort = 'eventCreatedDateTime:DESC')
    {

        if ($this->oAuthHeaders() === 'failed') {
            return response()->json(['message' => 'UnAuthorized'], 401);
        }

        $queryParams = [
            'carrierBookingReference' => $bookingReference,
            'limit' => $limit,
            'cursor' => $cursor,
            'sort' => $sort
        ];

        return Http::withHeaders($this->oAuthHeaders())
            ->get(env('MAERSK_API_URL') . 'track-and-trace/public-events', $queryParams)
            ->json();
    }

    public function getDemurrage(string $billOfLadingNumber, string $carrierCustomerCode, string $carrierCode, string $chargedEndDate)
    {

        if ($this->oAuthHeaders() === 'failed') {
            return response()->json(['message' => 'UnAuthorized'], 401);
        }

        $queryParams = [
            'billOfLadingNumber' => $billOfLadingNumber,
            'carrierCustomerCode' => $carrierCustomerCode,
            'carrierCode' => $carrierCode
        ];
        if ($chargedEndDate) {
            $queryParams['chargesEndDate'] = $chargedEndDate;
        }

        return Http::withHeaders($this->oAuthHeaders())
            ->get(env('MAERSK_API_URL') . 'shipping-charges/import/DMR', $queryParams)
            ->json();
    }

    public function getDetention(string $billOfLadingNumber, string $carrierCustomerCode, string $carrierCode, string $chargedEndDate)
    {

        if ($this->oAuthHeaders() === 'failed') {
            return response()->json(['message' => 'UnAuthorized'], 401);
        }

        $queryParams = [
            'billOfLadingNumber' => $billOfLadingNumber,
            'carrierCustomerCode' => $carrierCustomerCode,
            'carrierCode' => $carrierCode
        ];
        if ($chargedEndDate) {
            $queryParams['chargesEndDate'] = $chargedEndDate;
        }

        return Http::withHeaders($this->oAuthHeaders())
            ->get(env('MAERSK_API_URL') . 'shipping-charges/import/DET', $queryParams)
            ->json();
    }

    public function getInvoices(string $carrierCode, string $customerCodeCMD, string $invoiceType)
    {

        if ($this->oAuthHeaders() === 'failed') {
            return response()->json(['message' => 'UnAuthorized'], 401);
        }

        $queryParams = [
            'carrierCode' => $carrierCode,
            'customerCodeCMD' => $customerCodeCMD,
            'invoiceType' => $invoiceType,
            'isCreditCountry ' => 'true',
            'isSelected' => 'false'
        ];

        return Http::withHeaders($this->oAuthHeaders())
            ->get(env('MAERSK_API_URL') . 'invoices', $queryParams)
            ->json();
    }

    private function headers()
    {

        return [
            'accept' => 'application/json',
            'API-Version' => 1,
            'Consumer-Key' => env('MAERSK_API_CONSUMER_KEY')
        ];
    }

    private function oAuthHeaders()
    {

        return [
            'accept' => 'application/json',
            'API-Version' => 1,
            'Consumer-Key' => env('MAERSK_API_CONSUMER_KEY'),
            'Authorization' => 'Bearer ' . $this->getOAuthToken()
        ];
    }


    private function getOAuthToken()
    {


        $response = Http::asForm()->post('https://api.maersk.com/oauth2/access_token', [
            'grant_type' => 'client_credentials',
            'client_id' => env('MAERSK_API_CONSUMER_KEY'),
            'client_secret' => env('MAERSK_API_SECRET'),
        ]);

        Log::info('idtoken : '. $response->json('id_token'));

        if ($response->ok()) {
            return $response->json('id_token');
        } else {
            return 'failed';
        }
    }
}
