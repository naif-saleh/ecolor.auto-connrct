<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoDistributererFeedFile extends Model
{
    protected $fillable = ['user_ext_id', 'extension', 'from', 'to', 'date', 'on', 'file_name'];
}
