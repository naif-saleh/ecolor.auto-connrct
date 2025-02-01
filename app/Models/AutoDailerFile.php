<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AutoDailerFile extends Model
{
    protected $fillable = ['file_name', 'slug', 'is_done', 'allow', 'from', 'to', 'date', 'uploaded_by', 'provider_id'];

    public function user()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function uploadedData()
    {
        return $this->hasMany(AutoDailerUploadedData::class, 'file_id'); // hasMany instead of belongsTo
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->slug = Str::slug($model->file_name . '-' . time());
        });
    }
}
