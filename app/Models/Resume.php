<?php

namespace App\Models;

use App\Enums\ResumeExtractionStatus;
use Database\Factories\ResumeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resume extends Model
{
    /** @use HasFactory<ResumeFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'disk_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'is_generated',
        'is_current',
        'extraction_status',
        'extracted_text',
        'extracted_data',
        'reviewed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'disk_path',
        'extracted_text',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'is_generated' => 'boolean',
            'size_bytes' => 'integer',
            'extraction_status' => ResumeExtractionStatus::class,
            'extracted_data' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }
}
