<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Application workflow states. Allowed moves are defined here and enforced
 * by ApplicationWorkflow; organizations move applications forward, only
 * the applicant can withdraw.
 */
enum ApplicationStatus: string
{
    use HasOptions;

    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Shortlisted = 'shortlisted';
    case ForInterview = 'for_interview';
    case ForFurtherRequirements = 'for_further_requirements';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::Shortlisted => 'Shortlisted',
            self::ForInterview => 'For Interview',
            self::ForFurtherRequirements => 'For Further Requirements',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /**
     * What the status means for the applicant.
     */
    public function description(): string
    {
        return match ($this) {
            self::Submitted => 'Your application was received and is waiting to be reviewed.',
            self::UnderReview => 'The organization is reviewing your application.',
            self::Shortlisted => 'You are on the shortlist. The organization may contact you for the next steps.',
            self::ForInterview => 'You are invited to an interview. Check the remarks and your email or phone for details.',
            self::ForFurtherRequirements => 'The organization needs more documents or information from you. See the remarks below.',
            self::Approved => 'Congratulations! Your application was approved.',
            self::Rejected => 'Your application was not selected this time. You can keep applying to other opportunities.',
            self::Withdrawn => 'You withdrew this application.',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Withdrawn], true);
    }

    public function isDecision(): bool
    {
        return $this === self::Approved || $this === self::Rejected;
    }

    /**
     * Statuses an organization may move an application to from this one.
     *
     * @return list<self>
     */
    public function nextStatuses(): array
    {
        return match ($this) {
            self::Submitted => [self::UnderReview, self::Shortlisted, self::ForFurtherRequirements, self::Rejected],
            self::UnderReview => [self::Shortlisted, self::ForInterview, self::ForFurtherRequirements, self::Approved, self::Rejected],
            self::Shortlisted => [self::ForInterview, self::ForFurtherRequirements, self::Approved, self::Rejected],
            self::ForInterview => [self::Shortlisted, self::ForFurtherRequirements, self::Approved, self::Rejected],
            self::ForFurtherRequirements => [self::UnderReview, self::Shortlisted, self::ForInterview, self::Approved, self::Rejected],
            self::Approved, self::Rejected, self::Withdrawn => [],
        };
    }

    public function canMoveTo(self $status): bool
    {
        return in_array($status, $this->nextStatuses(), true);
    }

    /**
     * Statuses still in progress, from which the applicant may withdraw.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => ! $status->isFinal()));
    }
}
