<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoDialerData extends Model
{
    protected $fillable = ['file_id', 'mobile', 'provider_name', 'extension', 'state'];

    public function file()
    {
        return $this->belongsTo(AutoDailerFile::class, 'file_id');
    }
}
