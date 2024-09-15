<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ImageService
{
    public static function getCarImagesUsingID($id){
        $query = DB::Table('wdb_images')
                    ->selectRaw('image_url as photo_name,id')
                    ->where('car_id', $id)
                    ->groupBy('image_url')
                    ->get();

        return $query->toArray();
    }

    public static function getLoadingImagesUsingContainer($container_no){
        $query = DB::connection('mysql2')
                    ->Table('from_warehouse_containers_images')
                    ->selectRaw('image_url as photo_name')
                    ->where('container_no', $container_no)
                    ->get();

        return $query->toArray();
    }
    public static function getloadCarImages($car_id)
    {
        $query = DB::Table('loading_car_photo')
            ->select('*')
            ->where('shipping_order_car_id', $car_id)
            ->get();
        return $query->toArray();
    }

    public static function getContainerUsingCarID($car_id)
    {
        $query = DB::Table('container')
            ->select('container_number')
            ->leftJoin('container_car', 'container_car.container_id','=','container.container_id')
            ->where('container_car.car_id', $car_id)
            ->limit(1);
            return $query->first()->container_number;
    }
}
