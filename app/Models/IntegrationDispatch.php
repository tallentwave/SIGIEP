<?php

namespace App\Models;

use App\Enums\DispatchStatus;
use App\Enums\DispatchSystem;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationDispatch extends Model
{
    use Auditable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'system' => DispatchSystem::class,
            'status' => DispatchStatus::class,
            'summary' => 'encrypted',
            'sent_at' => 'date',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(JudicialProcess::class, 'judicial_process_id');
    }
}
