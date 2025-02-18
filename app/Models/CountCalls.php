<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CountCalls extends Model
{
    protected $fillable = ['key', 'value', 'description'];

    public static function get($key, $default = null)
    {
        $count = self::where('key', $key)->first();
        return $count ? $count->value : $default;
    }

    public static function set($key, $value, $description = null)
    {
        $count = self::where('key', $key)->first();
        if ($count) {
            $count->update([
                'value' => $value,
                'description' => $description ?? $count->description
            ]);
        } else {
            self::create([
                'key' => $key,
                'value' => $value,
                'description' => $description
            ]);
        }
    }
}
