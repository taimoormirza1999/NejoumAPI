<?php

namespace App\Http\Controllers\External;

use App\Http\Controllers\Controller;
use App\Services\CustomerService;
use App\Services\GeneralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nesk\Puphpeteer\Puppeteer;
use Netflie\WhatsAppCloudApi\Response\ResponseException;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;
use Netflie\WhatsAppCloudApi\Message\Template\Component;

class WhatsAppController extends Controller
{
    private $whatsapp_cloud_api;

    public function __construct()
    {
        $this->whatsapp_cloud_api = new WhatsAppCloudApi([
            'from_phone_number_id' => env('WHATSAPP_SENDER_ID'),
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        ]);
    }


    public function sendBillToCustomer(Request $request): JsonResponse
    {
        return $this->trySendingBill(
            $request->input('recipient'),
            $request->input('bill_id'),
            $request->input('type', 'auction')
        );
    }



    public function notifyTowingCaseCS(Request $request): JsonResponse
    {
        $case_id = $request->input('case_id');
        if (empty($case_id)) {
            return $this->createErrorResponse('Case ID is required');
        }

        $case = GeneralService::getTowingCase($case_id);
        $cs_users = GeneralService::getCustomerServiceUsers($case_id);

        foreach ($cs_users as $user) {
            $this->sendTowingCase($user, $case);
        }

        return $this->createSuccessResponse('Towing case notifications sent successfully');
    }

    function sendFeedbackLink(Request $request): JsonResponse
    {
        $link = $request->input('link');
        $phone = $request->input('phone');
        $lang = $request->input('lang');
        $lang = empty($lang) ? 'ar' : $lang;
        if (empty($link) || empty($phone)) {
            return $this->createErrorResponse('Link & phone number are required');
        }
        try{
            $this->sendFeedbackLinkCustomer($phone, $link, $lang);
            return $this->createSuccessResponse('Feedback link is shared successfully');
        }
        catch(\Exception $e){
            return $this->createErrorResponse($e->getMessage());
        }
    }

    function sendMonthlyFeedbackLink(Request $request): JsonResponse
    {
        $link = $request->input('link');
        $lang = 'ar';
        try{
            $customers = CustomerService::getMonthlyCustomersFeedbackToken();
            foreach($customers as $row){
                $link = str_replace('REPLACE_CUSTOMER_TOKEN', $row->token, $link);
                $this->sendFeedbackLinkCustomer($row->phone, $link, $lang);
            }
            return $this->createSuccessResponse('Feedback link is shared successfully');
        }
        catch(\Exception $e){
            return $this->createErrorResponse($e->getMessage());
        }
    }

    private function sendFeedbackLinkCustomer($phone, $link, $lang){
        $recipient = $this->formatNumberForWhatsapp($phone);

        $component_header = [];
        $component_body = [
            ['type' => 'text', 'text' => $link], // {{1}}
        ];
        $components = new Component($component_header, $component_body);
        $this->whatsapp_cloud_api->sendTemplate(
            $recipient,
            'feedback',
            $lang,
            $components
        );
    }


    public function whatsapp_test(Request $request): JsonResponse
    {

        if(!env('ENABLE_TESTING')){
            return $this->createErrorResponse('This endpoint is not enabled');
        }
        $sampleUser = (object)[
            'full_name' => 'Ibrahem',
            'phone' => env('WHATSAPP_TEST_NUMBER'),
        ];
        $sampleCase = (object)[
            'response' => 2, // Sample response status
            'id' => '123',
            'lotnumber' => '456',
            'vin' => '789',
            'response_time' => '24 hours',
            'response_time_unit' => 'hours',
            'create_date' => '2023-11-28',
            'full_name' => 'Sample Customer',
            'full_name_ar' => 'عميل نموذجي',
            'phone' => '60123456789',
        ];

        try {
            $this->sendBill($sampleUser->phone, '123', 'auction');
            $this->sendTowingCase($sampleUser, $sampleCase);
            return $this->createSuccessResponse('Test messages sent successfully');
        } catch (ResponseException $e) {
            return $this->createErrorResponse($e->getMessage());
        }
    }
    private function trySendingBill($recipient, $bill_id, $type): JsonResponse
    {
        if (empty($recipient) || empty($bill_id)) {
            return $this->createErrorResponse('Recipient and Bill ID are required');
        }
        try {
            $this->sendBill($recipient, $bill_id, $type);
            return $this->createSuccessResponse('Bill sent successfully');
        } catch (ResponseException $e) {
            return $this->createErrorResponse($e->getMessage());
        }
    }
    private function sendBill($recipient, $bill_id, $type): void
    {
        $recipient = $this->formatNumberForWhatsapp($recipient);
        $url = "https://tinyurl.com/4b79289c/$type/" . $this->decode($bill_id);
        $component_header = [];
        $component_body = [
            [
                'type' => 'text',
                'text' => $url
            ],
        ];
        $components = new Component($component_header, $component_body);
        $this->whatsapp_cloud_api->sendTemplate($recipient, 'send_receipt_to_customer', 'en', $components);
    }


    private function sendTowingCase($user, $case)
    {
        $recipient = $this->formatNumberForWhatsapp($user->phone);
        if ($case->response == 0 || $case->response == 1) {
            return;
        }
        $response = $case->response == 2 ? 'Pending' : 'Rejected';


        $component_header = [];
        $component_body = [
            [
                'type' => 'text',
                'text' => $user->full_name, // {{1}}
            ],
            [
                'type' => 'text',
                'text' => $case->id . ' | ' . $case->lotnumber . "|" . $case->vin, // {{2}}
            ],
            [
                'type' => 'text',
                'text' => $response, // {{3}}
            ],
            [
                'type' => 'text',
                'text' => $case->response_time . ' ' . $case->response_time_unit, // {{4}}
            ],
            [
                'type' => 'text',
                'text' => $case->create_date, // {{5}}
            ],
            [
                'type' => 'text',
                'text' => $case->full_name . " " . $case->full_name_ar . "|" . $case->phone, // {{6}}
            ],
            [
                'type' => 'text',
                'text' => "https://nejoumaljazeera.co/towing_cases/in/cs", // {{7}}
            ],
        ];
        $components = new Component($component_header, $component_body);
        $this->whatsapp_cloud_api->sendTemplate(
            $recipient,
            'notify_customer_service', // Replace with your actual template name
            'en',
            $components
        );
    }

    private function decode($number): string
    {
        /*
         * encode on client side is base_convert(substr($shortString, 0, -4), 36, 10);
        */
        $decoded = base_convert($number, 10, 36);
        $random = substr(md5(uniqid(mt_rand(), true)), 0, 4);
        $shortString = $decoded . $random;
        return $shortString;

    }

    private function formatNumberForWhatsapp($number): string
    {
        $number = ltrim($number, '+00');
        return '+' . preg_replace('/[\s-]/', '', $number);
    }

    private function createErrorResponse($message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message]);
    }

    private function createSuccessResponse($message): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message]);
    }
}
