<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Participant extends Model
{
    protected $fillable = ['call_id', 'status', 'phone_number'];

    public function autoDailerProviderFeed()
    {
        return $this->belongsTo(AutoDailerProviderFeed::class, 'call_id', 'call_id');
    }
}
