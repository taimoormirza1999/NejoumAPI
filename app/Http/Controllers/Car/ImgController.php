<?php


namespace App\Http\Controllers\Car;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\CustomerCarService as CustomerCar;
use App\Libraries\Constants;
use App\Libraries\Helpers;
use App\Services\CarService;
use App\Services\ImageService;
use Symfony\Component\HttpFoundation\Response;

class ImgController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */



    public function __construct()
    {
    //    /
    }

    public function getwarehouseImages(Request $request)
    {
        $nejoum_img_url = config('constants.IMG.nejoumImgBase');
        $warehouse_img_url  = config('constants.IMG.nejoumExpressImgBase');
        $imgquery   = DB::connection('mysql')->raw('(CASE
                        WHEN arrived_car_photo.create_by = 99
                        THEN CONCAT("'.$warehouse_img_url.'", arrived_car_photo.photo_name)
                        ELSE CONCAT("'.$nejoum_img_url.'", arrived_car_photo.photo_name) END) as photo_name');
        $car_id    = $request->car_id;
        $CarsImg   = DB::connection('mysql')->Table('car')
        ->leftJoin('arrived_car_photo', 'arrived_car_photo.car_id', '=', 'car.id')
        ->select('car.id' , 'arrived_car_photo.create_by', $imgquery, 'arrived_car_photo.photo_name as name')
        ->where([
            ['car.id', '=', $car_id]
        ])
        ->get()->toArray();
        foreach ($CarsImg as $key => $value) {
            if($value->create_by == Constants::WAREHOUSE_SYSTEM){
                $new_url = Helpers::get_internal_url($value->id, $value->name);
                if(@getimagesize($new_url.$name)) {
                    $CarsImg[$key]->photo_name =  $new_url.$name;
                }
            }
        }
        return response()->json($CarsImg);
    }

    public function getAllImages(Request $request)
    {
        $nejoum_img_url     = config('constants.IMG.nejoumImgBase');
        $car_id             = $request->car_id;

        $CarsImg   = DB::connection('mysql')->Table('car')
        ->leftJoin('arrived_car_photo', 'arrived_car_photo.car_id', '=', 'car.id')
        ->select('CONCAT("'.$warehouse_img_url.'", arrived_car_photo.photo_name) as photo_name')
        ->where([
            ['car.id', '=', $car_id]
        ])
        ->get();
        return response()->json($CarsImg);
    }
    public function getImages(Request $request) {
        $car_id                 = $request->car_id;
        $container_no                 = $request->container_no;
        $type                 = $request->type;
        $system                 = $request->system;
        if(!$container_no){
            $container_no = ImageService::getContainerUsingCarID($car_id);
        }
        if($type == 'warehouse' || $type == 'shipping'|| $type == 'damagewarehouse'){
            $images = ImageService::getCarImagesUsingID($car_id);
            if ($images) {
                $result = [];
                foreach ($images as $key => $value){
                    if($type == 'damagewarehouse'){
                        $result[] = ['id'=>$value->id,'image'=>$value->photo_name];
                    }else{
                        $result[] = $value->photo_name;
                    }
                }
                $data = ['images'=>$result];
                return response()->json($data, Response::HTTP_OK);
            }
            $image_data = CustomerCar::getArrivedCarsPhoto1($car_id);

            if (!$image_data) {
                $image_data = CustomerCar::getArrivedCarsPhoto2($car_id);
            }

            $warehouse_url =  "";
            if(!empty($image_data[0]->create_by) && $image_data[0]->create_by == Constants::WAREHOUSE_SYSTEM){
                $warehouse_url = Helpers::get_internal_url($car_id,$image_data[0]->photo_name);
            }

            $old_url = 'https://old.nejoum.net/upload/car_images/warehouse_car/';
            $warehouse_url = @getimagesize($warehouse_url . $image_data[0]->photo_name) ?$warehouse_url :$old_url;
            $checkIfInWareHouse = @getimagesize($warehouse_url . $image_data[0]->photo_name);
            if ($checkIfInWareHouse){
                $warehouse_url= $warehouse_url;
            }
            elseif ( @getimagesize($old_url . $image_data[0]->photo_name)){
                $warehouse_url= $old_url;
            }
            else {
                //none of the above is correct, so we will use method 2 --slower
                $newUrls = json_decode(Helpers::get_internal_url($car_id,$image_data[0]->photo_name,'2'));
                $warehouse_url = $old_url;
                if(is_array($newUrls)){
                    foreach ($newUrls as $key=>$value){
                        if (@getimagesize($value. $image_data[0]->photo_name)){
                            $warehouse_url=$value;
                            break;
                        }
                    }
                }
            }

            $images = [];
            foreach ($image_data as $key2 => $value2):
                if($value2->create_by == Constants::WAREHOUSE_SYSTEM){
                    if($type == 'damagewarehouse'){
                        $images[] = ['id'=>$value2->arrived_car_photo_id,'image'=>$warehouse_url . $value2->photo_name];
                    }else{
                        $images[] = $warehouse_url . $value2->photo_name;
                    }
                }else{
                    if($type == 'damagewarehouse'){
                        $images[] = ['id'=>$value2->arrived_car_photo_id,'image'=>Constants::NEJOUM_CDN . 'upload/car_images/warehouse_car/'. $value2->photo_name];
                    }else{
                        $images[] = Constants::NEJOUM_CDN . 'upload/car_images/warehouse_car/'. $value2->photo_name;
                    }
                }
            endforeach;
        }else if ($type == 'store' || $type == 'damagestore') {
            $image_data = CustomerCar::getArrivedStoreCarsPhoto($car_id);
            foreach ($image_data as $key2 => $value2):
                if (!$system && @getimagesize(Constants::NEJOUM_CDN .'upload/car_images/store_car/app/'.$value2->photo_name)){
                    if($type == 'damagestore'){
                        $images[] = ['id'=>$value2->receive_car_photo_id,'image'=>Constants::NEJOUM_CDN .'upload/car_images/store_car/app/' . $value2->photo_name];
                    }else{
                        $images[] = Constants::NEJOUM_CDN .'upload/car_images/store_car/app/' . $value2->photo_name;
                    }
                } elseif(@getimagesize(Constants::NEJOUM_CDN .'upload/car_images/store_car/'.$value2->photo_name)){
                    if($type == 'damagestore'){
                        $images[] = ['id'=>$value2->receive_car_photo_id,'image'=>Constants::NEJOUM_CDN .'upload/car_images/store_car/' . $value2->photo_name];
                    }else{
                        $images[] = Constants::NEJOUM_CDN .'upload/car_images/store_car/' . $value2->photo_name;
                    }
                }
            endforeach;
        }else if ($type == 'loading') {
            $images = $container_no?ImageService::getLoadingImagesUsingContainer($container_no):[];
            if ($images) {
                $result = [];
                foreach ($images as $key => $value){
                    $result[] = $value->photo_name;
                }
                $data = ['images'=>$result];
                return response()->json($data, Response::HTTP_OK);
            }
            $images = ImageService::getloadCarImages($car_id);
            if ($images) {
                $result = [];
                foreach ($images as $key => $value){
                    $result[] = Constants::NEJOUM_CDN .'upload/car_images/load_car/' . $value->photo_name;
                }
                $data = ['images'=>$result];
                return response()->json($data, Response::HTTP_OK);
            }
        }
        else {
            $images = [];
        }
        $data = ['images'=>$images];
        return response()->json($data, Response::HTTP_OK);
    }
    public function getDownloadableImages(Request $request)
    {

        $img_data = $this->getImages($request);
        $images = $img_data->getData()->images;
        // zip the images and return the zip file
        if($images){
            echo 'please wait while we are zipping your images...';
            $zip_name = 'images.zip';
            $file_path = storage_path($zip_name);
            $zip = new \ZipArchive;
            $zip->open($file_path, (\ZipArchive::CREATE | \ZipArchive::OVERWRITE));
            foreach ($images as $image) {
                $image_file = file_get_contents($image);
                // $ch = curl_init();
                // curl_setopt($ch, CURLOPT_URL, $image);
                // curl_setopt($ch, CURLOPT_HEADER, false);
                // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                // $res = curl_exec($ch);
                // $rescode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
                // if($res){
                    $zip->addFromString(basename($image), $image_file);
                // }
                // curl_close($ch) ;
            }
            $zip->close();
            $headers = array(
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename=' . $zip_name,
            );
            echo '<br/>';
            echo 'files zipped successfully.';
            $response = response()->download($file_path, 'images.zip', $headers, 'attachment');
            ob_end_clean();
            
            return $response;
        }
        return false;
    }

    public function getDownloadableImagesTest(Request $request)
    {

        $img_data = $this->getImages($request);
        $images = $img_data->getData()->images;
        // zip the images and return the zip file
        if($images){
            echo 'please wait while we are zipping your images...';
            $zip_name = 'images.zip';
            echo '1'.$zip_name;
            $file_path = storage_path($zip_name);
            echo '2'.$file_path;
            $zip = new \ZipArchive;
            $zip->open($file_path, (\ZipArchive::CREATE | \ZipArchive::OVERWRITE));
            foreach ($images as $image) {
                echo 'inhere';
                $image_file = file_get_contents($image);
                // $ch = curl_init();
                // curl_setopt($ch, CURLOPT_URL, $image);
                // curl_setopt($ch, CURLOPT_HEADER, false);
                // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                // $res = curl_exec($ch);
                // $rescode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
                // print_r($rescode);
                // print_r($res);
                $zip->addFromString(basename($image), $image_file);
                //curl_close($ch) ;
            }
            echo '3';
            $zip->close();
            echo '4';
            $headers = array(
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename=' . $zip_name,
            );
            echo '<br/>';
            echo 'files zipped successfully.';
            $response = response()->download($file_path, 'images.zip', $headers, 'attachment');
            //ob_end_clean();
            
            return $response;
        }
        return false;
    }
}
