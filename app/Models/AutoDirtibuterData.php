<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AutoDirtibuterData extends Model
{
    use HasFactory;

    protected $fillable = ['auto_dirtibuter_id', 'mobile', 'provider_name', 'extension', 'state'];

    // Relationship with AutoDirtibuter
    public function autodistributer()
    {
        return $this->belongsTo(AutoDirtibuter::class);
    }
}
