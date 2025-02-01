<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ADialData extends Model
{
    protected $fillable = ['feed_id', 'mobile', 'state', 'call_id', 'call_date'];

    public function file()
    {
        return $this->belongsTo(ADialFeed::class, 'feed_id');
    }
    
}
