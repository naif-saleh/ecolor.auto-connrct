<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ADialFeed extends Model
{
    use HasFactory;

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
        'is_done',
        'allow',
        'from',
        'to',
        'date',
        'uploaded_by',
        'provider_id',
        'webhook_batch_id'
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
        return $this->hasMany(ADialData::class, 'feed_id'); // hasMany instead of belongsTo
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
    public function provider()
{
    return $this->belongsTo(ADialProvider::class, 'provider_id');
}

    /**
     * Generate Slug
     *
     */
    // protected static function boot()
    // {
    //     parent::boot();

    //     static::creating(function ($model) {
    //         $model->slug = Str::slug($model->file_name . '-' . time());
    //     });
    // }
}
