<?php

namespace App\Services\Recommendation;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Keeps opportunity_matches current. Scores are recomputed for a seeker the
 * next time they are needed after either their profile or any opportunity
 * changed, so stale scores are never shown and nothing is computed eagerly
 * for seekers who aren't using the portal.
 */
class MatchRefresher
{
    private const GLOBAL_KEY = 'matches:version';

    public function __construct(private readonly MatchScorer $scorer) {}

    /**
     * Call when opportunities change (published, edited, closed, ...) or an
     * organization's visibility changes.
     */
    public static function invalidateAll(): void
    {
        Cache::forever(self::GLOBAL_KEY, (string) Str::uuid());
    }

    /**
     * Call when a seeker's profile changes.
     */
    public static function invalidateUser(int $userId): void
    {
        Cache::forget(self::userKey($userId));
    }

    public function ensureFresh(User $user): void
    {
        if (! $user->isSeeker()) {
            return;
        }

        $version = Cache::rememberForever(self::GLOBAL_KEY, fn () => (string) Str::uuid());

        if (Cache::get(self::userKey($user->id)) === $version) {
            return;
        }

        $this->refresh($user);

        Cache::forever(self::userKey($user->id), $version);
    }

    /**
     * Score every open opportunity for the seeker and replace their stored
     * matches.
     */
    public function refresh(User $user): void
    {
        $snapshot = SeekerSnapshot::fromUser($user);
        $now = now();

        $rows = Opportunity::query()
            ->visible()
            ->open()
            ->with(['skills', 'barangay', 'jobDetail', 'scholarshipDetail', 'livelihoodDetail'])
            ->get()
            ->map(function (Opportunity $opportunity) use ($snapshot, $user, $now) {
                $result = $this->scorer->score($snapshot, $opportunity);

                return [
                    'user_id' => $user->id,
                    'opportunity_id' => $opportunity->id,
                    'score' => $result->score,
                    'reasons' => json_encode(['reasons' => $result->reasons, 'gaps' => $result->gaps]),
                    'computed_at' => $now,
                ];
            });

        DB::transaction(function () use ($user, $rows) {
            DB::table('opportunity_matches')->where('user_id', $user->id)->delete();

            foreach ($rows->chunk(500) as $chunk) {
                DB::table('opportunity_matches')->insert($chunk->all());
            }
        });
    }

    private static function userKey(int $userId): string
    {
        return "matches:user:{$userId}";
    }
}
