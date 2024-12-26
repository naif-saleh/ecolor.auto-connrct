<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoDialerProvider extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'extension', 'file_sound', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
