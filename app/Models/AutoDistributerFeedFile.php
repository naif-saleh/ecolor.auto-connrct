<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoDistributerFeedFile extends Model
{
    protected $fillable = ['user_ext_id', 'extension', 'userStatus', 'three_cx_user_id', 'from', 'to', 'date', 'on', 'file_name'];

    public function extension()
    {
        return $this->belongsTo(AutoDistributererExtension::class, 'user_ext_id');
    }

    public function extensionFeeds()
    {
        return $this->hasMany(AutoDistributerExtensionFeed::class, 'auto_dist_feed_file_id');
    }

}
