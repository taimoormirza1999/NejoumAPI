<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralFile extends Model
{  protected $table = 'general_files';

    protected $fillable = ['file_name', 'table_id', 'primary_column', 'tag', 'create_by'];
    public $timestamps = false;
    public function guestUser()
    {
        return $this->belongsTo(GuestUser::class, 'create_by');
    }

    public function appTrafficRequest()
    {
        return $this->belongsTo(AppTrafficRequest::class, 'primary_column');
    }
}
