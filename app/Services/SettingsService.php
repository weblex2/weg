<?php
namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Redis;

class SettingsService
{

    public static function get(string $key, $default = null)
    {
        $redisKey = "settings:$key";

        // 1️⃣ Redis
        if (Redis::exists($redisKey)) {
            return Redis::get($redisKey);
        }

        // 2️⃣ DB
        $setting = Setting::find($key);
        if ($setting) {
            Redis::set($redisKey, $setting->value);
            return $setting->value;
        }

        return $default;
    }

    public static function set(string $key, $value): void
    {
        // DB (persistent)
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        // Redis (cache)
        Redis::set("settings:$key", $value);
    }

    public static function warmup(array $keys): void
    {
        foreach ($keys as $key) {
            self::get($key);
        }
    }
}
