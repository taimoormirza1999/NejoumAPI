<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Libraries\Constants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CarAccounting;
use App\Libraries\Helpers;
use App\Services\AccountingService;
use App\Services\GeneralService;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;


class AccountingController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->customer_id        = Auth::user()->customer_id;
    }

    public function shippingCalculator(Request $request)
    {
        $args                       = $request->all();
        $args['customer_id']        = $this->customer_id;

        $shippingEstimatedCostData  = AccountingService::getShippingEstimatedCost($args);
        if ($shippingEstimatedCostData) {
            $towingCost                 = ($shippingEstimatedCostData->towing_cost) ? $shippingEstimatedCostData->towing_cost : 0 ;
            $shippingCost               = ($shippingEstimatedCostData->shipping_cost) ? $shippingEstimatedCostData->shipping_cost : 0 ;
            $loadingCost                = ($shippingEstimatedCostData->loading_cost) ? $shippingEstimatedCostData->loading_cost : 0 ;
            $clearanceCost              = ($shippingEstimatedCostData->clearance_cost) ? $shippingEstimatedCostData->clearance_cost : 0 ;
            $transportationCost         = ($shippingEstimatedCostData->transportation_cost) ? $shippingEstimatedCostData->transportation_cost : 0 ;
            $excludeServices = AccountingService::getBusinessServicesCustomers($args['customer_id']);
            if (in_array(Constants::CUSTOMER_EXCLUDE_SERVICES['TOWING'], $excludeServices)) {
                $towingCost = 0;
            }
            if (in_array(Constants::CUSTOMER_EXCLUDE_SERVICES['LOADING'], $excludeServices)) {
                $loadingCost = 0;
            }
            if (in_array(Constants::CUSTOMER_EXCLUDE_SERVICES['SHIPPING'], $excludeServices)) {
                $shippingCost = 0;
            }
            if (in_array(Constants::CUSTOMER_EXCLUDE_SERVICES['CLEARANCE'], $excludeServices)) {
                $clearanceCost = 0;
            }
            if (in_array(Constants::CUSTOMER_EXCLUDE_SERVICES['TRANSPORTATION'], $excludeServices)) {
                $transportationCost = 0;
            }

            $result                     = $towingCost + $shippingCost + $loadingCost + $clearanceCost + $transportationCost;
        } else {
            $result = 0;
        }
        

        $data = [
            "data"      => number_format($result, 2),
            "dirhams"   => number_format($result*3.675,2)
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function averageContainerShippingPrice(Request $request)
    {
        $args                   = $request->all();
        $totalContainersDB        = AccountingService::totalContainersByRegion($args);
        $shippingPricesDB        = AccountingService::shippingCompanyPrices($args);

        $shippingPrices = [];
        foreach($shippingPricesDB as $row){
            $newKey = $row->destination .'_'. $row->region_id .'_'. $row->shipping_company_id;
            $shippingPrices [ $newKey ] = $row;
        }

        $totalContainers = [];
        foreach($totalContainersDB as $row){
            $newKey = $row->destination .'_'. $row->region_id .'_'. $row->shipping_company_id;
            $totalContainers [ $newKey ] = $row;
        }

        $regionDestinationContainers = [];
        foreach($totalContainers as $key => $row){
            $pod = $row->destination;
            $region_id = $row->region_id;
            $shipping_price_key = $row->destination .'_'. $row->region_id .'_'. $row->shipping_company_id;;
            if(empty($regionDestinationContainers[$pod])){
                $regionDestinationContainers[$pod] = [];
            }
            if(empty($regionDestinationContainers[$pod][ $region_id ])){
                $regionDestinationContainers[$pod][ $region_id ] = [];
            }

            $shippingPrice = $shippingPrices[$shipping_price_key];
            $shipping_line_price = ($shippingPrice->price_for_40 * $row->total_normal_containers_40) + 
            ( ($shippingPrice->price_for_40 + $shippingPrice->price_for_spot)* $row->total_spot_containers_40 );

            $shipping_line_price_45 = ($shippingPrice->price_for_45 * $row->total_normal_containers_45) + 
            ( ($shippingPrice->price_for_45 + $shippingPrice->price_for_spot)* $row->total_spot_containers_45 );



            $regionDestinationContainers[$pod][ $region_id ] [$row->shipping_company_id]= [
                'destination' => $row->destination,
                'region_id' => $row->region_id,
                'shipping_company_id' => $row->shipping_company_id,
                'shipping_line_price' => $shipping_line_price,
                'shipping_line_price_45' => $shipping_line_price_45,
                'total_containers_40' => $row->total_normal_containers_40 + $row->total_spot_containers_40,
                'total_containers_45' => $row->total_normal_containers_45 + $row->total_spot_containers_45
            ];
        }

        foreach($regionDestinationContainers as $key => $regions){
            foreach($regions as $subKey => $regionLines){
                $total_containers_40 = array_sum(array_column($regionLines, 'total_containers_40'));
                $total_containers_45 = array_sum(array_column($regionLines, 'total_containers_45'));
                $shipping_line_price = array_sum(array_column($regionLines, 'shipping_line_price'));
                $shipping_line_price_45 = array_sum(array_column($regionLines, 'shipping_line_price_45'));

                $regionLines['average_price'] = $total_containers_40 ? round($shipping_line_price / $total_containers_40) : round($shipping_line_price) ;
                $regionLines['average_price_45'] = $total_containers_45 ? round($shipping_line_price_45 / $total_containers_45) : round($shipping_line_price_45) ;
                
                $regions[$subKey] = $regionLines;
                $regionDestinationContainers[$key] = $regions;
            }
        }

        $data = [
            'data'          => $regionDestinationContainers,
            'totalRecords'  => count($regionDestinationContainers)
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function averageContainerLoadingPrice(Request $request)
    {
        $args                   = $request->all();
        $loadingPricesDB        = AccountingService::loadingCompanyPrices($args);

        $loadingPrices = [];
        foreach($loadingPricesDB as $row){
            $newKey = $row->region_id;
            $loadingPrices [ $newKey ] = $row;
        }

        $data = [
            'data'          => $loadingPrices,
            'totalRecords'  => count($loadingPrices)
        ];

        return response()->json($data, Response::HTTP_OK);
    }
    
    public function getGeneratedPriceLists(Request $request)
    {
        $args                   = $request->all();
        $priceLists             = AccountingService::getGeneratedPriceLists($args);

        foreach($priceLists as $key => $priceList){
            $priceList = (array)$priceList;
            $priceListDetailDB = AccountingService::getPriceListsDetail(['price_list_id' => $priceList['price_list_id']]);

            $priceList['container_prices'] = [];
            foreach($priceListDetailDB as $row){
                $priceList['container_prices'][ $row->sale_list_id ][$row->region_id][] = $row;
            }

            $priceLists[$key] = $priceList;
        }

        $data = [
            'data'          => $priceLists,
            'totalRecords'  => count($priceLists)
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function editGeneratedPriceList(Request $request)
    {
        $args               = $request->all();
        $priceListDB        = AccountingService::editGeneratedPriceList($args);
        $lastRow = end($priceListDB);
        $priceList = [];

        if($lastRow){
            $priceList = [
                'price_list_id' => $lastRow->price_list_id,
                'service_id' => $lastRow->service_id,
                'margin_type' => $lastRow->margin_type,
                'container_margin' => $lastRow->container_margin,
                'start_date' => $lastRow->start_date,
                'end_date' => $lastRow->end_date,
                'create_by' => $lastRow->create_by,
                'creator_name' => $lastRow->creator_name,
                'create_date' => $lastRow->create_date,
                'status' => $lastRow->status,
                'deleted' => $lastRow->deleted,
            ];

            $priceList['container_prices'] = [];
        }

        foreach ($priceListDB as $row) {
            $rowArray = (array)$row;
            foreach ($rowArray as $rowKey => $rowValue) {
                if (in_array($rowKey, ['price_list_id', 'create_date', 'service_id', 'margin_type', 'container_margin', 'car_margin', 'create_by', 'creator_name'])) {
                    unset($rowArray[$rowKey]);
                }
            }

            switch ($args['service_id']) {
                case Constants::NAJ_SERVICES['SHIPPING']:
                    $priceList['container_prices'][$row->sale_list_id][$row->pod][$row->region_id] = $rowArray;
                    break;
                case Constants::NAJ_SERVICES['LOADING']:
                    $priceList['container_prices'][$row->sale_list_id][$row->region_id] = $rowArray;
                    break;
                default:
                    break;
            }
        }

        $data = [
            'data' => $priceList,
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function getPriceListNotes(Request $request)
    {
        $args         = $request->all();
        $notes        = GeneralService::getOperationNotesArray($args);

        $data = [
            'data'          => $notes,
            'totalRecords'  => count($notes)
        ];

        return response()->json($data, Response::HTTP_OK);
    }        
    
    public function savePriceListNote(Request $request)
    {
        $data                   = $request->all();

        if($data['note_id']){
            $note_id = $data['note_id'];
            unset($data['note_id']);
            DB::table('operations_notes')->where('id', $note_id)->update($data);
        }
        else{
            unset($data['note_id']);
            DB::table('operations_notes')->insert($data);
        }

        if($data){
            $data = [
                'success'=> true,
                'message' => 'Saved successfully'
            ];
        }else{
            $data = [
                'success'=> false,
                'message' => 'Failed to save'
            ];
        }
        return response()->json($data, Response::HTTP_OK);
    }
    
    public function deletePriceListNote($note_id)
    {
        if($note_id){
            DB::table('operations_notes')->delete($note_id);
        }

        if($note_id){
            $data = [
                'success'=> true,
                'message' => 'Deleted successfully'
            ];
        }else{
            $data = [
                'success'=> false,
                'message' => 'Failed to delete'
            ];
        }
        return response()->json($data, Response::HTTP_OK);
    }

    public function getNAJShippingBroker(Request $request)
    {
        $args         = $request->all();
        $args['shipping_broker'] = Constants::SHIPPING_BROKER['NAJ_LOGISTIC'];
        $result        = AccountingService::getShippingBrokerCommission($args);

        $data = [
            'data'          => $result,
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function getNAJShippingBrokerCommission(Request $request)
    {
        $args         = $request->all();
        $args['shipping_broker'] = Constants::SHIPPING_BROKER['NAJ_LOGISTIC'];
        $result        = AccountingService::getShippingBrokerCommission($args);

        $data = [
            'data'          => $result->amount,
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function getListPrices(Request $request)
    {
        $args                   = $request->all();
        $priceLists             = AccountingService::getListPrices($args);

        $data = [
            'data'          => $priceLists,
            'totalRecords'  => count($priceLists)
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function shippingCalculatornoAuth(Request $request)
    {
        $args                       = $request->all();
        $shippingEstimatedCostData  = AccountingService::getShippingEstimatedCost($args);
        if ($shippingEstimatedCostData) {
            $towingCost                 = ($shippingEstimatedCostData->towing_cost) ? $shippingEstimatedCostData->towing_cost : 0 ;
            $shippingCost               = ($shippingEstimatedCostData->shipping_cost) ? $shippingEstimatedCostData->shipping_cost : 0 ;
            $loadingCost                = ($shippingEstimatedCostData->loading_cost) ? $shippingEstimatedCostData->loading_cost : 0 ;
            $clearanceCost              = ($shippingEstimatedCostData->clearance_cost) ? $shippingEstimatedCostData->clearance_cost : 0 ;
            $transportationCost         = ($shippingEstimatedCostData->transportation_cost) ? $shippingEstimatedCostData->transportation_cost : 0 ;
            $excludeServices = AccountingService::getBusinessServicesCustomers($args['customer_id']);
            if (in_array(Constants::CUSTOMER_EXCLUDE_SERVICES['TOWING'], $excludeServices)) {
                $towingCost = 0;
            }
            if (in_array(Constants::CUSTOMER_EXCLUDE_SERVICES['LOADING'], $excludeServices)) {
                $loadingCost = 0;
            }
            if (in_array(Constants::CUSTOMER_EXCLUDE_SERVICES['SHIPPING'], $excludeServices)) {
                $shippingCost = 0;
            }
            if (in_array(Constants::CUSTOMER_EXCLUDE_SERVICES['CLEARANCE'], $excludeServices)) {
                $clearanceCost = 0;
            }
            if (in_array(Constants::CUSTOMER_EXCLUDE_SERVICES['TRANSPORTATION'], $excludeServices)) {
                $transportationCost = 0;
            }

            $result                     = $towingCost + $shippingCost + $loadingCost + $clearanceCost + $transportationCost;
        } else {
            $result = 0;
        }
        

        $data = [
            "data"      => number_format($result, 2),
            "dirhams"   => number_format($result*3.675,2)
        ];

        return response()->json($data, Response::HTTP_OK);
    }

}
