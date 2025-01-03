<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoDistributererExtension extends Model
{
    protected $fillable = ['name','lastName', 'extension', 'userStatus', 'user_id', '3cx_user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function feedFiles()
    {
        return $this->hasMany(AutoDistributerFeedFile::class, 'user_ext_id');
    }

    public function extensionFeeds()
    {
        return $this->hasMany(AutoDistributerExtensionFeed::class, 'user_ext_id');
    }
}
