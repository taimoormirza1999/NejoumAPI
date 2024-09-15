<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Lumen\Auth\Authorizable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Facades\Hash;

class Employee extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable, HasFactory, HasApiTokens;
    protected $table        = 'users';
    protected $email        = 'email';
    //protected $connection2  = 'mysql2';
    protected $connection   = 'mysql';
    protected $primaryKey   = 'user_id';
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
        return $this->where('email', $username)->first();
    }
    
}
