<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('children')]
class Child extends Model
{
    use Auditable, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'birth_date_estimated' => 'boolean',
            'has_disability' => 'boolean',
            'anonymized_at' => 'datetime',
        ];
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CaseRecord::class);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }

    public function displayName(): string
    {
        return $this->social_name ?: $this->name;
    }
}
