<?php

namespace App\Services\Moderation;

use App\Enums\OpportunityStatus;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Opportunity;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportReviewed;
use App\Services\Audit\AuditLogger;
use App\Services\Opportunities\OpportunityService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Reports about opportunities and the administrator decisions on them. A
 * decision is about the listing, so it closes every open report on it.
 */
class ReportService
{
    /** Decisions an administrator can make on an open report. */
    public const DECISIONS = ['suspend', 'resolve', 'dismiss'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly OpportunityService $opportunities,
    ) {}

    public function submit(User $reporter, Opportunity $opportunity, ReportReason $reason, ?string $details): Report
    {
        if ($reporter->organizationMembership?->organization_id === $opportunity->organization_id) {
            throw ValidationException::withMessages(['reason' => 'You cannot report your own organization’s opportunity.']);
        }

        $alreadyOpen = $opportunity->reports()->open()->where('reporter_id', $reporter->id)->exists();

        if ($alreadyOpen) {
            throw ValidationException::withMessages(['reason' => 'You already reported this opportunity. An administrator will review it.']);
        }

        $report = new Report(['reason' => $reason, 'details' => $details]);
        $report->reporter_id = $reporter->id;
        $report->reportable()->associate($opportunity);
        $report->status = ReportStatus::Open;
        $report->save();

        // Not tagged with the organization: who reported a listing is not
        // shown to the organization.
        $this->audit->log('report.submitted', $report, new: ['reason' => $reason->value, 'opportunity_id' => $opportunity->id]);

        return $report;
    }

    /**
     * @param  'suspend'|'resolve'|'dismiss'  $decision
     */
    public function decide(Report $report, User $admin, string $decision, string $notes): void
    {
        if (! $report->isOpen()) {
            throw ValidationException::withMessages(['decision' => 'This report has already been reviewed.']);
        }

        $opportunity = $report->reportable;

        if ($decision === 'suspend' && (! $opportunity instanceof Opportunity || $opportunity->status === OpportunityStatus::Suspended)) {
            throw ValidationException::withMessages(['decision' => 'This opportunity is already suspended or no longer exists. Mark the report as resolved instead.']);
        }

        $reports = DB::transaction(function () use ($report, $admin, $decision, $notes, $opportunity) {
            if ($decision === 'suspend') {
                // The notes double as the reason sent to the organization.
                $this->opportunities->suspend($opportunity, $admin, $notes);
            }

            /** @var Collection<int, Report> $reports */
            $reports = Report::query()
                ->open()
                ->where('reportable_type', $report->reportable_type)
                ->where('reportable_id', $report->reportable_id)
                ->lockForUpdate()
                ->get();

            foreach ($reports as $item) {
                $item->forceFill([
                    'status' => $decision === 'dismiss' ? ReportStatus::Dismissed : ReportStatus::ActionTaken,
                    'outcome' => match ($decision) {
                        'suspend' => 'opportunity_suspended',
                        'resolve' => 'resolved',
                        'dismiss' => 'no_violation',
                    },
                    'resolved_by' => $admin->id,
                    'resolved_at' => now(),
                    'resolution_notes' => $notes,
                ])->save();
            }

            $this->audit->log('report.reviewed', $report, ['status' => ReportStatus::Open->value], [
                'decision' => $decision,
                'reports_closed' => $reports->count(),
            ]);

            return $reports;
        });

        $reporters = $reports->map->reporter->filter(fn (?User $user) => $user !== null && ! $user->trashed())->unique('id');

        Notification::send($reporters, new ReportReviewed($report->refresh(), $opportunity instanceof Opportunity ? $opportunity->title : null));
    }
}
