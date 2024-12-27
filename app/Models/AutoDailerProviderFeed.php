<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoDailerProviderFeed extends Model
{
    protected $fillable = ['provider_id', 'mobile', 'extension', 'from', 'to', 'date', 'on'];

    public function provider()
    {
        return $this->belongsTo(AutoDialerProvider::class, 'provider_id');
    }
}
