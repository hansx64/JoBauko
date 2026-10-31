<?php

namespace App\Models;

use App\Enums\SmsTemplateKey;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property SmsTemplateKey $key
 * @property string $name
 * @property string $body
 * @property bool $is_active
 */
class SmsTemplate extends Model
{
    /** Longest message sent: two SMS segments. */
    public const MAX_LENGTH = 320;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'name',
        'body',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => SmsTemplateKey::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * The stored template, created from the default wording on first use.
     */
    public static function for(SmsTemplateKey $key): self
    {
        return self::firstOrCreate(
            ['key' => $key],
            ['name' => $key->label(), 'body' => $key->defaultBody(), 'is_active' => true],
        );
    }

    /**
     * Fill in the placeholders. Unknown or empty placeholders are dropped
     * and the result is capped at MAX_LENGTH characters.
     *
     * @param  array<string, string|null>  $values
     */
    public function render(array $values): string
    {
        $text = preg_replace_callback('/\{(\w+)\}/', fn (array $m) => (string) ($values[$m[1]] ?? ''), $this->body);
        $text = trim((string) preg_replace('/\s+/', ' ', (string) $text));

        // Plain dots: a "…" would switch the whole SMS to Unicode encoding,
        // which fits fewer than half as many characters per segment.
        return mb_strlen($text) > self::MAX_LENGTH ? rtrim(mb_substr($text, 0, self::MAX_LENGTH - 3)).'...' : $text;
    }
}
