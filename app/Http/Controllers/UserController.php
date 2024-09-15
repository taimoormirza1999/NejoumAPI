<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
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

    public function getCustomerLoggedInDevices(Request $request)
    {
        // Always validate incoming data. Never trust your users!
        /**$this->validate($request, [
            'name'      => 'required',
            'email'     => 'required|email|unique:authors',
            'location'  => 'required|alpha'
        ]);**/

        $customer_id = $request->get('apikey');
        $users = DB::Table('user_devices')
                    ->select('*')
                    ->where('deleted', '0')
                    ->where('user_devices.logged_in', '0')
                    ->where('user_devices.customer_id', $customer_id)
                    ->groupBy('user_id', 'Device_push_regid')
                    ->get();
        return response()->json($users);
    }

    public function checkCustomerLoggedin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_push_regid' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $args = $request->all();
        $args['customer_id'] = 36;  // get through client auth
        $status = User::checkCustomerLoggedin($args);

        $data = [
            'status' => !empty($status) ? true : false
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function logoutFromAll(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_push_regid' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $args = $request->all();
        $args['customer_id'] = 36;
        $status = User::logoutFromAll($args);

        $data = [
            'status' => !empty($status) ? true : false
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function customerLoggedInDevices()
    {
        $args['customer_id'] = 36;
        $devices = User::getCustomerLoggedInDevices($args);

        $loggedInDevices = [];
        foreach($devices as $row){
            $loggedInDevices[] = [
                'device_id' => $row->device_id,
                'device_brand' => $row->Device_brand,
                'device_model' => $row->Device_model,
                'device_os' => $row->Device_os,
                'device_appversion' => $row->Device_appversion,
                'device_platform' => $row->Device_platform,
                'device_push_regid' => $row->Device_push_regid,
                'device_lang' => $row->Device_lang,
                'logged_in' => $row->logged_in == 0 ? true : false,
            ];
        }

        $data = [
            'data' => $loggedInDevices
        ];
        return response()->json($data, Response::HTTP_OK);
    }

    public function changePassword(Request $request){
        $validator = Validator::make($request->all(), [
            'password' => 'required',
            'old_password' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $args = $request->all();
        $args['customer_id'] = $request->user()->customer_id;
        $user = User::find($args['customer_id']);

        if(Hash::check($args['old_password'], $user->password)){
            $user->password = Hash::make($args['password']);
            $user->save();

            User::logoutFromAll(['customer_id' => $user->customer_id, 'device_push_regid' => '']);

            $success = true;
            $message = "Updated successfully";
        }
        else{
            $success = false;
            $message = "Incorrect password";
        }

        $data = [
            'success' => $success,
            'message' => $message
        ];
        return response()->json($data, Response::HTTP_OK);
    }
}
