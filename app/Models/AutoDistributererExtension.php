<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoDistributererExtension extends Model
{
    protected $fillable = ['name', 'lastName', 'extension', 'userStatus', 'user_id', 'three_cx_user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function files()
    {
        return $this->hasMany(AutoDistributorFile::class, 'provider_id');
    }
}
