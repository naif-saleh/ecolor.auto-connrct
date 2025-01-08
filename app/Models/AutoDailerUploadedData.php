<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoDailerUploadedData extends Model
{
    protected $fillable = ['mobile', 'provider', 'extension', 'from', 'to', 'date', 'uploaded_by', 'state', 'file_id', 'call_date', 'call_id' ];


    public function file(){
        return $this->belongsTo(AutoDailerFile::class, 'file_id');
    }

    public function report(){
        return $this->belongsTo(AutoDailerReport::class, 'call_id');
    }

    public function user(){
        return $this->belongsTo(User::class, 'uploaded_by');
    }
    
}
