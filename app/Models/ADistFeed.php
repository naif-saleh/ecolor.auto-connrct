<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ADistFeed extends Model
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
        'file_name',
        'slug',
        'allow',
        'is_done',
        'uploaded_by',
        'from',
        'to',
        'date',
        'agent_id'
    ];

    /**
     * Define a relationship with the User model.
     *
     * This method establishes an Eloquent relationship, linking this model
     * to the User model based on a foreign key. It allows retrieving the
     * associated User instance for this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }


     /**
     * Define a relationship with the ADialData model.
     *
     * This method establishes an Eloquent relationship, linking this model
     * to the ADialData model based on a foreign key. It allows retrieving the
     * associated ADialData instance for this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function uploadedData()
    {
        return $this->hasMany(ADistData::class, 'feed_id'); // hasMany instead of belongsTo
    }


     /**
     * Define a relationship with the ADialProvider model.
     *
     * This method establishes an Eloquent relationship, linking this model
     * to the ADialProvider model based on a foreign key. It allows retrieving the
     * associated ADialProvider instance for this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function agent()
    {
        return $this->belongsTo(ADistAgent::class, 'agent_id', 'id');
    }

}
