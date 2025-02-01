<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ADistAgent extends Model
{
    protected $fillable = ['firstName', 'lastName', 'displayName', 'email', 'three_cx_user_id' , 'user_id','isRegistred', 'QueueStatus', 'extension', 'status'];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function files()
    {
        return $this->hasMany(ADistFeed::class, 'agent_id');
    }
}
