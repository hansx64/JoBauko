<?php

namespace App\Models;

use App\Enums\Classification;
use App\Enums\EducationLevel;
use App\Enums\EducationStatus;
use App\Enums\Gender;
use Database\Factories\ApplicantProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicantProfile extends Model
{
    /** @use HasFactory<ApplicantProfileFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'classification',
        'date_of_birth',
        'gender',
        'address_line',
        'highest_education',
        'current_education_status',
        'summary',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'classification' => Classification::class,
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'highest_education' => EducationLevel::class,
            'current_education_status' => EducationStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ApplicantEducation, $this>
     */
    public function education(): HasMany
    {
        return $this->hasMany(ApplicantEducation::class)->orderByDesc('is_current')->orderByDesc('year_graduated');
    }

    /**
     * @return HasMany<ApplicantExperience, $this>
     */
    public function experience(): HasMany
    {
        return $this->hasMany(ApplicantExperience::class)->orderByRaw('end_date is null desc')->orderByDesc('start_date');
    }

    /**
     * @return HasMany<ApplicantCertification, $this>
     */
    public function certifications(): HasMany
    {
        return $this->hasMany(ApplicantCertification::class)->orderByDesc('issued_at');
    }

    /**
     * @return HasMany<ApplicantTraining, $this>
     */
    public function trainings(): HasMany
    {
        return $this->hasMany(ApplicantTraining::class)->orderByDesc('completed_at');
    }

    /**
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'applicant_skills')
            ->withPivot('source')
            ->withTimestamps()
            ->orderBy('name');
    }

    /**
     * @return HasMany<ApplicantInterest, $this>
     */
    public function interests(): HasMany
    {
        return $this->hasMany(ApplicantInterest::class);
    }
}
