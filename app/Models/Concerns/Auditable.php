<?php

namespace App\Models\Concerns;

use App\Enums\AuditAction;
use App\Models\AuditLog;

/**
 * Registra criação/alteração/exclusão. Guarda apenas os NOMES dos campos
 * alterados — nunca valores — para que o log não vire vazamento de dado sigiloso.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($m) => AuditLog::record(AuditAction::Created, $m, $m->auditCaseId()));

        static::updated(function ($m) {
            $fields = array_keys(array_diff_key($m->getChanges(), ['updated_at' => 1, 'remember_token' => 1]));
            if ($fields) {
                AuditLog::record(AuditAction::Updated, $m, $m->auditCaseId(), ['fields' => $fields]);
            }
        });

        static::deleted(fn ($m) => AuditLog::record(AuditAction::Deleted, $m, $m->auditCaseId()));
    }

    public function auditCaseId(): ?int
    {
        return $this->case_id ?? null;
    }
}
