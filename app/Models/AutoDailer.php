<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AutoDailer extends Model
{
    use HasFactory;

    protected $fillable = ['file_name', 'uploaded_by', 'file_path'];

    // Relationship with User
    public function user()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Relationship with CSV Data
    public function autodailerData()
    {
        return $this->hasMany(AutoDailerData::class);
    }
}
