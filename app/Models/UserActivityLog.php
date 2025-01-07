<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'opreation',
        'user_role',
        'user_name',
        'user_email',

    ];


    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }
}
