<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AutoDailerReport extends Model
{
    use HasFactory;
    protected $fillable = [
        'call_id',
        'status',
        'provider',
        'extension',
        'phone_number',
    ];


    // public function providerFeed()
    // {
    //     return $this->belongsTo(AutoDailerProviderFeed::class, 'call_id', 'call_id');
    // }
}
