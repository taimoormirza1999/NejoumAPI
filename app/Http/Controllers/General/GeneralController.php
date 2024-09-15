<?php


namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Libraries\Constants;
use App\Libraries\Helpers;
use App\Models\General;
use App\Services\CarSellService;
use App\Services\CarService;
use App\Services\GeneralService;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Requests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\AppTrafficRequestPayment;
use App\Models\AppTrafficRequest;
use App\Models\ServiceRequest;
use App\Models\AppTrafficFeedback;
use App\Http\Controllers\PaymentController;
use App\Models\User;
use Illuminate\Support\Str;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;

class GeneralController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */



    public function __construct()
    {
        //
    }

    public function complaintMessage(Request $request)
    {
        $args = $request->all();
        $args['customer_id'] = $request->user()->customer_id;

        try{
        $complaintMessages = General::getComplaintMessage($args);
        $complaintMessage = [];

        foreach ($complaintMessages as $dbRow) {
            $dbRow->complaint_no = str_pad($dbRow->complaint_message_id, 6, 0, STR_PAD_LEFT);
            $dbRow->readable_create_date = date('M j, Y, g:i a', strtotime($dbRow->create_date));
            $dataRow = $dbRow;
            $complaintMessage[] = $dataRow;
        }
        $data = [
            'data' => $complaintMessage,
            'totalRecords' => General::getComplaintMessageCount($args)
        ];

        return response()->json($data, Response::HTTP_OK);
        }
        catch(\Exception $e){
            $output = array(
                'success'=> false,
                'message' => $e->getMessage()
            );
            return response()->json($output, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function complaintMessageNoAuth(Request $request)
    {
        $args = $request->all();
        $args['customer_id'] = $request->customer_id;
        $args['complaint_status'] = $request->complaint_status;

        try{
        $complaintMessages = General::getComplaintMessage($args);
        $complaintMessage = [];
        foreach ($complaintMessages as $dbRow) {
            $dbRow->complaint_no = str_pad($dbRow->complaint_message_id, 6, 0, STR_PAD_LEFT);
            $dbRow->readable_create_date = date('M j, Y, g:i a', strtotime($dbRow->create_date));
            $dataRow = $dbRow;
            $complaintMessage[] = $dataRow;
        }
        $data = [
            'data' => $complaintMessage,
            'totalRecords' => General::getComplaintMessageCount($args)
        ];

        return response()->json($data, Response::HTTP_OK);
        }
        catch(\Exception $e){
            $output = array(
                'success'=> false,
                'message' => $e->getMessage()
            );
            return response()->json($output, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function seenGeneralNotification(Request $request){
        $post = $request->all();

        $where = [
            'customer_id' => $request->user()->customer_id,
        ];
        if(!empty($post['notification_id'])){
            $where['id'] = $post['notification_id'];
        }

        $data = [
            'opened' => 1
        ];

        $result = General::updateGeneralNotification($data, $where);

        if($result > 0){
            $response = [
                'success'=> true,
                'message' => 'Updated successfully'
            ];
        }
        else{
            $response = [
                'success'=> false,
                'message' => 'Failed to update'
            ];
        }

        return response()->json($response, Response::HTTP_OK);
    }
    public static function getModel(Request $request){
        $id                = $request->maker_id;
        $year              = $request->year;
        $dataShippingCars  = CarSellService::getModelShippingCars($id, $year);
        $dataCarsForSell   = CarSellService::getModelCarsForSell($id, $year);

        if (sizeof($dataShippingCars) > sizeof($dataCarsForSell)) {
            $array         = $dataShippingCars;
            $array1        = $dataCarsForSell;
        } else {
            $array         = $dataCarsForSell;
            $array1        = $dataShippingCars;
        }

        foreach ($array as $key => $subArray) {
            foreach ($array1 as $key1 => $value) {
                if ($subArray->id_car_model == $value->id_car_model) {
                    $array[$key]->total += $value->total;
                    unset($array1[$key1]);
                }
            }
        }
        $final_array    = array_merge($array, $array1);
        $output         = array(
            "data"      => $final_array
        );
        return response()->json($output, Response::HTTP_OK);
    }

    public static function getMaker(Request $request){
        $year               = $request->year;
        $dataShippingCars   = CarSellService::getMakerShippingCars($year);
        $dataCarsForSell    = CarSellService::getMakerCarsForSell($year);
        if (sizeof($dataShippingCars) > sizeof($dataCarsForSell)) {
            $array          = $dataShippingCars;
            $array1         = $dataCarsForSell;
        } else {
            $array          = $dataCarsForSell;
            $array1         = $dataShippingCars;
        }

        foreach ($array as $key => $subArray) {
            foreach ($array1 as $key1 => $value) {
                if ($subArray->id_car_make == $value->id_car_make) {
                    $array[$key]->total += $value->total;
                    unset($array1[$key1]);
                }
            }
        }
        $final_array        = array_merge($array, $array1);
        $output             = array (
            "data"          => $final_array
        );
        return response()->json($output, Response::HTTP_OK);
    }

    public static function getYear(Request $request){
        $array              = [];
        $currently_selected = date('Y');
        $earliest_year      = 1950;
        $latest_year        = date('Y');
        foreach (range($latest_year, $earliest_year) as $i) {
            $array        []= $i;
        }
        $output             = array (
            "data"          => $array
        );
        return response()->json($output, Response::HTTP_OK);
    }

    public static function getColors(Request $request){
        $output = [
            'data' => GeneralService::getColors(),
        ];
        return response()->json($output, Response::HTTP_OK);
    }

    public static function getVehicleTypes(Request $request){
        $output = [
            'data' => GeneralService::getVehicleTypes(),
        ];
        return response()->json($output, Response::HTTP_OK);
    }

    public static function getMakerAll(Request $request){
        $output = [
            'data' => GeneralService::getMakerAll(),
        ];
        return response()->json($output, Response::HTTP_OK);
    }

    public static function getModelAll(Request $request){
        $args = $request->all();

        $output = [
            'data' => GeneralService::getModelAll($args),
        ];
        return response()->json($output, Response::HTTP_OK);
    }

    public function sendMsg(Request $request){
        $msg     = trim($request->message);
        $customer_id = !empty($request->customer_id) ? $request->customer_id : $request->user()->customer_id;
        $subject   = trim($request->subject);
        $lot_vin   = trim($request->lot_vin);
        $complaint_type   = trim($request->complaint_type);
        $complaint_file   = trim($request->complaint_file);
        $images = $request->input('images', []);
        if(!empty($images)){
            foreach ($images as $image) {
                $fileContent = $image['fileContent'];
                $fileName = $image['name'];
                $file_type = $image['type'];
                $extension = $image['extension'];
                $fileName = Str::random(40) . '.' . $extension;
                $destinationPath = "upload/complaints/$fileName";
                $s3DestinationPath = $destinationPath;
                Storage::disk('local')->put($destinationPath, base64_decode($fileContent));
                if(\Maestroerror\HeicToJpg::isHeic(storage_path("app/$destinationPath"))){
                    $newFileName = explode('.', $destinationPath)[0];
                    $file_type = "image/jpeg";
                    $destinationPathJPG = "$newFileName.jpg";
                    \Maestroerror\HeicToJpg::convert(storage_path("app/$destinationPath"))->saveAs(storage_path("app/$destinationPathJPG"));
                    //unlink(storage_path("app/$destinationPath"));
                    $destinationPath = $s3DestinationPath = $destinationPathJPG;

                }
                $destinationPath = storage_path('app/'.$destinationPath);
                $s3FilePath = Helpers::uploadToS3($destinationPath, $s3DestinationPath, ['file_type' => $file_type]);
                if ($s3FilePath) {
                    unlink($destinationPath);
                    $complaint_file = explode('.', $s3FilePath)[0];
                }
            }
        }

        $data = array(
            'customer_id'   => $customer_id,
            'title'         => $subject,
            'lot_vin'       => $lot_vin,
            'complaint_type'=> $complaint_type,
            'complaint_file'=> $complaint_file,
            'message'       => $msg,
            'show_to_customer'=> '1',
        );

        $existingComplaint = General::checkIfChatOpen($customer_id, $lot_vin);
        if($existingComplaint){
            $msg_id = $existingComplaint->complaint_message_id;
        }
        else{
            $msg_id  = General::addContactMessage($data);
        }


        if($msg_id){
            $complaint_no = str_pad($msg_id, 6, '0', STR_PAD_LEFT);
            $customer = Helpers::get_customer_data($customer_id);
            $subject = "New complaint registered successfully - Tracking No: $complaint_no";

            if($customer_id){
                Helpers::send_notification_service_customer(array(
                    'customers'=>$customer_id,
                    'subject' => $subject,
                    'subject_ar' => "تم تسجيل شكوى جديدة بنجاح - رقم التتبع: $complaint_no",
                    'notification_text' => "Complaint for lot # $lot_vin has been registered successfully. Please use tracking #$complaint_no for further follow-ups.",
                    'notification_text_ar' => "تم تسجيل شكوى للحزمة رقم $lot_vin بنجاح. يرجى استخدام رقم التتبع $complaint_no للمتابعة اللاحقة.",
                ));
            }
            // return "SDFSDf";

            // notify customer service
            $this->notifyCustomerService($msg_id);

            $data['name'] = $customer->full_name;
            $data['tracking_no'] = $complaint_no;
            // Mail::send('emails.complaint',  $data , function($message) use ($customer, $subject){
            //     $message->from(Constants::COMPLAINT['FROM_EMAIL'], 'NAJ Call Center');
            //     $message->to($customer->primary_email)->subject($subject);
            // });
             if ($complaint_type == 40) {
                 // insert into complaint_messages_chat
                 $data = array(
                     'complaint_message_id'   => $msg_id,
                     'message'                => $msg,
                     'attachment'             => $complaint_file,
                     'source'                 => 1, // 1 for customer and 2 for customer service
                 );
                 General::addChatMessage($data);
            }

            $output = array(
                'success'=> true,
                'complaint_no' => $complaint_no,
                'message_id'=>$msg_id,
                'message' => 'Sent successfully'
            );
        }else {
            $output = array(
                'success'=> false,
                'message' => 'Failed to update'
            );
        }
        return response()->json($output, Response::HTTP_OK);
    }
    public function sendChatMessage(Request $request){
        $msg     = trim($request->message);
        $complaint_message_id = trim($request->complaint_message_id);
        $source = trim($request->source);
        $attachment = $request->input('attachment', null);
        $audio_files = $request->input('audio_files', null);

        if (!empty($attachment) && is_array($attachment)) {
            $fileContent = $attachment['fileContent'];
            $fileName = $attachment['name'];
            $file_type = $attachment['type'];
            $extension = $attachment['extension'];
            $fileName = Str::random(40) . '.' . $extension;
            $destinationPath = "upload/complaints/$fileName";
            $s3DestinationPath = $destinationPath;

            Storage::disk('local')->put($destinationPath, base64_decode($fileContent));

            if (\Maestroerror\HeicToJpg::isHeic(storage_path("app/$destinationPath"))) {
                $newFileName = explode('.', $destinationPath)[0];
                $file_type = "image/jpeg";
                $destinationPathJPG = "$newFileName.jpg";
                \Maestroerror\HeicToJpg::convert(storage_path("app/$destinationPath"))->saveAs(storage_path("app/$destinationPathJPG"));
                // unlink(storage_path("app/$destinationPath"));
                $destinationPath = $s3DestinationPath = $destinationPathJPG;
            }

            $destinationPath = storage_path('app/' . $destinationPath);
            $s3FilePath = Helpers::uploadToS3($destinationPath, $s3DestinationPath, ['file_type' => $file_type]);

            if ($s3FilePath) {
                unlink($destinationPath);
                $attachment = basename($s3FilePath);
                //$attachment = explode('.', $s3FilePath)[0];
            } else {
                // If S3 upload fails, log an error and return a response
                Log::error("Failed to upload file to S3.");
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload file to S3.'
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        if (!empty($audio_files) && is_array($audio_files)) {
            $fileContent = $audio_files['fileContent'];
            $fileName = $audio_files['name'];
            $audio_fileFileName = $audio_file['name'];
            $file_type = $audio_files['type'];
            $extension = $audio_files['extension'];
            $fileName = Str::random(40) . '.' . $extension;
            $destinationPath = "upload/complaints/audio_$fileName";
            $s3DestinationPath = $destinationPath;

            Storage::disk('local')->put($destinationPath, base64_decode($fileContent));

            $destinationPath = storage_path('app/' . $destinationPath);
            $s3FilePath = Helpers::uploadToS3($destinationPath, $s3DestinationPath, ['file_type' => $file_type]);

            if ($s3FilePath) {
                unlink($destinationPath);
                $audio_files = basename($s3FilePath);
            } else {
                // If S3 upload fails, log an error and return a response
                Log::error("Failed to upload file to S3.");
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload file to S3.'
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $data = array(
            'complaint_message_id'   => $complaint_message_id,
            'message'                => $msg,
            'attachment'             => $attachment,
            'voice_message'          => $audio_files,
            'source'                 => $source,
        );
        $message  = General::addChatMessage($data);
        if($message){
            $this->notifyCustomerService($complaint_message_id);
            $output = array(
                'success'=> true,
                'message' => 'Sent successfully'
            );
        }else {
            $output = array(
                'success'=> false,
                'message' => 'Failed to update'
            );
        }


        return response()->json($output, Response::HTTP_OK);
    }

    public function sendChatMessageMultiImages(Request $request){
        $msg     = trim($request->message);
        $complaint_message_id = trim($request->complaint_message_id);
        $source = trim($request->source);
        $attachment_status = $request->input('attachment_status', null);
        $attachments = $request->input('attachments', []);
        $mediaType="";
        $data = [
            'complaint_message_id' => $complaint_message_id,
            'message' => $msg,
            'attachment' => $attachment_status,
            'source' => $source,
            'video' => $attachments[0]['media']? $attachments[0]['media']:0,
        ];
        $message  = General::addChatMessage($data);
        if($message){
        $chatId=$message;
        //  ADD into S3 and DB
        if (!empty($attachments) && is_array($attachments)) {
            foreach ($attachments as $attachment) {
                $mediaType=$attachment['media'];
                $fileContent = $attachment['fileContent'];
                $fileName = $attachment['name'];
                $file_type = $attachment['type'];
                $extension = $attachment['extension'];
                $fileName = Str::random(40) . '.' . $extension;
                $destinationPath = "upload/complaints/$fileName";
                $s3DestinationPath = $destinationPath;
                $localPath = storage_path("app/$destinationPath");

                Storage::disk('local')->put($destinationPath, base64_decode($fileContent));
                if (\Maestroerror\HeicToJpg::isHeic(storage_path("app/$destinationPath"))) {
                    $newFileName = explode('.', $destinationPath)[0];
                    $file_type = "image/jpeg";
                    $destinationPathJPG = "$newFileName.jpg";
                    \Maestroerror\HeicToJpg::convert(storage_path("app/$destinationPath"))->saveAs(storage_path("app/$destinationPathJPG"));
                    $destinationPath = $s3DestinationPath = $destinationPathJPG;
                }

               
                // Handle M4A or MP4 conversion
                // if (in_array($extension, ['m4a', 'mp4'])) {
                //     $mp3FilePath = storage_path('app/public/' . Str::random(40) . '.mp3'); // Unique name for MP3
                //     try {
                //         $ffmpeg = FFMpeg::create();
                //         $audio = $ffmpeg->open($localPath);
                //         $audioFormat = new Mp3();
                //         $bitRate='56k';
                //         $audioFormat->setAudioKiloBitrate($bitRate);
                //         $audio->save($audioFormat, $mp3FilePath);

                //         // Update S3 destination path for MP3
                //         $s3DestinationPath = str_replace($extension, 'mp3', $destinationPath);
                //         $destinationPath = $mp3FilePath;
                //     } catch (\Exception $e) {
                //         // Log::error("Error during MP3 conversion: " . $e->getMessage());
                //         return response()->json([
                //             'success' => false,
                //             'message' => 'Error during MP3 conversion.'
                //         ], Response::HTTP_INTERNAL_SERVER_ERROR);
                //     }
                // } 
                // else {
                //     $destinationPath = storage_path('app/' . $destinationPath);
                // }
                $destinationPath = storage_path('app/' . $destinationPath);
                // return response()->json($s3DestinationPath);
                //$destinationPath = storage_path('app/' . $destinationPath);
                $s3FilePath = Helpers::uploadToS3($destinationPath, $s3DestinationPath, ['file_type' => $file_type]);
                if ($s3FilePath) {
                    unlink($destinationPath);
                    // Insert attachment URL into general_files table
                    $fileData = [
                        'file_name' =>  basename($s3DestinationPath),
                        'table_id' => 4,
                        'primary_column' => $chatId,
                        'tag' => 'complaint_messages_chat',
                        'create_by' => 1,
                    ];
                    DB::table('general_files')->insert($fileData);
                } else {
                    Log::error("Failed to upload file to S3.");
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to upload file to S3.'
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            }}
            $this->notifyCustomerService($complaint_message_id);
            $output = array(
                'success'=> true,
                'message' => 'Sent successfully'
            );
        }else {
            $output = array(
                'success'=> false,
                'message' => 'Failed to update'
            );
        }
        return response()->json($output, Response::HTTP_OK);
    }

    public function getMsgChatMultiImages(Request $request)
    {
        $args = $request->all();
        $queryMessageChat = General::getChatMessagesForComplain($args);
        $complaintMessage = [];
        foreach ($queryMessageChat as $dbRow) {
            $dbRow->complaint_no = str_pad($dbRow->complaint_message_id, 6, 0, STR_PAD_LEFT);
            $dbRow->attachment_full_path =   $dbRow->attachment ? Constants::NEJOUM_CDN . 'upload/complaints/'.$dbRow->attachment: 'no';
            $dbRow->readable_create_date = date('M j, Y, g:i a', strtotime($dbRow->created_at));
            $attachments_files = $dbRow->attachments_files ? explode(',', $dbRow->attachments_files) : [];
            $attachments_files_create_date = $dbRow->attachments_files_create_date ? explode(',', $dbRow->attachments_files_create_date) : [];
            // Combine file names and creation dates into an array of objects
            $dbRow->attachments_files = array_map(function($file, $created_at) {
                $fileType = pathinfo($file, PATHINFO_EXTENSION);
                return [
                    'file_name' => $file,
                    'file_type' => $fileType,
                    'file_path' => Constants::NEJOUM_CDN . 'upload/complaints/' . $file,
                    'created_at' => $created_at
                ];
            },
             $attachments_files, $attachments_files_create_date);
             // Filter conditions
        // if (
        //     $dbRow->message === "" &&
        //     empty($dbRow->attachments_files) &&
        //     ($dbRow->attachment === "1" && $dbRow->attachment_full_path === Constants::NEJOUM_CDN . 'upload/complaints/1') ||
        //     ($dbRow->attachment < "1" && strpos($dbRow->attachment_full_path, Constants::NEJOUM_CDN . 'upload/complaints/') === 0)
        // ) {
        //     continue; // Skip this record if it meets the conditions
        // }
             $dataRow = $dbRow;
            $complaintMessage[] = $dataRow;
        }
        $data = [
            'data' => $complaintMessage,
            'totalRecords' => count($complaintMessage)
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function getMsgChat(Request $request){
        $args = $request->all();
        $queryMessageChat = General::getChatMessagesForComplain($args);
        $complaintMessage = [];
        foreach ($queryMessageChat as $dbRow) {
            $dbRow->complaint_no = str_pad($dbRow->complaint_message_id, 6, 0, STR_PAD_LEFT);
            $dbRow->readable_create_date = date('M j, Y, g:i a', strtotime($dbRow->created_at));
            $dbRow->attachment_full_path =   $dbRow->attachment ? Constants::NEJOUM_CDN . 'upload/complaints/'.$dbRow->attachment: 'no';
            $dbRow->audio_files_full_path =   $dbRow->voice_message ? Constants::NEJOUM_CDN . 'upload/complaints/audio_'.$dbRow->voice_message: 'no';
            $dataRow = $dbRow;
            $complaintMessage[] = $dataRow;
        }
        $data = [
            'data' => $complaintMessage,
            'totalRecords' => count($complaintMessage)
        ];
        return response()->json($data, Response::HTTP_OK);
    }


    public function complaintEmailTest(){
        $data['name'] = 'Test name';
        $data['tracking_no'] = '0001';
        // Mail::send('emails.complaint',  $data , function($message){
        //     $message->from(Constants::COMPLAINT['FROM_EMAIL'], 'NAJ Call Center');
        //     $message->to('wajid@naj.ae')->subject('Test complaint Email');
        // });
    }

    public function complaintMessageDetails(Request $request){
        $args = $request->all();
        $complaintMessages = General::getComplaintMessageDetails($args);
        $complaintMessage = [];
        foreach ($complaintMessages as $dbRow) {
            $dataRow = $dbRow;
            $complaintMessage[] = $dataRow;
        }
        $data = [
            'data' => $complaintMessage,
            'totalRecords' => General::getComplaintMessageDetailsCount($args)
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function getAuctionLocations(Request $request){
        $args = $request->all();
        $auctionLocations = General::getAuctionLocations($args);

        $output = array(
            "data"=> $auctionLocations,
            'totalRecords' => General::getAuctionLocationsCount($args)
        );
        return response()->json($output, Response::HTTP_OK);
    }

    public function getCountryPorts(Request $request){
        $args = $request->all();
        $portsData = General::getCountryPorts($args);

        $countryPorts = [];
        foreach($portsData as $row){
            $countryPorts[] = [
                'port_id' => $row->port_id,
                'port_name' => $row->port_name,
                'port_name_ar' => $row->port_name_ar,
            ];
        }

        $output = array(
            "data"=> $countryPorts,
            'totalRecords' => General::getCountryPortsCount($args)
        );
        return response()->json($output, Response::HTTP_OK);
    }

    public function getAboutUs(){
        $about = DB::table('html_posts')
        ->where('slug', '=', 'about-company')
        ->first();

        $lang = app()->getLocale();

        $aboutText = [
            'title' => $about->{"title_".$lang},
            'body' => $about->{"html_".$lang},
        ];

        $output = array(
            "data"=> $aboutText,
        );
        return response()->json($output, Response::HTTP_OK,
        ['Content-Type' => 'application/json;charset=UTF-8', 'Charset' => 'utf-8'], JSON_UNESCAPED_UNICODE);
    }

    public function getAboutUsAR(){
        app()->setLocale('ar');
        return $this->getAboutUs();
    }

    public function contact_us(Request $request){
        $name           = $request->name;
        $email          = $request->email;
        $phone          = $request->phone;
        $msg            = $request->message;
        $data           = array();
        $subject  = 'New Message from visitor';
        $data           = array(
            'complaint_source' => '1',
            'complaint_type' => '1',
            'show_to_customer' => '1',
            'name'    => $name,
            'email'   =>  $email,
            'phone'   =>  $phone,
            'message_' =>  $msg,
            'title' =>  $subject,
            'create_date' => Helpers::get_db_timestamp(),
        );
        $msg_id  = '';
        if($data) {
            $msg_id  = 1;//General::addContactMessage($data);
            //$complaint_no = str_pad($msg_id, 6, '0', STR_PAD_LEFT);
            //$subject = "New complaint registered successfully - Tracking No: $complaint_no";

            $data['name'] = $name;
            //$data['tracking_no'] = $complaint_no;
            // Mail::send('emails.complaint',  $data , function($message) use ($email, $subject){
            //     $message->from(Constants::COMPLAINT['FROM_EMAIL']);
            //     $message->to($email)->subject($subject);
            // });

            Mail::send('emails.visitor',  $data , function($message) use ($subject){
                     $message->from(Constants::COMPLAINT['FROM_EMAIL']);
                     $message->to(Constants::COMPLAINT['FROM_EMAIL'])->subject($subject);
            });
        }
        if($msg_id){
            $output = array(
                'success'=> true,
                'message' => 'Send successfully'
            );
        }else {
            $output = array(
                'success'=> false,
                'message' => 'Failed to update'
            );
        }
        return response()->json($output, Response::HTTP_OK);
}

    public function contact_us_marketing(Request $request){
        $name           = $request->name;
        $phone          = $request->phone;
        $from            = $request->from;
        $notes            = $request->notes;
        $data           = array();
        $subject  = 'New Message from visitors';
        $data           = array(
            'name'    => $name,
            'number'   =>  $phone,
            'country'    =>  $from,
            'notes'    =>  $notes,
        );
        // dd($data);
        $msg_id  = '';
        if($data) {
            // $msg_id  = GeneralService::addContactUsMessage($data);
            //  Send mail to Application Admin
            Mail::send('emails.marketing',  $data , function($message) use ($request){
            $message->from(env('MAIL_USERNAME'));
            $message->to(env('MAIL_MARKETING'), 'Marketing')->subject('Marketing Email From Web Form');

        });
        GeneralService::addMarketingMessage($data);

        $output = array(
            'success'=> true,
            'message' => 'Send successfully'
        );

        }

        return response()->json($output, Response::HTTP_OK);
}

    public function saveFeedback(Request $request){

        try{
            $url_id = DB::table('customers_feedback_token')->where(['token' => $request->token])->get()->first()->id;

            $data = [
                'question_1' =>  $request->question_1,
                'question_2' =>  $request->question_2,
                'comment' =>  $request->comment,
                'feedback_token_id' =>  $url_id,
                'create_date' => Helpers::get_db_timestamp(),
            ];

            $id = GeneralService::saveFeedback($data);
            $output = array(
                'success'=> true,
                'message' => 'Saved successfully'
            );

        } catch(\Exception $e){
            $output = array(
                'success'=> false,
                'message' => $e->getMessage()
            );
        }

        return response()->json($output, Response::HTTP_OK);
    }

    public function validateFeedback(Request $request){
        $token = $request->token;

        try{
            $data = [
                'data' => GeneralService::getValidFeedbackData($token)
            ];
        } catch(\Exception $e){
            $data = array(
                'success'=> false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($data, Response::HTTP_OK);

    }

    public function getVehicleType(){
        $res = GeneralService::getVehicleType();
        $data = [
            'data' => $res
        ];
        return response()->json($data, Response::HTTP_OK);

    }

    public function getAuction(){
        $res = GeneralService::getAuction();
        $data = [
            'data' => $res
        ];
        return response()->json($data, Response::HTTP_OK);

    }
    public function getAuctionLocation(Request $request){
        $args = $request->all();
        $res = GeneralService::getAuctionLocation($args);
        $data = [
            'data' => $res
        ];
        return response()->json($data, Response::HTTP_OK);

    }

    public function getCountries(){
        $res = GeneralService::getCountries();
        $data = [
            'data' => $res
        ];
        return response()->json($data, Response::HTTP_OK);

    }

    public function getAuctionsRegions(){
        $params = ['country_id' => [38, 231]];
        $res = GeneralService::getRegions($params);
        $data = [
            'data' => $res
        ];
        return response()->json($data, Response::HTTP_OK);

    }

    public function getStates(Request $request){
        $params = $request->all();
        $res = GeneralService::getStates($params);
        $data = [
            'data' => $res
        ];
        return response()->json($data, Response::HTTP_OK);

    }

    public function getCities(Request $request){
        $params = $request->all();
        $res = GeneralService::getCities($params);
        $data = [
            'data' => $res
        ];
        return response()->json($data, Response::HTTP_OK);

    }

    public function getBankAccount(){
        $params = ['ID' => [1071, 1073, 2433, 4972]];
        $res = GeneralService::getBankAccounts($params);
        $data = [
            'data' => $res
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function getTypeofReceiver(){
        $res = ['vcc', 'car'];
        $data = [
            'data' => $res
        ];
        return response()->json($data, Response::HTTP_OK);
    }


    public function setCustomerToken(Request $request){
        $post = $request->all();

        $data = [
            'user_token' => $post['token'],
            'customer_id' => $request->user()->customer_id,
        ];

        $result = General::updateCustomerToken($data);

        if($result > 0){
            $response = [
                'success'=> true,
                'message' => 'Updated successfully'
            ];
        }
        else{
            $response = [
                'success'=> false,
                'message' => 'Failed to update'
            ];
        }

        return response()->json($response, Response::HTTP_OK);
    }

    public function getRegionAuctionLocations(Request $request){
        $args = $request->all();
        $auctionLocations = GeneralService::getRegionAuctionLocations($args);

        $data = [
            'data'          => $auctionLocations,
            'totalRecords'  => count($auctionLocations)
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function getTowingCases(Request $request){
        $args = $request->all();
        $data = GeneralService::getTowingCases($args['customer_id']);

        $data = [
            'data'          => $data,
            'totalRecords'  => count($data)
        ];
        return response()->json($data, Response::HTTP_OK);
    }


    // Authorized Receiver
    public function uploadIDReceiver(Request $request){
        $customer_id = $request->customer_id;
        $file = $request->image;
        $fileContent = $request->fileContent;
        $fileName = $request->name;
        $file_type = $request->file_type;
        $extension = $request->extension;
        $cu_notes = $request->cu_notes;
        $auth_name = $request->auth_name;
        $phone = $request->phone;
        $auth_id = $request->auth_id;
        $type = $request->val_to;
        $cars = $request->cars;
        $result = 0;
        $data = [];
        if(!empty($file)){
            $ext = $extension;
            $fileName = $fileName."$ext";
            $destinationPath = "uploads/authorized_receiver/$fileName";
            $s3DestinationPath = $destinationPath;
            Storage::disk('local')->put($destinationPath, base64_decode($fileContent));
            $destinationPath = storage_path('app/'.$destinationPath);
            $s3FilePath = Helpers::uploadToS3($destinationPath, $s3DestinationPath, ['file_type' => $file_type]);
            if($s3FilePath){
                unlink($destinationPath);
                $data['file'] = $s3DestinationPath;
                $data['customer_id'] = $customer_id;
                $data['emirates_id'] = $auth_id;
                $data['name'] = $auth_name;
                $data['notes'] = $cu_notes;
                $data['phone'] = $phone;
                $data['type'] = $type;
                $data['cars'] = json_encode($cars);
                $result = GeneralService::saveAuthorizedReceiver($data, $customer_id);
            }
        }
        $data = [
            'data'=> $result,
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function deleteAuthorizedReceiver(Request $request){
        $request_id = $request->id;
        $deleted = GeneralService::deleteAuthorizedReceiver($request_id);
        if($deleted){
            $data = [
                'success'=> true,
                'message' => 'Deleted successfully'
            ];
        }else{
            $data = [
                'success'=> false,
                'message' => 'Unable to Delete'
            ];
        }

        return response()->json($data, Response::HTTP_OK);
    }

    public function activateAuthorizedReceiver(Request $request){
        $request_id = $request->id;
        $deleted = GeneralService::activateAuthorizedReceiver($request_id);
        if($deleted){
            $data = [
                'success'=> true,
                'message' => 'Activated successfully'
            ];
        }else{
            $data = [
                'success'=> false,
                'message' => 'Unable to Update'
            ];
        }

        return response()->json($data, Response::HTTP_OK);
    }

    public function deactivateAuthorizedReceiver(Request $request){
        $request_id = $request->id;
        $deleted = GeneralService::deactivateAuthorizedReceiver($request_id);
        if($deleted){
            $data = [
                'success'=> true,
                'message' => 'Deactivated successfully'
            ];
        }else{
            $data = [
                'success'=> false,
                'message' => 'Unable to Update'
            ];
        }

        return response()->json($data, Response::HTTP_OK);
    }


    public function getAuthorizedReceiverDetails(Request $request){
        $payment_id = $request->payment_id;
        $data = [];
        if($payment_id){
            $data = GeneralService::getAuthorizedReceiverDetails($payment_id);
        }
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function getAllAuthorizedReceiver(Request $request){
        $customer_id = $request->customer_id;
        $data = [];

        if($customer_id){
            $data = GeneralService::getAllAuthorizedReceiver($customer_id);

            if(count($data) > 0){
                foreach ($data as $key => $item) {
                    if($item->cars){
                        $vins = GeneralService::getAllvinsFromid($item->cars);
                        $data[$key]->vins = $vins;
                    }
                    else {
                        $data[$key]->vins = [];
                    }
                }
            }
        }

        $responseData = [
            'data' => $data,
        ];

        return response()->json($responseData, Response::HTTP_OK);
    }


    public function getnonDelivered(Request $request){
        $args['customer_id'] = $request->customer_id;
        $args['limit'] = PHP_INT_MAX;
        $args['page'] = 0;
        $data = [];
        $data = GeneralService::getnonDelivered($args);
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function getnonVcc(Request $request){
        $customer_id = $request->customer_id;
        $data = [];
        if($customer_id){
            $data = GeneralService::getnonVcc($customer_id);
        }
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function getBuyerAcc(Request $request){
        $customer_id = $request->customer_id;
        $data = [];
        if($customer_id){
            $data = GeneralService::getBuyerAcc($customer_id);
        }
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function getSpecialRequest(Request $request){
        $customer_id = $request->customer_id;
        $data = [];
        if($customer_id){
            $data = GeneralService::getSpecialRequest($customer_id);
        }
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function getAllSpecialRequest(Request $request){
        $customer_id = $request->customer_id;
        $data = [];
        if($customer_id){
            $data = GeneralService::getAllSpecialRequest();
        }
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function addLoadingRequest(Request $request){
        $customer_id = $request->customer_id;
        $services = $request->services;
        $services = json_decode($services);
        $car_id = $request->car_id;
        $data = [];
        if(count($services) > 0){
            $service_data = GeneralService::getServiceData($services[0]);
            if($service_data){
                $data['car_id'] = $car_id;
                $data['loading_special_request_id'] = $services[0];
                $data['amount_cost'] = $service_data->amount_cost;
                $data['amount_revenue'] = $service_data->amount_revenue;
                $data['confirmed'] = 3;
            }
            if($data){
                $data = GeneralService::addLoadingRequest($data);
            }
        }
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }


    public function deleteLoadingRequest(Request $request){
        $request_id = $request->id;
        $deleted = GeneralService::deleteLoadingRequest($request_id);
        if($deleted){
            $data = [
                'success'=> true,
                'message' => 'Deleted successfully'
            ];
        }else{
            $data = [
                'success'=> false,
                'message' => 'Unable to Delete'
            ];
        }

        return response()->json($data, Response::HTTP_OK);
    }

    public function getAllExchangeCompanies(Request $request){
        $data = [];
        $data = GeneralService::getExchangeCompanies();
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    private function convertHEICToJpgUsingImagick($url = Constants::NEJOUM_CDN .'uploads/transfer_payment/IMG_1655.HEIC.jpg', $file_type) {
        try {
            // Check the original filename for the .HEIC or .heic extension after removing trailing .jpg
            $fileName = basename($url);
            $modifiedFilename = preg_replace('/\.jpg$/i', '', $fileName);
            $pathInfo = pathinfo($modifiedFilename);

            // If the filename is not a .HEIC or .heic, exit early
            if (!isset($pathInfo['extension']) || strtolower($pathInfo['extension']) !== 'heic') {
                return false;  // Not a HEIC file
            }

            // Create a new Imagick object from the URL
            $image = new \Imagick($url);

            // Convert the image to JPG format
            $image->setImageFormat('jpg');

            // Destination paths
            $destinationPath = "uploads/transfer_payment/$fileName";
            $localDestinationPath = storage_path('app/'.$destinationPath);

            // Save the converted image locally using Laravel's Storage
            Storage::disk('local')->put($destinationPath, $image);

            // Upload the converted image to S3
            $s3DestinationPath = $destinationPath;
            $s3FilePath = Helpers::uploadToS3($localDestinationPath, $s3DestinationPath, ['file_type' => $file_type]);

            return $s3FilePath;  // Return the S3 path where the image was uploaded
        } catch (\Exception $e) {
            return false;  // Return false if any errors occur
        }
    }

    public function getLotNumbersDamage(Request $request){
        $customer_id = $request->customer_id;
        $data = [];
        if($customer_id){
            $data = GeneralService::getLotNumbersDamage($customer_id);
        }
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function getLotNumbersDamageInfo(Request $request){
        $car_id = $request->car_id;
        $data = [];
        if($car_id){
            $data = GeneralService::getLotNumbersDamageInfo($car_id);
        }
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }


    public function getCarInfo(Request $request){
        $car_id = $request->car_id;
        $data = [];
        if($car_id){
            $data = GeneralService::getCarInfo($car_id);
        }
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function getDamageParts(Request $request){
        $data = [];
        $data = GeneralService::getDamageParts();
        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);
    }


    public function uploadDamageRequest(Request $request){
        $postData = $request->all()['fields'];
        $customer_id = $request->input('customer_id');
        $images = $request->input('images', []);
        $s3_images = [];
        $car_id = $request->input('car_id');
        $w_images = $request->input('w_images', []);
        $s_images = $request->input('s_images', []);
        $selectedDamagePartIds = $request->input('selectedDamagePartIds', []);
        $cu_notes = $request->input('cu_notes');

        if($postData['source'] === 'web'){
            $customer_id = $postData['customer_id'];
            $images = [];
            $s3_images = $postData['s3_images'];
            $car_id = $postData['lotNumber'];
            $w_images = json_encode(explode(',', $postData['w_images']));
            $s_images = json_encode(explode(',', $postData['s_images']));
            $selectedDamagePartIds = json_encode(explode(',', $postData['damageParts']));
            $cu_notes = $postData['cu_notes'];
        }

        $result = 0;

        $data = [];
        $data['customer_id']                = $customer_id;
        $data['car_id']                     = $car_id;
        $data['s_images']                   = $s_images;
        $data['w_images']                   = $w_images;
        $data['selectedDamagePartIds']      = $selectedDamagePartIds;
        $data['notes']                      = $cu_notes;
        $data['created_by']                 = 0;

        $result = GeneralService::saveDamageRequest($data);

        if($result){
            foreach ($images as $image) {
                $fileContent = $image['fileContent'];
                $fileName = $image['name'];
                $file_type = $image['type'];
                $extension = $image['extension'];
                $fileName = $fileName;
                $destinationPath = "upload/car_images/damaged_car/$fileName";
                $s3DestinationPath = $destinationPath;
                Storage::disk('local')->put($destinationPath, base64_decode($fileContent));
                if(\Maestroerror\HeicToJpg::isHeic(storage_path("app/$destinationPath"))){
                    $newFileName = explode('.', $destinationPath)[0];
                    $file_type = "image/jpeg";
                    $destinationPathJPG = "$newFileName.jpg";
                    \Maestroerror\HeicToJpg::convert(storage_path("app/$destinationPath"))->saveAs(storage_path("app/$destinationPathJPG"));
                    //unlink(storage_path("app/$destinationPath"));
                    $destinationPath = $s3DestinationPath = $destinationPathJPG;
                }
                $destinationPath = storage_path('app/'.$destinationPath);
                $s3FilePath = Helpers::uploadToS3($destinationPath, $s3DestinationPath, ['file_type' => $file_type]);
                if ($s3FilePath) {
                    unlink($destinationPath);
                    $imageData []= array(
                        'photo_name'         => $fileName,
                        'car_id'          => $car_id,
                        'type'            => '2',
                        'create_by'       => 1,
                        'visible'        => 1
                    );
                }
            }
            foreach ($s3_images as $fileName) {
                $imageData []= array(
                    'photo_name'         => $fileName,
                    'car_id'          => $car_id,
                    'type'            => '2',
                    'create_by'       => 1,
                    'visible'        => 1
                );
            }

            if(!empty($imageData)){
                $query1 = DB::table('damaged_car_photo')->insert($imageData);
            }
        }
        return response()->json($result, Response::HTTP_OK);
    }

    public function uploadServiceRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'max:255',
            'phone' => 'max:255',
            'email' => 'max:255',
            'customer_id' => 'max:255',
            'region' => 'max:255',
            'license_no' => 'required|max:255',
            'paymentIntentId' => 'required',
            'documents' => 'required|array',
            'documents.*.name' => 'required|string|max:255',
            'documents.*.fileContent' => 'required|string',
            'documents.*.type' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        DB::beginTransaction();

        try {
            // Fetch Existing Customer email if customer_id is provided
            $email = null;
            $name = null;

            if ($request->input('customer_id')) {
                $user = DB::table('customer')
                    ->where('customer_id', $request->input('customer_id'))
                    ->first();

                if ($user) {
                    $email = $user->primary_email;
                    $name = $user->full_name;
                }
            } else {
                $email = $request->input('email');
                $name = $request->input('name');
            }

            // Create AppTrafficRequest
            $appTrafficRequest = AppTrafficRequest::create([
                'customer_name' => $request->input('name') ?? '',
                'customer_id' => $request->input('customer_id') ?? 0,
                'service_type' => $request->input('service_id'),
                'region' => $request->input('region'),
                'phone' => $request->input('phone'),
                'licence_number' => $request->input('license_no'),
                'traffic_code' => '',
                'operation_number' => '',
                'vin' => '',
                'status' => 0,
                'traffic_charge' => $request->input('service_price'),
            ]);

            // Create AppTrafficRequestPayments
            $paymentIntentId = $request->input('paymentIntentId');
            $paymentController = app(PaymentController::class);
            $paymentIntent_status = $paymentController->checkPaymentStatus($paymentIntentId);

            $payment = AppTrafficRequestPayment::create([
                'request_id' => $appTrafficRequest->id,
                'amount' => $request->input('service_price'),
                'status' => $paymentIntent_status == "succeeded" ? 1 : 0,
            ]);

            // Upload and save documents
            foreach ($request->input('documents') as $document) {
                $fileContent = $document['fileContent'];
                $fileName = $document['name'];
                $fileType = $document['type'];
                $filePath = "upload/service_request_documents/$fileName";
                $destinationPath = "upload/registration/$fileName";
                $s3DestinationPath = $destinationPath;

                // Save file to storage
                Storage::disk('local')->put($destinationPath, base64_decode($fileContent));

                // Convert HEIC to JPG if necessary
                if (\Maestroerror\HeicToJpg::isHeic(storage_path("app/$destinationPath"))) {
                    $newFileName = explode('.', $destinationPath)[0];
                    $file_type = "image/jpeg";
                    $destinationPathJPG = "$newFileName.jpg";
                    \Maestroerror\HeicToJpg::convert(storage_path("app/$destinationPath"))->saveAs(storage_path("app/$destinationPathJPG"));
                    unlink(storage_path("app/$destinationPath"));
                    $destinationPath = $s3DestinationPath = $destinationPathJPG;
                }

                // Create GeneralFile associated with AppTrafficRequest
                $appTrafficRequest->generalFiles()->create([
                    'file_name' => $fileName,
                    'table_id' => 7, // Adjust as per your application logic
                    'primary_column' => $appTrafficRequest->id,
                    'tag' => 'app_traffic_request',
                    'create_by' => $request->input('customer_id') ?? 0,
                ]);
            }

            DB::commit();

            // Send email confirmation if payment was successful
            if ($paymentIntent_status == "succeeded") {
                $toEmail = $email;
                $data['amount'] = $request->input('service_price');
                $data['name'] = $name;
                $data['transaction_id'] = $paymentIntentId;

                Mail::send('emails.paymentConfirmation', $data, function ($message) use ($toEmail) {
                    $message->from(Constants::COMPLAINT['FROM_EMAIL'], 'NAJ Call Center');
                    $message->to($toEmail)->subject('Service Request Payment Received Confirmation');
                });
            }

            return response()->json(['message' => 'Service request created successfully'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create service request: ' . $e->getMessage()], 500);
        }
    }


    public function getAllAppTraficServices(){
        $data = [];

            $data = GeneralService::getAllAppTraficServices();

        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);

    }

    public function getAllAppTraficRegions(){
        $data = [];

            $data = GeneralService::getAllAppTraficRegions();

        $data = [
            'data'=> $data,
        ];
        return response()->json($data, Response::HTTP_OK);

    }

    public function getCustomerServiceRequest(Request $request){
        $customer_id = $request->customer_id;
        $licence_number = $request->licence_number;
        $data = GeneralService::getCustomerServiceRequest($customer_id, $licence_number);

    // Return the fetched data in the response
    return response()->json(['data' => $data], Response::HTTP_OK);
    }
    public function saveAppFeedback(Request $request)
    {
      // Validate the incoming request data
   $validatedData = Validator::make($request->all(), [
    'request_id' => 'required',
    'service_experience' => 'required',
    'overall_experience' => 'required',
]);

if ($validatedData->fails()) {
    return response()->json(['errors' => $validatedData->errors()], 400);
}

// Create a new instance of AppTraficFeedback model
DB::table('app_traffic_feedback')->insert([
    "request_id" => $request->request_id,
    "service_experience" => $request->service_experience,
    "overall_experience" => $request->overall_experience,
]);
        // Optionally, you can return a response indicating success
        return response()->json(['message' => "Data Inserted"], 200);
    }

    public function getAllDamageRequest(Request $request){
        $args                   = $request->all();
        $args['customer_id']    = $request->customer_id;
        $data = [
            'data' => GeneralService::getAllDamageRequest($args),
            'totalRecords'  => GeneralService::getDamageRequestCount($args)
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    function getComplaintTypes(){
        $data = GeneralService::getComplaintTypes();
        return response()->json($data, Response::HTTP_OK);
    }
    function getcomplaintMessageId(Request $request){
        $data = [
            'title' =>$request->input('title'),
            'lot_vin' =>$request->input('lot_vin'),
            'message' =>$request->input('message'),
            'customer_id' =>$request->input('customer_id'),
        ];
        $complaintMessageId = GeneralService::getComplaintMessageId($data['title'],$data['message'], $data['lot_vin'] ,$data['customer_id']);
        if (!$complaintMessageId) {
            return response()->json(['success' => false, 'message' => 'Complaint message not found'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['success' => true, 'complaint_message_id' => $complaintMessageId->complaint_message_id], Response::HTTP_OK);
    }

    private function notifyCustomerService(int $msg_id): void
    {
        $CALL_CENTER = Helpers::get_users_by_role(Constants::ROLES['CALL_CENTER']);
        $UAE_OPERATION_MANAGER = Helpers::get_users_by_role(Constants::ROLES['UAE_OPERATION_MANAGER']);
        $IT = Helpers::get_users_by_department(Constants::DEPARTMENTS['IT']);
        $users = array_merge($CALL_CENTER, $IT, $UAE_OPERATION_MANAGER);
        $users = array_column($users, 'id');
        Helpers::send_notification_service(array(
            'sender_id' => 1,
            'recipients_ids' => $users,
            'subject' => 'New Message from Customer',
            'subject_ar' => 'رسالة جديدة من العميل',
            'body' => "There is a new message from the customer " . "Please click on the notifications for review",
            'body_ar' => "هناك رسالة جديدة من العميل " . "يرجى النقر على الإشعارات للمراجعة",
            'priority' => Constants::NOTIFICATION_PRIORITY_MEDIUM,
            'url' => "Complaint/getChats?complaint_id=" . $msg_id,
            'type' => Constants::NOTIFICATION_ALERT_TYPE,
        ));
    }

}
