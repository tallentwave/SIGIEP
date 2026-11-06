<?php

namespace App\Models;

use App\Enums\SessionStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ListeningSession extends Model
{
    use Auditable, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => SessionStatus::class,
            'scheduled_at' => 'datetime',
            'performed_at' => 'datetime',
            'recorded' => 'boolean',
            'report' => 'encrypted',
            'technical_notes' => 'encrypted',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }
}
