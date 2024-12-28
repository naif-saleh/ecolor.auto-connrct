<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoDistributererProvider extends Model
{
    protected $fillable = ['name', 'extension', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function feedFiles()
    {
        return $this->hasMany(AutoDistributererFeedFile::class, 'user_ext_id');
    }
}
