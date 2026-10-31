<?php

namespace App\Http\Controllers\Public;

use App\Enums\OpportunityCategory;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Support\OpportunityCard;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $openCounts = Opportunity::query()->visible()->open()
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        return Inertia::render('welcome', [
            'categories' => array_map(fn (OpportunityCategory $category) => [
                'value' => $category->value,
                'label' => $category->pluralLabel(),
                'open' => (int) ($openCounts[$category->value] ?? 0),
            ], OpportunityCategory::cases()),
            'latest' => Opportunity::query()->visible()->open()
                ->with(['organization', 'barangay', 'jobDetail'])
                ->latest('published_at')
                ->limit(6)
                ->get()
                ->map(fn (Opportunity $o) => OpportunityCard::make($o)),
            'featuredOrganizations' => Organization::query()
                ->where('verification_status', VerificationStatus::Verified)
                ->withCount(['opportunities as open_count' => fn (Builder $q) => $q->open()])
                ->having('open_count', '>', 0)
                ->orderByDesc('open_count')
                ->limit(6)
                ->get()
                ->map(fn (Organization $o) => [
                    'id' => $o->id,
                    'name' => $o->name,
                    'type' => $o->type->label(),
                    'logo_url' => $o->logoUrl(),
                    'open_count' => $o->open_count,
                ]),
            'totals' => [
                'open' => (int) $openCounts->sum(),
                'organizations' => Organization::where('verification_status', VerificationStatus::Verified)->count(),
            ],
        ]);
    }
}
