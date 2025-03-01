<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemporaryCall extends Model
{

    protected $fillable = [
        'call_id',
        'provider',
        'extension',
        'phone_number',
        'call_data',
        'status'
    ];
    
}
