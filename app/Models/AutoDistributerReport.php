<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class AutoDistributerReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'mobile',
        'provider',
        'extension',
        'state',
        'called_at',
    ];
}
