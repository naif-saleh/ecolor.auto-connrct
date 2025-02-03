<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ADialProvider extends Model
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
        'name',
        'extension',
        'user_id'
    ];


    /**
     * Define a relationship with the User model.
     *
     * This method establishes an Eloquent relationship, linking this model
     * to the User model based on a foreign key. It allows retrieving the
     * associated user instance for this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Define a relationship with the ADialFeed model.
     *
     * This method establishes an Eloquent relationship, linking this model
     * to the ADialFeed model based on a foreign key. It allows retrieving the
     * associated ADialFeed instance for this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */

    public function files()
    {
        return $this->hasMany(ADialFeed::class, 'provider_id');
    }
}
