<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipality extends Model
{
    use Auditable, HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'accepts_public_reports' => 'boolean'];
    }

    public function flowSteps(): HasMany
    {
        return $this->hasMany(FlowStep::class)->orderBy('position')->orderBy('id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CaseRecord::class);
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function fullName(): string
    {
        return "{$this->name}/{$this->uf}";
    }
}
