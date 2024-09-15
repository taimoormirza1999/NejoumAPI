<?php


namespace App\Http\Controllers\CustomerService;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Libraries\Constants;
use App\Libraries\Helpers;
use App\Services\CustomerCarService as CustomerCar;
use App\Services\CustomerService;
use App\Services\DashboardService;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    
    protected $customer_id;
    public function __construct()
    {
        $this->customer_id = Auth::user()->customer_id;
    }

    public function customerGroups(Request $request)
    {
        $args                   = $request->all();
        $customerGroups        = CustomerService::customerGroups($args);
        $data = [
            'data'          => $customerGroups,
            'totalRecords'  => count($customerGroups)
        ];

        return response()->json($data, Response::HTTP_OK);
    }
    
    public function customerLists(Request $request)
    {
        $args                   = $request->all();
        $customerLists        = CustomerService::customerLists($args);
        $data = [
            'data'          => $customerLists,
            'totalRecords'  => count($customerLists)
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function activeContractCustomerLists(Request $request)
    {
        $args                   = $request->all();
        $customerLists        = CustomerService::activeContractCustomerLists($args);
        $data = [
            'data'          => $customerLists,
            'totalRecords'  => count($customerLists)
        ];

        return response()->json($data, Response::HTTP_OK);
    }
    
    public function customerCurrentLists($customer_id)
    {
        $args = [
            'customer_id' => $customer_id,
        ];
        $customerLists        = CustomerService::customerCurrentLists($args);
        $data = [
            'data'          => $customerLists,
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function saveAgencyDocument(Request $request){
        $data = $request->all();
        $customer_id = $data['customer_id'] ?? $this->customer_id;
        $external_car_contact = $data['external_car_contact'];

        try{
            $result = CustomerService::saveAgencyDocument($customer_id, $data);
            if($external_car_contact){
                $result = DB::table('customer')->where('customer_id', $data['customer_id'])->update(['external_car_contact' => $external_car_contact]);
            }
        }
        catch(\Exception $e){
            $output = array(
                'success'=> false,
                'message' => $e->getMessage()
            );
            return response()->json($output, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if($result){
            $output = array(
                'success'=> true,
                'message' => 'Saved successfully'
            );
        }else {
            $output = array(
                'success'=> false,
                'message' => 'Failed to update'
            );
        }
        return response()->json($output, Response::HTTP_OK);
    }

    public function getAgencyDocument(Request $request){
        $customer_id = $this->customer_id;

        try{
            if($customer_id){
                $output = [
                    'data' => CustomerService::getAgencyDocument($customer_id),
                ];
            }
            else{
                $output = [
                    'data' => [],
                ];
            }
            
            return response()->json($output, Response::HTTP_OK);
        } catch(\Exception $e){
            $output = array(
                'success'=> false,
                'message' => $e->getMessage()
            );
            return response()->json($output, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function hasAgencyDocument(Request $request){
        $customer_id = $this->customer_id;

        try{
            $output = [
                'data' => CustomerService::hasAgencyDocument($customer_id),
            ];
            return response()->json($output, Response::HTTP_OK);

        } catch(\Exception $e){
            $output = array(
                'success'=> false,
                'message' => $e->getMessage()
            );
            return response()->json($output, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
   
}