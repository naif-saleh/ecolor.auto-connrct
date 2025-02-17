<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    protected $fillable = ['key', 'value', 'description'];

    public static function get($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function set($key, $value, $description = null)
    {
        $setting = self::where('key', $key)->first();
        if ($setting) {
            $setting->update([
                'value' => $value,
                'description' => $description ?? $setting->description
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
