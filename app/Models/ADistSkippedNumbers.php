<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ADistSkippedNumbers extends Model
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
        'agent_id',
        'feed_id',
        'webhook_batch_id',

    ];

    protected $table = 'a_dist_skipped_numbers';

     /**
     * Define a relationship with the ADistFeed model.
     *
     * This method establishes an Eloquent relationship, linking this model
     * to the ADistFeed model based on a foreign key. It allows retrieving the
     * associated ADistFeed instance for this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function agent()
    {
        return $this->belongsTo(ADistAgent::class, 'id');
    }
}
