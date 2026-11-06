<?php

namespace App\Services;

use App\Enums\ReferralStatus;
use App\Models\CaseRecord;

class FlowService
{
    /**
     * Gera os encaminhamentos pendentes definidos pelo fluxo do município do caso.
     * Idempotente: não duplica uma etapa já gerada para o mesmo caso.
     */
    public function apply(CaseRecord $case): int
    {
        $created = 0;
        $existing = $case->referrals()->pluck('flow_step_id')->filter()->all();

        foreach ($case->municipality->flowSteps()->where('active', true)->get() as $step) {
            if (in_array($step->id, $existing, true) || ! $step->appliesTo($case)) {
                continue;
            }
            $case->referrals()->create([
                'flow_step_id' => $step->id,
                'destination' => $step->destination,
                'destination_detail' => $step->destination_detail ?: $step->name,
                'status' => ReferralStatus::Pendente,
                'due_at' => $case->received_at->copy()->addDays($step->due_days),
            ]);
            $created++;
        }

        return $created;
    }
}
