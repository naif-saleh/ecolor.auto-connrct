<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class General_Setting extends Model
{
    use HasFactory;

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
