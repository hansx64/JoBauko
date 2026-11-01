<?php

namespace App\Models;

use App\Enums\CivilStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Details a seeker adds only for the resume builder (objective, languages,
 * character references, optional personal details).
 */
class ResumeDetail extends Model
{
    /** Most employers ask for three character references. */
    public const MAX_REFERENCES = 3;

    public const MAX_LANGUAGES = 8;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'desired_position',
        'objective',
        'languages',
        'references',
        'civil_status',
        'nationality',
        'show_birthdate',
        'show_sex',
        'include_certification',
    ];

    /**
     * Defaults for a seeker who has not saved any details yet.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'show_birthdate' => false,
        'show_sex' => false,
        'include_certification' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'languages' => 'array',
            'references' => 'array',
            'civil_status' => CivilStatus::class,
            'show_birthdate' => 'boolean',
            'show_sex' => 'boolean',
            'include_certification' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
