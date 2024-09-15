<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $table        = 'booking';
    protected $connection   = 'mysql';
    protected $primaryKey   = 'booking_id';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'shipping_instructions_cut_off_date',
    ];

    

    
}
