<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class AutoDistributerReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'call_id',
        'status',
        'provider',
        'extension',
        'phone_number',
        'duration_time',
    ];

    public function providerFeed()
    {
        return $this->belongsTo(AutoDistributerExtensionFeed::class, 'call_id', 'call_id');
    }
}
