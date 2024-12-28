<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoDailerProviderFeed extends Model
{
    protected $fillable = ['provider_id',  'mobile', 'state', 'call_id', 'party_dn_type', 'call_date', 'auto_dailer_feed_file_id'];

    public function provider()
    {
        return $this->belongsTo(AutoDialerProvider::class, 'provider_id');
    }

    public function feedFile()
    {
        return $this->belongsTo(AutoDailerFeedFile::class, 'auto_dailer_feed_file_id');
    }
    
    public function scopeByProvider($query, $providerId)
    {
        return $query->where('provider_id', $providerId);
    }

    public function scopeByFeedFile($query, $feedFileId)
    {
        return $query->where('auto_dailer_feed_file_id', $feedFileId);
    }

    public function participant()
    {
        return $this->hasOne(Participant::class, 'call_id', 'call_id');
    }
}
