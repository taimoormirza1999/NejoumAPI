<?php

namespace App\Http\Controllers\CarSell;

use App\Http\Controllers\Controller;
use App\Libraries\Constants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Libraries\Helpers;
use App\Services\CarSellService;
use Symfony\Component\HttpFoundation\Response;

class CarsForSaleController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    public function getAllCarsForSale(Request $request){
        $args = $request->all();
        $args['customer_id']  = !empty($request->customer_id) ? $request->customer_id : $request->user()->customer_id;
        $data = CarSellService::getAllCars($args);
        $newfavorite_arr = ($args['customer_id'])?CarSellService::getFavoritdata($args):[];
        return response()->json([
            'data' => $data,
            'newfavorite_arr' => $newfavorite_arr
        ], Response::HTTP_OK);
    }

    public function getCarDetails(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $data = CarSellService::getCarDetails($args);
        return response()->json([
            'data' => $data
        ], Response::HTTP_OK);
    }

    public function getFavoritdata(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $data = CarSellService::getFavoritdata($args);

        if($data){
            $output = array(
                "success"   => 'success',
                "data"      => $data
            );
        }else{
            $output = array(
                "success"   => 'faild',
                "data"      => []
            );
        }

        return response()->json([
            'data' => $output
        ], Response::HTTP_OK);
    }

    public function addtoFavorit(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $data = CarSellService::addtoFavorit($args);
        $newfavorite_arr = CarSellService::getFavoritdata($args['customer_id']);

        if($data){
            $output = array(
                "success"   => 'success',
                "newfavorite_arr"      => $newfavorite_arr
            );
        }else {
            $output = array(
                "success"   => 'faild',
                "newfavorite_arr"      => []
            );
        }

        return response()->json([
            'data' => $output
        ], Response::HTTP_OK);
    }

    public function changestatusfave(Request $request){
        $args = $request->all();
        $args['customer_id']  = $request->user()->customer_id;
        $data = CarSellService::changestatusfave($args);

        if($data){
            $output = array(
                "success"   => 'success',
                "data"      => $data
            );
        }else{
            $output = array(
                "success"   => 'faild',
                "data"      => []
            );
        }

        return response()->json([
            'data' => $output
        ], Response::HTTP_OK);
    }
}