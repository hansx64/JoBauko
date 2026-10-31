<?php

namespace App\Models;

use App\Enums\EducationLevel;
use App\Enums\OpportunityCategory;
use App\Enums\OpportunityStatus;
use App\Enums\VerificationStatus;
use Database\Factories\OpportunityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property OpportunityCategory $category
 * @property OpportunityStatus $status
 * @property string $title
 * @property string|null $description
 * @property int|null $barangay_id
 * @property string|null $location_text
 * @property bool $is_remote
 * @property Carbon|null $application_start
 * @property Carbon|null $application_deadline
 * @property Carbon|null $published_at
 * @property Carbon|null $reopened_at
 * @property string|null $suspension_reason
 * @property OpportunityStatus|null $status_before_suspension
 * @property Carbon|null $suspended_at
 * @property int|null $suspended_by
 */
class Opportunity extends Model
{
    /** @use HasFactory<OpportunityFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * Status and lifecycle timestamps are changed only by OpportunityService.
     *
     * @var list<string>
     */
    protected $fillable = [
        'category',
        'title',
        'description',
        'qualifications',
        'benefits',
        'application_instructions',
        'min_education',
        'barangay_id',
        'location_text',
        'is_remote',
        'application_start',
        'application_deadline',
        'slots',
        'contact_email',
        'contact_number',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => OpportunityCategory::class,
            'status' => OpportunityStatus::class,
            'min_education' => EducationLevel::class,
            'is_remote' => 'boolean',
            'application_start' => 'date',
            'application_deadline' => 'date',
            'slots' => 'integer',
            'published_at' => 'datetime',
            'reopened_at' => 'datetime',
            'status_before_suspension' => OpportunityStatus::class,
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Barangay, $this>
     */
    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    /**
     * @return HasOne<JobDetail, $this>
     */
    public function jobDetail(): HasOne
    {
        return $this->hasOne(JobDetail::class);
    }

    /**
     * @return HasOne<ScholarshipDetail, $this>
     */
    public function scholarshipDetail(): HasOne
    {
        return $this->hasOne(ScholarshipDetail::class);
    }

    /**
     * @return HasOne<LivelihoodDetail, $this>
     */
    public function livelihoodDetail(): HasOne
    {
        return $this->hasOne(LivelihoodDetail::class);
    }

    /**
     * @return HasMany<OpportunityRequirement, $this>
     */
    public function requirements(): HasMany
    {
        return $this->hasMany(OpportunityRequirement::class)->orderBy('sort_order');
    }

    /**
     * Supporting documents applicants must submit.
     *
     * @return HasMany<OpportunityDocument, $this>
     */
    public function requiredDocuments(): HasMany
    {
        return $this->hasMany(OpportunityDocument::class);
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * @return MorphMany<Report, $this>
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function suspender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by')->withTrashed();
    }

    /**
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'opportunity_skills')->withPivot('is_required')->orderBy('name');
    }

    /**
     * Opportunities the public may see: published or closed listings of
     * verified organizations. Drafts, archived and suspended listings, and
     * anything from unverified or suspended organizations, stay hidden.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->whereIn('status', [OpportunityStatus::Published, OpportunityStatus::Closed])
            ->whereHas('organization', fn (Builder $q) => $q->where('verification_status', VerificationStatus::Verified));
    }

    /**
     * Published opportunities whose application period is open today.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', OpportunityStatus::Published)
            ->whereDate('application_deadline', '>=', today())
            ->where(fn (Builder $q) => $q->whereNull('application_start')->orWhereDate('application_start', '<=', today()));
    }

    public function isExpired(): bool
    {
        return $this->status === OpportunityStatus::Published
            && $this->application_deadline !== null
            && $this->application_deadline->lt(today());
    }

    public function isAcceptingApplications(): bool
    {
        return $this->status === OpportunityStatus::Published
            && ! $this->isExpired()
            && ($this->application_start === null || $this->application_start->lte(today()));
    }

    /**
     * Status as shown to users, including the derived "expired" state.
     */
    public function displayStatus(): string
    {
        return $this->isExpired() ? 'expired' : $this->status->value;
    }

    public function displayStatusLabel(): string
    {
        return $this->isExpired() ? 'Expired' : $this->status->label();
    }

    public function locationLabel(): string
    {
        $parts = [];

        if ($this->barangay) {
            $parts[] = "{$this->barangay->name}, Bauko";
        } elseif ($this->location_text) {
            $parts[] = $this->location_text;
        }

        if ($this->is_remote) {
            $parts[] = 'Remote';
        }

        return $parts === [] ? 'Location not specified' : implode(' · ', $parts);
    }
}
