<?php

namespace App\Models;

use App\Enums\AssistanceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LivelihoodDetail extends Model
{
    protected $primaryKey = 'opportunity_id';

    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'assistance_type',
        'target_beneficiaries',
        'assistance_provided',
        'training_requirements',
        'target_classifications',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assistance_type' => AssistanceType::class,
            'target_classifications' => 'array',
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
