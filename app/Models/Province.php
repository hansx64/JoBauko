<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'psgc_code',
        'name',
    ];

    /**
     * @return HasMany<Municipality, $this>
     */
    public function municipalities(): HasMany
    {
        return $this->hasMany(Municipality::class);
    }
}
