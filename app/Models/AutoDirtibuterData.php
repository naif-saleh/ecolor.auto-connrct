<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AutoDirtibuterData extends Model
{
    protected $fillable = ['file_id', 'mobile', 'user_name', 'extension', 'state'];

    public function file()
    {
        return $this->belongsTo(AutoDistributorFile::class, 'file_id');
    }
}
