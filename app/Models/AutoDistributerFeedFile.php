<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoDistributerFeedFile extends Model
{
    protected $fillable = ['user_ext_id', 'extension', 'from', 'to', 'date', 'on', 'file_name'];

    public function user_ext()
    {
        return $this->belongsTo(AutoDistributererExtension::class);
    }

    public function feeds()
    {
        return $this->hasMany(AutoDistributerExtensionFeed::class);
    }

    public function providerFeeds()
    {
        return $this->hasMany(AutoDistributerExtensionFeed::class, 'auto_dist_feed_file_id');
    }
}
