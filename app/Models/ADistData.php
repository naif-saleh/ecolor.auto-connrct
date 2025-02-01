<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ADistData extends Model
{
    protected $fillable = ['file_id', 'mobile', 'state', 'call_id', 'call_date'];

    public function file()
    {
        return $this->belongsTo(ADistFeed::class, 'feed_id');
    }
}
