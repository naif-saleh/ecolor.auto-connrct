<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoDistributerExtensionFeed extends Model
{
    protected $fillable = ['user_ext_id', 'mobile', 'state','call_id', 'party_dn_type', 'call_date', 'auto_dist_feed_file_id'];

    public function extension()
    {
        return $this->belongsTo(AutoDistributererExtension::class, 'user_ext_id');
    }


    public function feedFile()
    {
        return $this->belongsTo(AutoDistributerFeedFile::class, 'auto_dist_feed_file_id');
    }
    public function scopeByFeedFile($query, $feedFileId)
    {
        return $query->where('auto_dist_feed_file_id', $feedFileId);
    }
}
