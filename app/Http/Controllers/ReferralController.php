<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Enums\ReferralDestination;
use App\Enums\ReferralStatus;
use App\Models\CaseRecord;
use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReferralController extends Controller
{
    public function store(Request $request, CaseRecord $case)
    {
        $this->authorize('update', $case);

        $data = $request->validate([
            'destination' => ['required', Rule::enum(ReferralDestination::class)],
            'destination_detail' => ['nullable', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'sent_at' => ['nullable', 'date', 'before_or_equal:today'],
            'due_at' => ['nullable', 'date', 'after_or_equal:sent_at'],
            'summary' => ['nullable', 'string', 'max:5000'],
        ]);

        $case->referrals()->create([
            ...$data,
            'status' => filled($data['sent_at'] ?? null) ? ReferralStatus::Enviado : ReferralStatus::Pendente,
            'created_by' => $request->user()->id,
        ]);

        if (in_array($case->status, [CaseStatus::Recebido, CaseStatus::EmEscuta], true)) {
            $case->update(['status' => CaseStatus::Encaminhado]);
        }

        return back()->with('ok', 'Encaminhamento registrado.');
    }

    public function update(Request $request, Referral $referral)
    {
        $this->authorize('update', $referral->case);

        $data = $request->validate([
            'status' => ['required', Rule::enum(ReferralStatus::class)],
            'sent_at' => ['nullable', 'date'],
            'returned_at' => ['nullable', 'date'],
            'return_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $status = ReferralStatus::from($data['status']);
        if ($status === ReferralStatus::ComRetorno && blank($data['returned_at'] ?? null)) {
            $data['returned_at'] = now()->toDateString();
        }
        if ($status === ReferralStatus::Enviado && blank($data['sent_at'] ?? null) && ! $referral->sent_at) {
            $data['sent_at'] = now()->toDateString();
        }

        $referral->update($data);

        return back()->with('ok', 'Encaminhamento atualizado.');
    }
}
