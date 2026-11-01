<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A supporting document an opportunity requires applicants to submit.
 */
class OpportunityDocument extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'is_required',
        'accepted_types',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
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
     * Whether this asks for the applicant's resume, e.g. "Resume / CV". The
     * resume on the applicant's profile is sent with the application and
     * stands in for it, so it is not uploaded twice.
     */
    public function isResume(): bool
    {
        return preg_match('/\b(r[eé]sum[eé]|cv|curriculum vitae)\b/iu', $this->name) === 1;
    }
}
