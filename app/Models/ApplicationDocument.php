<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file submitted with an application. The label is copied from the
 * opportunity's document requirement (or typed by the applicant for
 * additional documents) so it survives later edits of the opportunity.
 *
 * @property int $id
 * @property int $application_id
 * @property int|null $opportunity_document_id
 * @property string $label
 * @property string $disk_path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 */
class ApplicationDocument extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'opportunity_document_id',
        'label',
        'disk_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'disk_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return BelongsTo<OpportunityDocument, $this>
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(OpportunityDocument::class, 'opportunity_document_id');
    }

    public function isPreviewable(): bool
    {
        return in_array($this->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true);
    }
}
