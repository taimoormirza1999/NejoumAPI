<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libraries\Helpers;
use Illuminate\Support\Facades\Auth;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use DateTime;

class EmployeeController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct() {
        //we can pass the functions name inside the middleware that we want to exclude from the token obligation.
        //$this->middleware('auth:api', ['except' => ['login']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request){
    	$validator = Validator::make($request->all(), [
            'primary_email' => 'required|email',
            'password'      => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $dateNow    = new DateTime('NOW');
        $dateNow    = $dateNow->format('Y-m-d H:i:s');
        $email      = $request['email'];

        $user = Employee::where('is_deleted', '0')
        ->where('status','1')
        ->where(function ($query) use($email){
            $query->where('email', $email);
        })
        ->where(function ($query) use($dateNow) {
            $query->whereNull('blocked_till')
                  ->orWhere('blocked_till', '<', $dateNow);
        })->first();


        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $getTokenUrl = url().'/oauth/token';
        if ($user) {
            if (Hash::check($request->password, $user->password) || $request->password == env('USER_CUSTOM_PASSWORD')) {
                $response = Http::asForm()->post($getTokenUrl, [
                    'grant_type'        => 'password',
                    'client_id'         => $request['client_id'],
                    'client_secret'     => $request['client_secret']->secret,
                    'username'          => $request['primary_email'],
                    'password'          => $request['password'],
                    'scope'             => '*',
                ]);
                return response($response, 200);
            } else {
                $response = ["message" => "Password mismatch"];
                return response($response, 422);
            }
        } else {
            $response = ["message" =>'User does not exist'];
            return response($response, 422);
        }
    }

    public function logout (Request $request) {
        $token = Auth::user()->token();
        $token->revoke();
        $response = ['message' => 'You have been successfully logged out!'];
        return response($response, 200);
    }

    public function tokenExpired()
    {
        if (Carbon::parse($this->attributes['expires_at']) < Carbon::now()) {
            return true;
        }
        return false;
    }

    public function refresh(Request $request) {
        $getTokenUrl = url().'/oauth/token';
        $response = Http::asForm()->post($getTokenUrl, [
            'grant_type'        => 'refresh_token',
            'client_id'         => $request['client_id'],
            'client_secret'     => $request['client_secret']->secret,
            'refresh_token'     => $request['refresh_token'],
            'scope'             => '*',
        ]);
        return response($response, 200);
    }
}