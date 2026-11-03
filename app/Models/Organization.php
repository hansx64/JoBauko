<?php

namespace App\Models;

use App\Enums\OrganizationType;
use App\Enums\VerificationStatus;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * Verification fields are deliberately excluded; they change only
     * through the administrator verification workflow.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'official_email',
        'contact_number',
        'office_address',
        'barangay_id',
        'description',
        'representative_name',
        'representative_position',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Barangay, $this>
     */
    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return HasMany<OrganizationMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    /**
     * @return HasMany<OrganizationDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(OrganizationDocument::class);
    }

    /**
     * @return HasMany<Opportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function isVerified(): bool
    {
        return $this->verification_status === VerificationStatus::Verified;
    }

    public function isSuspended(): bool
    {
        return $this->verification_status === VerificationStatus::Suspended;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path
            ? route('organizations.logo', ['organization' => $this, 'v' => substr(md5($this->logo_path), 0, 8)])
            : null;
    }
}
