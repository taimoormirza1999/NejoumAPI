<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppTrafficRequest extends Model
{  protected $table = 'app_traffic_request';

    protected $fillable = [
        'customer_id',
        'customer_name',
        'service_type',
        'region',
        'phone',
        'traffic_code',
        'number_plates',
        'licence_number',
        'licence_date',
        'operation_number',
        'maker',
        'model',
        'year',
        'color',
        'vin',
        'origin',
        'customer_service_status',
        'customer_service_user',
        'customer_service_date',
        'customer_service_notes',
        'customer_service_file',
        'traffic_charge',
        'bank_charge',
        'other_charge',
        'nejoum_charge',
        'payment_user',
        'payment_updated_date',
        'payment_file',
        'payment_journal',
    ];

    protected $casts = [
        'licence_date' => 'date',
        'create_date' => 'datetime',
        'customer_service_date' => 'datetime',
        'payment_updated_date' => 'datetime',
    ];
    public $timestamps = false;
    public function guestUser()
    {
        return $this->belongsTo(GuestUser::class, 'guest_id');
    }

    public function generalFiles()
    {
        return $this->hasMany(GeneralFile::class, 'primary_column')->where('tag', 'app_traffic_request');
    }

    public function payments()
    {
        return $this->hasMany(AppTrafficRequestPayment::class, 'request_id');
    }
}
