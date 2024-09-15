<?php

namespace App\Http\Controllers\Maersk;

use App\Http\Controllers\Controller;
use App\Services\MaerskApiService;

class InvoiceController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    protected $maerskApiService;

    public function __construct(MaerskApiService $apiService)
    {
        $this->maerskApiService = $apiService;
    }

    public function index(string $type)
    {

        try {

            $invoices_response = $this->maerskApiService->getInvoices(env('MAERSK_CARRIER_CODE'), env('MAERSK_CUSTOMER_CODE_CMD'), $type);

            /* TO-DO: Use this when you publish to github
            return response()->json(
                [
                    'invoices' => $invoices_response['invoices'],
                    'total' => $invoices_response['invoiceCount'],
                    'sumOpenAmount' => $invoices_response['sumOpenAmount'],
                    'sumPaidAmount' => $invoices_response['sumPaidAmount']
                ],
                200
            ); */

            // TO-DO: Remove this when you publish to github
            $amount = 0;
            switch($type) {
                case 'OPEN':
                    $amount = 152375;
                    break;
                case 'PAID':
                    $amount = 1435899;
                    break;
                case 'OVERDUE':
                    $amount = 55374;
                    break;
                case 'CREDITS':
                    $amount = 32465;
                    break;
                default:
                    $amount = 0;
                    break;
            }
            return response()->json(
                [
                    'invoices' => [],
                    'total' => 500,
                    'amount' => 0,
                ],
                200
            );

        } catch (\Exception $e) {

            return response()->json(['error' => $e->getMessage()], 500);

        }

    }

}
