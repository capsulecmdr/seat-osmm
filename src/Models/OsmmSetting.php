<?php

namespace CapsuleCmdr\SeatOsmm\Models;

use Illuminate\Database\Eloquent\Model;

class OsmmSetting extends Model
{
    protected $table = 'osmm_settings';
    protected $fillable = ['key','value','type','updated_by'];

    // Convenience getters
    public static function get(string $key, $default = null)
    {
        $row = static::query()->where('key', $key)->first();
        return $row ? $row->value : $default;
    }

    public static function put(string $key, $value, string $type = 'text', ?int $user_id = null): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type, 'updated_by' => $user_id]
        );
    }
}
