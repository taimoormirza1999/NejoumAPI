<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table        = 'countries';
    protected $connection   = 'mysql';
    protected $primaryKey   = 'id';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'shortname', 'name'
    ];

    

    
}
