<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppTrafficFeedback extends Model
{
    protected $table = 'app_traffic_feedback';

    protected $fillable = [
        'request_id',
        'service_id',
        'customer_id ',
        'guest_id',
        'service_experience',
        'overall_experience',
    ];

    public $timestamps = false;


}
