<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{protected $table = 'service_requests';
    protected $fillable = [
        'service_name', 'service_price', 'u_id'
    ];
    public $timestamps = false;
    public function guestUser()
    {
        return $this->belongsTo(GuestUser::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
