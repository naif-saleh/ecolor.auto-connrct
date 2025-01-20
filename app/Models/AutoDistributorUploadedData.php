<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoDistributorUploadedData extends Model
{
    protected $fillable = ['mobile', 'user', 'extension', 'userStatus', 'uploaded_by', 'state', 'file_id', 'call_date', 'call_id', 'three_cx_user_id' ];


    public function file(){
        return $this->belongsTo(AutoDistributorFile::class, 'file_id');
    }

    public function report(){
        return $this->belongsTo(AutoDistributerReport::class, 'call_id');
    }

    public function threeCxUsers(){
        return $this->belongsTo(TrheeCxUserStatus::class, 'three_cx_user_id');
    }
}
