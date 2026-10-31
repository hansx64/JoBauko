<?php

namespace App\Models;

use App\Enums\SkillType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Skill extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'type',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SkillType::class,
        ];
    }

    /**
     * @return BelongsToMany<Opportunity, $this>
     */
    public function opportunities(): BelongsToMany
    {
        return $this->belongsToMany(Opportunity::class, 'opportunity_skills');
    }

    /**
     * Find a skill by name regardless of capitalization or spacing, creating
     * it when it isn't in the dictionary yet.
     */
    public static function findOrCreateByName(string $name, SkillType $type = SkillType::Technical): self
    {
        $name = Str::squish($name);

        return static::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'type' => $type],
        );
    }
}
