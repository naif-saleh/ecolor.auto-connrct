<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ADistFeed extends Model
{
    protected $fillable = ['file_name', 'slug', 'allow', 'is_done', 'uploaded_by', 'from', 'to', 'date', 'agent_id'];

    public function user()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
    

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->slug = Str::slug($model->file_name . '-' . time());
        });
    }
}
