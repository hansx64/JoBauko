<?php

namespace App\Support;

use App\Enums\OpportunityCategory;
use App\Models\Opportunity;
use Illuminate\Support\Str;

/**
 * The summary of an opportunity shown on cards (landing page, browse,
 * dashboard, saved list). Expects organization, barangay and jobDetail to
 * be loaded.
 */
final class OpportunityCard
{
    /**
     * @param  array{score: int, reasons: list<string>, gaps?: list<string>}|null  $match
     * @return array<string, mixed>
     */
    public static function make(Opportunity $opportunity, ?array $match = null, bool $saved = false): array
    {
        $deadline = $opportunity->application_deadline;

        return [
            'id' => $opportunity->id,
            'slug' => $opportunity->slug,
            'title' => $opportunity->title,
            'category' => $opportunity->category->value,
            'category_label' => $opportunity->category->label(),
            'organization' => [
                'name' => $opportunity->organization->name,
                'logo_url' => $opportunity->organization->logoUrl(),
            ],
            'location' => $opportunity->locationLabel(),
            'deadline' => $deadline?->toDateString(),
            'days_left' => $deadline && $deadline->gte(today()) ? (int) today()->diffInDays($deadline) : null,
            'status' => $opportunity->displayStatus(),
            'status_label' => $opportunity->displayStatusLabel(),
            'accepting' => $opportunity->isAcceptingApplications(),
            'summary' => Str::limit(Str::squish((string) $opportunity->description), 170),
            'employment_type' => $opportunity->category === OpportunityCategory::Job ? $opportunity->jobDetail?->employment_type->label() : null,
            'salary' => self::salary($opportunity),
            'slots' => $opportunity->slots,
            'match' => $match,
            'saved' => $saved,
        ];
    }

    public static function salary(Opportunity $opportunity): ?string
    {
        $detail = $opportunity->jobDetail;

        if ($opportunity->category !== OpportunityCategory::Job || $detail === null) {
            return null;
        }

        $format = fn ($amount) => '₱'.number_format((float) $amount);

        return match (true) {
            $detail->salary_min !== null && $detail->salary_max !== null && (float) $detail->salary_min !== (float) $detail->salary_max => $format($detail->salary_min).' – '.$format($detail->salary_max).' / month',
            $detail->salary_min !== null => $format($detail->salary_min).' / month',
            $detail->salary_max !== null => 'Up to '.$format($detail->salary_max).' / month',
            default => null,
        };
    }
}
