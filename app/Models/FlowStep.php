<?php

namespace App\Models;

use App\Enums\ReferralDestination;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowStep extends Model
{
    use Auditable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'destination' => ReferralDestination::class,
            'violence_types' => 'array',
            'only_urgent' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function appliesTo(CaseRecord $case): bool
    {
        if (! $this->active || ($this->only_urgent && ! $case->urgent)) {
            return false;
        }

        return empty($this->violence_types)
            || array_intersect($this->violence_types, $case->violence_types ?? []) !== [];
    }
}
