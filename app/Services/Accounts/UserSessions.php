<?php

namespace App\Services\Accounts;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signs a person out of their other browsers and devices: their stored
 * sessions are deleted and "remember me" cookies stop working.
 */
class UserSessions
{
    public static function end(User $user, ?string $exceptSessionId = null): void
    {
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->when($exceptSessionId, fn ($q) => $q->where('id', '!=', $exceptSessionId))
            ->delete();

        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }
}
