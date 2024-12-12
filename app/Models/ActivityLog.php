<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'operation', 'file_type', 'file_name', 'operation_time'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
