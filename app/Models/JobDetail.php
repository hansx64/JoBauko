<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\WorkSetup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobDetail extends Model
{
    protected $primaryKey = 'opportunity_id';

    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employment_type',
        'salary_min',
        'salary_max',
        'work_setup',
        'min_experience_years',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'work_setup' => WorkSetup::class,
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'min_experience_years' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Opportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
}
