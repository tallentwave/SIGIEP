<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Trilha de auditoria imutável: sem update/delete via aplicação. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['action' => AuditAction::class, 'changes' => 'array', 'created_at' => 'datetime'];
    }

    public static function record(AuditAction $action, ?Model $subject = null, ?int $caseId = null, array $changes = []): self
    {
        $user = auth()->user();
        $request = request();

        return static::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'case_id' => $caseId,
            'municipality_id' => $user?->municipality_id,
            'changes' => $changes ?: null,
            'ip' => $request?->ip(),
            'user_agent' => mb_substr((string) $request?->userAgent(), 0, 255),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Registros de auditoria são imutáveis.');
    }

    public function delete(): ?bool
    {
        throw new \LogicException('Registros de auditoria são imutáveis.');
    }
}
