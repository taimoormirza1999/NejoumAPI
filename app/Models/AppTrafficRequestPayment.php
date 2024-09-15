<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppTrafficRequestPayment extends Model
{  protected $table = 'app_traffic_request_payments';

    protected $fillable = ['request_id', 'date', 'amount', 'status'];
    public $timestamps = false;
    public function appTrafficRequest()
    {
        return $this->belongsTo(AppTrafficRequest::class, 'request_id');
    }
}
