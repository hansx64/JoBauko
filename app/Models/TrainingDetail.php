<?php

namespace App\Models;

use App\Enums\TrainingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 */
class TrainingDetail extends Model
{
    protected $primaryKey = 'opportunity_id';

    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'training_type',
        'starts_on',
        'ends_on',
        'schedule',
        'duration_hours',
        'fee',
        'certificate',
        'topics',
        'inclusions',
        'target_participants',
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
            'training_type' => TrainingType::class,
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'duration_hours' => 'integer',
            'fee' => 'decimal:2',
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

    public function isFree(): bool
    {
        return $this->fee === null || (float) $this->fee === 0.0;
    }

    /**
     * "Free" or the amount, e.g. "₱1,500".
     */
    public function feeLabel(): string
    {
        return $this->isFree() ? 'Free' : '₱'.number_format((float) $this->fee, fmod((float) $this->fee, 1.0) === 0.0 ? 0 : 2);
    }

    /**
     * e.g. "Mar 3 – Mar 28, 2027", or a single date.
     */
    public function dateLabel(): ?string
    {
        if ($this->starts_on === null) {
            return null;
        }

        if ($this->ends_on === null || $this->ends_on->isSameDay($this->starts_on)) {
            return $this->starts_on->format('M j, Y');
        }

        $start = $this->starts_on->format($this->starts_on->isSameYear($this->ends_on) ? 'M j' : 'M j, Y');

        return "{$start} – {$this->ends_on->format('M j, Y')}";
    }
}
