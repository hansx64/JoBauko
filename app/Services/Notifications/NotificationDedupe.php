<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\DB;

/**
 * Makes an automatic notice go out at most once per key.
 */
class NotificationDedupe
{
    /**
     * True the first time a key is claimed, false every time after.
     */
    public static function claim(string $key): bool
    {
        return DB::table('notification_dedupes')->insertOrIgnore(['key' => $key, 'created_at' => now()]) === 1;
    }
}
