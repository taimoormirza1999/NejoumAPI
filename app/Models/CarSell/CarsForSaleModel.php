<?php

namespace App\Models\CarSell;

use App\Libraries\Helpers;
use Faker\Extension\Helper;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Lumen\Auth\Authorizable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Facades\DB;
use stdClass;

class CarsForSaleModel extends Model
{
    protected $table        = null;
    protected $primaryKey   = null;
}