<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $opportunity_id
 * @property int $user_id
 * @property int|null $resume_id
 * @property string|null $cover_letter
 * @property ApplicationStatus $status
 * @property Carbon|null $data_sharing_consented_at
 * @property Carbon $submitted_at
 * @property Carbon|null $first_reviewed_at
 * @property Carbon|null $decided_at
 * @property Carbon|null $withdrawn_at
 * @property string|null $withdrawal_reason
 */
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * Status and its timestamps are changed only by ApplicationWorkflow.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cover_letter',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'data_sharing_consented_at' => 'datetime',
            'submitted_at' => 'datetime',
            'first_reviewed_at' => 'datetime',
            'decided_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Opportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Resume, $this>
     */
    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class);
    }

    /**
     * @return HasMany<ApplicationDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class)->orderBy('id');
    }

    /**
     * @return HasMany<ApplicationStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Internal notes of the organization; never shown to the applicant.
     *
     * @return HasMany<ApplicationRemark, $this>
     */
    public function remarks(): HasMany
    {
        return $this->hasMany(ApplicationRemark::class)->latest();
    }

    public function canBeWithdrawn(): bool
    {
        return ! $this->status->isFinal();
    }

    public function acceptsAdditionalDocuments(): bool
    {
        return $this->status === ApplicationStatus::ForFurtherRequirements;
    }
}
