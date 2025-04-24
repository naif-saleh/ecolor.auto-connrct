<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ADialWebhookBatch extends Model
{
    protected $fillable = [
        'batch_id',
        'status',
        'total_numbers',
        'processed_numbers',
        'skipped_numbers',
        'is_last_batch',
        'user_id',
        'received_at',
        'processing_started_at',
        'completed_at',
        'errors',
    ];

    protected $casts = [
        'is_last_batch' => 'boolean',
        'received_at' => 'datetime',
        'processing_started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user who initiated this batch.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the feeds created from this batch.
     */
    public function feeds()
    {
        return $this->hasMany(ADialFeed::class, 'webhook_batch_id');
    }

    /**
     * Get the skipped numbers for this batch.
     */
    public function skippedNumbers()
    {
        return $this->hasMany(ADialSkippedNumbers::class, 'webhook_batch_id');
    }
}
