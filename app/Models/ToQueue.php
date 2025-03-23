<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ToQueue extends Model
{
    protected $fillable = [
        'call_id',
        'status',
        'a_dial_report_id',

    ];

    
}
