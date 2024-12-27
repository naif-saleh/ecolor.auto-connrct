<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoDailerProviderFeed extends Model
{
    protected $fillable = ['provider_id', 'mobile', 'extension', 'from', 'to', 'date', 'on', 'auto_dailer_feed_file_id'];

    public function provider()
    {
        return $this->belongsTo(AutoDialerProvider::class, 'provider_id');
    }

    public function feedFile()
    {
        return $this->belongsTo(AutoDailerFeedFile::class, 'auto_dailer_feed_file_id');
    }
}
