<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ADialSkippedNumbers extends Model
{
     /**
     * The attributes that are mass assignable.
     *
     * These fields can be safely filled using mass assignment methods
     * like create() and fill(). Ensure only the necessary fields are
     * included to prevent mass assignment vulnerabilities.
     *
     * @var array
     */
    protected $fillable = [
        'mobile',
        'message',
        'uploaded_by',
        'provider_id',
        'feed_id',
        'webhook_batch_id'

    ];
}
