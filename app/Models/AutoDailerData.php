<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AutoDailerData extends Model
{
    use HasFactory;

    protected $fillable = ['auto_dailer_id', 'mobile', 'provider_name', 'extension', 'state'];

    // Relationship with AutoDailer
    public function autodailer()
    {
        return $this->belongsTo(AutoDailer::class);
    }
}
