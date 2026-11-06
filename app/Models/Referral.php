<?php

namespace App\Models;

use App\Enums\ReferralDestination;
use App\Enums\ReferralStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Referral extends Model
{
    use Auditable, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'destination' => ReferralDestination::class,
            'status' => ReferralStatus::class,
            'sent_at' => 'date',
            'due_at' => 'date',
            'returned_at' => 'date',
            'summary' => 'encrypted',
            'return_notes' => 'encrypted',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    /** Enviado e sem retorno após o prazo. */
    public function isOverdue(): bool
    {
        return $this->due_at
            && $this->due_at->isPast()
            && in_array($this->status, [ReferralStatus::Enviado, ReferralStatus::Recebido, ReferralStatus::SemRetorno], true);
    }
}
