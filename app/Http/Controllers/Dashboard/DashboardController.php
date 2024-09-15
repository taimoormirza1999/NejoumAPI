<?php


namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\DatabaseManager;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;
use \Laravel\Passport\Token;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('client')->only(['dashboardCounts', 'siteAdvertisment']);
    }

    public function getnotOpenednotif(Request $request)
    {
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $result = DashboardService::getnotOpenednotif($args);
        return $result;
    }

    public function dashboardCounts(Request $request)
    {
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $result = DashboardService::dashboardCounts($args);

        return response()->json([
            'data' => $result
        ], Response::HTTP_OK);
    }

    public function siteAdvertisment(Request $request)
    {
        $args = $request->all();
        $result = DashboardService::siteAdvertisment($args);
        return $result;
    }

    // NEW

    public function allCarsDetailsCount(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $result = DashboardService::allCarsDetailsCount($args);

        return $result;
    }

    public function cancelledCount(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $result = DashboardService::cancelledCount($args);

        return $result;
    }

    public function getnotOpenedreminders(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $result = DashboardService::getnotOpenedreminders($args);

        return response()->json([
            'count' => $result
        ], Response::HTTP_OK);
    }

    public function checkDisplyingBLFiles(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $result = DashboardService::checkDisplyingBLFiles($args);

        return $result;
    }

    public function checkDisplyingPricesFiles(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $result = DashboardService::checkDisplyingPricesFiles($args);

        return $result;
    }

    public function newCarscount(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $result = DashboardService::newCarscount($args);

        return $result;
    }

    public function towingCarscount(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $result = DashboardService::towingCarscount($args);

        return $result;
    }

    public function loadingCarscount(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $result = DashboardService::loadingCarscount($args);

        return $result;
    }

    public function warehouseCarscount(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $result = DashboardService::warehouseCarscount($args);

        return $result;
    }

    public function shippingCarscount(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $result = DashboardService::shippingCarscount($args);

        return $result;
    }

    public function portCarscount(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $result = DashboardService::portCarscount($args);

        return $result;
    }

    public function storeCarscount(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $result = DashboardService::storeCarscount($args);

        return $result;
    }

    public function GetSumBalance(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $result = DashboardService::GetSumBalance($args);

        return $result;
    }

    public function calcualteStorageFinesInUAE(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $result = DashboardService::calcualteStorageFinesInUAE($args);

        return $result;
    }

    public function getGeneralNotification(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $result = DashboardService::getGeneralNotification($args);

        return $result;
    }

    public function checkPopupAnnouncement(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $result = DashboardService::checkPopupAnnouncement($args);

        return $result;
    }

    public function ActivateAdminAccess(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $result = DashboardService::ActivateAdminAccess($args);

        return response()->json([
            'count' => $result
        ], Response::HTTP_OK);
    }

    public function getProfileData(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $result = DashboardService::getProfileData($args);

        return response()->json([
            'data' => $result
        ], Response::HTTP_OK);
    }

    // All dashboard count in one API.
    public function dashboardCount(Request $request){
        $allCount = $this->allCarsDetailsCount($request);
        $cancelledCount = $this->cancelledCount($request);
        $siteAdvertisment = $this->siteAdvertisment($request);
        $notif = $this->getnotOpenednotif($request);
        $count_cars = $this->newCarscount($request);
        $towingCars = $this->towingCarscount($request);
        $warehouseCars = $this->warehouseCarscount($request);
        $loadingCars = $this->loadingCarscount($request);
        $shippingCars = $this->shippingCarscount($request);
        $uaePortCars = $this->portCarscount($request);
        $storeCars = $this->storeCarscount($request);
        $checkDisplyingBLFiles = $this->checkDisplyingBLFiles($request);
        $checkDisplyingPricesFiles = $this->checkDisplyingPricesFiles($request);
        $getGeneralNotification = $this->getGeneralNotification($request);
        $popupAnnouncement = $this->checkPopupAnnouncement($request);

        return response()->json([
            'allCount' => $allCount,
            'cancelledCount' => $cancelledCount,
            'siteAdvertisment' => $siteAdvertisment,
            'notOpenednotif' => count($notif),
            'count_cars' => $count_cars,
            'towingCars' => $towingCars,
            'warehouseCars' => $warehouseCars,
            'loadingCars' => $loadingCars,
            'shippingCars' => $shippingCars,
            'uaePortCars' => $uaePortCars,
            'storeCars' => $storeCars,
            'PermittedtoBLFiles' => count($checkDisplyingBLFiles),
            'PermittedtoPricesFiles' => count($checkDisplyingPricesFiles),
            'GeneralNotification' => $getGeneralNotification,
            'popupAnnouncement' => $popupAnnouncement
        ], Response::HTTP_OK);
    }

    // Get customer balance
    public function getBalance(Request $request){
        $sumBalance = $this->GetSumBalance($request);
        $sumBalance_array = json_decode($sumBalance, true);
        $totalFines = $this->calcualteStorageFinesInUAE($request);

        return response()->json([
            'sumBalance' => number_format($sumBalance_array[0]['Debit'] - $sumBalance_array[0]['Credit'] + $totalFines , 2 )
        ], Response::HTTP_OK);
    }
}
