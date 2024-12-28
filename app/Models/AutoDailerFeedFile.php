<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoDailerFeedFile extends Model
{
    protected $fillable = ['provider_id', 'extension', 'from', 'to', 'date', 'on', 'file_name'];

    // Relationship: Each FeedFile belongs to one AutoDialerProvider
    public function provider()
    {
        return $this->belongsTo(AutoDialerProvider::class);
    }

    // Relationship: A FeedFile can have many AutoDailerProviderFeeds
    public function feeds()
    {
        return $this->hasMany(AutoDailerProviderFeed::class);
    }

    public function providerFeeds()
    {
        return $this->hasMany(AutoDailerProviderFeed::class, 'auto_dailer_feed_file_id');
    }
}
