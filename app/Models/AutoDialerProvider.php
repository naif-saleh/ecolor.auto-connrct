<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoDialerProvider extends Model
{
    protected $fillable = ['name', 'extension', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function files()
    {
        return $this->hasMany(AutoDailerFile::class, 'provider_id');
    }
}
