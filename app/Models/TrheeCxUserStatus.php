<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrheeCxUserStatus extends Model
{
    protected $fillable = ['user_id', 'firstName', 'lastName', 'displayName', 'email', 'isRegistred', 'QueueStatus', 'extension', 'status', 'csv_file_id'];



}
