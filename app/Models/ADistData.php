<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ADistData extends Model
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
        'file_id',
        'mobile',
        'state',
        'call_id',
        'call_date'
    ];

    /**
     * Define a relationship with the ADistFeed model.
     *
     * This method establishes an Eloquent relationship, linking this model
     * to the ADistFeed model based on a foreign key. It allows retrieving the
     * associated ADistFeed instance for this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function file()
    {
        return $this->belongsTo(ADistFeed::class, 'feed_id');
    }
}
