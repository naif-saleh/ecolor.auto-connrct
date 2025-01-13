<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    protected $fillable = ['mobile', 'extension', 'is_satisfied', 'lang'];
}
