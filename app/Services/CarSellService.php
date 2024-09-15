<?php

namespace App\Services;
use App\Libraries\Constants;
use Illuminate\Support\Facades\DB;

class CarSellService
{
    public static function getAllCars($args){
        $result =  array_merge(self::getAllShippedCars($args), self::getAllLocalCars($args));
        foreach ($result as $key => $value) {
            if(self::IsShippedCar($value->car_id)){
                $photos = self::getCarShippedPhotos($value->car_id);
            }else{
                $photos = self::getCarLocalPhotos($value->car_id);
            }
            $result[$key]->photos = Constants::NEJOUM_CDN .'upload/car_for_sale/'. $photos[0]->photo_name;
        }
        return $result;
    }

    public static function getCarDetails($args){
        if(self::IsShippedCar($args['car_id']))
        {
            $car = self::getAllShippedCars($args);
            $photos = self::getCarShippedPhotos($args['car_id']);
        }else{
            $car = self::getAllLocalCars($args);
            $photos = self::getCarLocalPhotos($args['car_id']);
        }
        foreach ($photos as $key => $value) {
            $photos[$key] = Constants::NEJOUM_CDN .'upload/car_for_sale/'. $value->photo_name;
        }
        return array_merge($car, array($photos));
    }

    public static function getAllShippedCars($args){
        $select = 'car.carnotes as notes, shipped_car_for_sale.sale_price AS price, car.year as car_year,  car.vin, car.lotnumber, color.color_name,color.color_code,
        car_make.name AS carMakerName , car_model.name AS carModelName, vehicle_type.name AS vehicleName, "shipped" as local_shipped,
        car.id AS car_id';

        $query = DB::Table('car')
        ->leftJoin('shipped_car_for_sale', 'car.id', '=', 'shipped_car_for_sale.car_id')
        ->leftJoin('receive_car', 'car.id', '=', 'receive_car.car_id')
        ->leftJoin('car_make', 'car.id_car_make', '=', 'car_make.id_car_make')
        ->leftJoin('car_model', 'car.id_car_model', '=', 'car_model.id_car_model')
        ->leftJoin('color', 'car.color', '=', 'color.color_id')
        ->leftJoin('vehicle_type', 'car.id_vehicle_type', '=', 'vehicle_type.id_vehicle_type')
        ->leftJoin('customer', 'car.customer_id', '=', 'customer.customer_id')
        ->leftJoin('customer AS To', 'shipped_car_for_sale.sold_to', '=', 'To.customer_id')
        ->leftJoin('users', 'shipped_car_for_sale.create_by', '=', 'users.user_id')
        ->leftJoin('users AS Sold_by', 'shipped_car_for_sale.sold_by', '=', 'Sold_by.user_id')
        ->where('car.deleted', '0')
        ->where('car.cancellation', '0')
        ->where('car.status', '!=', '4')
        ->whereRaw("IF(receive_car.car_id, receive_car.deliver_status=0, true)")
        ->where('car.for_sell', '1');

        if (!empty($args['car_id'])) {
            $query->where('shipped_car_for_sale.car_id', $args['car_id']);
        }

        if (!empty($args['maker'])) {
            $query->where('car.id_car_make', $args['maker']);
        }

        if (!empty($args['model'])) {
            $query->where('car.id_car_model', $args['model']);
        }

        if (!empty($args['year'])) {
            $query->where('car.year', $args['year']);
        }

        if (!empty($args['priceOrder'])) {
            $query->orderBy('shipped_car_for_sale.sale_price', $args['priceOrder']);
        }else{
            $query->orderBy('car.create_date', 'DESC');
        }

        if(isset($args['per_page']) && isset($args['page'])){
            $query->skip(($args['page']-1)*$args['per_page'])->take($args['per_page'])->get();
        }

        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }

    public static function getAllLocalCars($args){

        $select = 'car_for_sale.note as notes, car_for_sale.car_for_sale_id AS car_id, car_for_sale.car_for_sale_id,car_for_sale.car_year, car_for_sale.lotnumber,car_for_sale.vin,car_for_sale.price as price,car_for_sale.car_year,car_make.name AS carMakerName,'
        . 'car_model.name AS carModelName,  color.color_name, "local" as local_shipped,'
        . 'vehicle_type.name AS vehicleName';

        $query = DB::Table('car_for_sale')
        ->leftJoin('car_make', 'car_for_sale.make_id', '=', 'car_make.id_car_make')
        ->leftJoin('car_model', 'car_for_sale.model_id', '=', 'car_model.id_car_model')
        ->leftJoin('color', 'car_for_sale.color', '=', 'color.color_id')
        ->leftJoin('vehicle_type', 'car_for_sale.vehicle_type_id', '=', 'vehicle_type.id_vehicle_type')
        ->where('car_for_sale.is_deleted', '0')
        ->where('car_for_sale.status', '1');

        if (!empty($args['car_id'])) {
            $query->where('car_for_sale.car_for_sale_id', $args['car_id']);
        }

        if (!empty($args['maker'])) {
        $query->where('car_for_sale.make_id', $args['maker']);
        }

        if (!empty($args['model'])) {
        $query->where('car_for_sale.model_id', $args['model']);
        }

        if (!empty($args['year'])) {
        $query->where('car_for_sale.car_year', $args['year']);
        }

        if (!empty($args['priceOrder'])) {
        $query->orderBy('car_for_sale.price', $args['priceOrder']);
        }else{
        $query->orderBy('car_for_sale.create_date', 'DESC');
        }

        if(isset($args['per_page']) && isset($args['page'])){
        $query->skip(($args['page']-1)*$args['per_page'])->take($args['per_page'])->get();
        }

        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }

    private static function IsShippedCar($car_id){
        $query = DB::table('car')
            ->join('shipped_car_for_sale', 'car.id', '=', 'shipped_car_for_sale.car_id')
            ->where('car.deleted', '0')
            ->where('car.for_sell', '1');

        if (!empty($car_id)) {
            $query->where('shipped_car_for_sale.car_id', $car_id);
        }

        $car = $query->first();

        if (is_null($car)) {
            return false;
        } else {
            return true;
        }
    } 

    private static function getCarShippedPhotos($car_id){
        $query = DB::table('photo_shipped_car_for_sell')
        ->select('photo_name');

        if (!empty($car_id)) {
            $query->where('car_id', $car_id);
        }

        $photos = $query->get()->toArray();
        return $photos;
    }

    private static function getCarLocalPhotos($car_id){
        $query = DB::table('photo_car_for_sale')
        ->select('photo_name');

        if (!empty($car_id)) {
            $query->where('car_for_sale_id', $car_id);
        }

        $photos = $query->get()->toArray();
        return $photos;
    }
    
    public static function getFavoritdata($args){

        $query = DB::Table('favorit_cars')
        ->select('*')
        ->where('deleted', '0');

        if (!empty($args['customer_id'])) {
        $query->where('customer_id', $args['customer_id']);
        }

        return $query->get()->toArray();
    }

    public static function addtoFavorit($args){

        DB::table('favorit_cars')->insert($args);
        return DB::getPdo()->lastInsertId();
    }

    public static function changestatusfave($args){

        $data = array('deleted' => '1');

        $affected = DB::table('favorit_cars')
            ->where('car_id', $args['car_id'])
            ->where('customer_id', $args['customer_id'])
            ->update($data);

      if ($affected > 0)
        return TRUE;
      else
        return FALSE;
    }

    public static function getMakerShippingCars($year = ''){
        $where = "";
        if($year){
            $where = " AND B.year = '".$year."'";
        }
        $dataShippingCars = DB::select(DB::raw("SELECT A.id_car_make, A.name, (
            SELECT COUNT(*) FROM car B
            LEFT JOIN receive_car ON B.id = receive_car.car_id
            WHERE B.id_car_make = A.id_car_make and B.for_sell = '1'  and B.deleted = '0' and B.cancellation = '0' and B.status != '4' $where and IF(receive_car.car_id, receive_car.deliver_status=0, true)
            ) AS total 
        FROM car_make A
        having total > 0
        "));
        $dataShippingCars = $dataShippingCars;
        return $dataShippingCars;
    }

    public static function getMakerCarsForSell($year = ''){
        $where = "";
        if($year){
            $where = " AND B.car_year = '".$year."'";
        }
        $dataCarsForSell = DB::select(DB::raw("SELECT A.id_car_make, A.name, (
            SELECT COUNT(*) FROM car_for_sale B WHERE B.make_id = A.id_car_make and B.is_deleted = '0' and B.status = '1' $where
            ) AS total 
        FROM car_make A
        having total > 0
        "));
        $dataCarsForSell = $dataCarsForSell;
        return $dataCarsForSell;
    }

    public static function getModelShippingCars($maker_id, $year){
        $where = "";
        if($year){
            $where = " AND B.year = '".$year."'";
        }
        $dataShippingCars = DB::select(DB::raw("SELECT A.id_car_model, A.name, (
            SELECT COUNT(*) FROM car B
            LEFT JOIN receive_car ON B.id = receive_car.car_id
            WHERE B.id_car_model = A.id_car_model $where and B.for_sell = '1'  and B.deleted = '0' and B.cancellation = '0' and B.status != '4' and IF(receive_car.car_id, receive_car.deliver_status=0, true) and B.id_car_make = ".$maker_id."
            ) AS total 
        FROM car_model A
        having total > 0
        "));
        $dataShippingCars = $dataShippingCars;
        return $dataShippingCars;
    }

    public static function getModelCarsForSell($maker_id, $year){
        $where = "";
        if($year){
            $where = " AND B.car_year = '".$year."'";
        }
        $dataCarsForSell = DB::select(DB::raw("SELECT A.id_car_model, A.name, (
            SELECT COUNT(*) FROM car_for_sale B WHERE B.model_id = A.id_car_model $where and B.is_deleted and B.is_deleted = '0' and B.status = '1' and B.make_id = ".$maker_id."
            ) AS total 
        FROM car_model A
        having total > 0
        "));
        $dataCarsForSell = $dataCarsForSell;
        return $dataCarsForSell;
    }
    public function addShippingCost($carId){
        $dataCarsForSell = $this->dataCarsForSell($carId);
        if($dataCarsForSell){
            $port = $this->portRegion($dataCarsForSell->auction_location_id, $dataCarsForSell->id);
            $customerList = $this->customerList($dataCarsForSell->customer_id);
            $query = DB::Table('shipping')
            ->select('shipping.*');
            if ($dataCarsForSell->vehicle_type == 1) {
                $query->where('shipping.shipping_list_id', $customerList->shipping_list_id);
            } else {
                $query->where('shipping.shipping_list_id', $customerList->bike_list_id);
            }
            if ($port) {
                $query->where('shipping.port_id', $port->port_id);
            }
            $query->join('shipping_contract','shipping_contract.shipping_contract_id = shipping.shipping_contract_id');
            $query->where('shipping_contract.start_date <=',$dataCarsForSell->purchasedate);
            $query->where('shipping_contract.end_date >=',$dataCarsForSell->purchasedate);
            $query->where('shipping.port_id_distination', $dataCarsForSell->destination);
            DB::Table('car_sell_price')->updateOrInsert(['car_id'=> $dataCarsForSell->id],array('shipping_cost' => round($query->first()->price)));
            return 1;
        }  
        return 0;
        
    }
    private function auctionLocationObject($auctionLocation) {
        if(!$this->auctionLocation){
            $this->auctionLocation = DB::Table('auction_location')
            ->where('auction_location_id', $auctionLocation)
            ->first();
        }
        return $this->auctionLocation;
    }
    private function dataCarsForSell($carId){
        if(!$this->dataCarsForSell){
            $this->dataCarsForSell = DB::Table('car')->
            select(DB::raw("id,id_vehicle_type,external_car,auction_location_id,destination,customer_id,year,purchasedate"))->where('id', $carId)->first();
        }
        return $this->dataCarsForSell;
    }
    private function portRegion($auctionLocation, $car_id = ''){
        $auctionLocationObject = $this->auctionLocationObject($auctionLocation);
        if($auctionLocationObject->region_id == 0){
            $region = $this->getRegionfromExternal($car_id)->region_id;
        }else {
            $region = $auctionLocationObject->region_id;
        }
        if(!$this->port){
            $this->port = DB::Table('port')
            ->where('port.region_id', $region)->first();
        }
        return $this->port;
    }
    private function getRegionfromExternal($car_id){
        if(!$this->regionfromExternal){
            $this->regionfromExternal = DB::Table('external_car')->select('region_id')->where('car_id', $car_id)->first();
        }
        return $this->regionfromExternal;
    }
    public function checkYear($car_id){
        $car = $this->dataCarsForSell($car_id);
        return $car->year;
    }
    private function customerList($customer_id){
        if(!$this->customerList){
            $this->customerList = DB::Table('customer_list')
            ->where('customer_list.customer_id', $customer_id)->first();
        }
        return $this->customerList;
    }


}
