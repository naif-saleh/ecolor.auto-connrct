<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ADialProvider extends Model
{
    protected $fillable = ['name', 'extension', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function files()
    {
        return $this->hasMany(ADialFeed::class, 'provider_id');
    }
}
