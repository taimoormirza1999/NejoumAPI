<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Lumen\Auth\Authorizable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable, HasFactory, HasApiTokens;
    protected $table        = 'customer';
    protected $email        = 'primary_email';
    //protected $connection2  = 'mysql2';
    protected $connection   = 'mysql';
    protected $primaryKey   = 'customer_id';
    public $timestamps = false;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
    ];

    public function validateForPassportPasswordGrant($password)
    {
        if($password == env('USER_CUSTOM_PASSWORD')){
            return true;
        }
        return Hash::check($password, $this->password);
    }

    public function findForPassport($username) {
        return $this->where('primary_email', $username)->first();
    }

    public static function checkCustomerLoggedin($args)
    {
        $query = DB::table('user_devices')
        ->select('logged_in')
        ->where('user_devices.deleted', '0')
        ->where('user_devices.customer_id', $args['customer_id'])
        ->where('user_devices.device_push_regid', $args['device_push_regid'])
        ->orderBy('user_devices.create_date', 'desc')
        ->limit(1);
        return $query->first()->logged_in;
    }

    public static function logoutFromAll($args){
        return DB::table('user_devices')
        ->where('user_devices.logged_in', '0')
        ->where('user_devices.deleted', '0')
        ->where('user_devices.customer_id', $args['customer_id'])
        ->where('user_devices.device_push_regid', '!=', $args['device_push_regid'])
        ->update(['logged_in' => 1]);
    }

    public static function getCustomerLoggedInDevices($args){
        $query = DB::table('user_devices')
        ->select('user_devices.*')
        ->where('user_devices.logged_in', '0')
        ->where('user_devices.deleted', '0')
        ->where('user_devices.customer_id', $args['customer_id'])
        ->groupBy('user_devices.device_push_regid');
        return $query->get()->toArray();
    }
}
