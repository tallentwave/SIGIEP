<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Enums\ReferralStatus;
use App\Enums\SessionStatus;
use App\Models\CaseRecord;
use App\Models\ListeningSession;
use App\Models\Referral;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();
        $cases = CaseRecord::visibleTo($user);

        $visibleIds = (clone $cases)->select('cases.id');

        return view('dashboard', [
            'open' => (clone $cases)->where('status', '!=', CaseStatus::Encerrado)->count(),
            'urgent' => (clone $cases)->where('urgent', true)->where('status', '!=', CaseStatus::Encerrado)->count(),
            'awaiting' => (clone $cases)->where('status', CaseStatus::Recebido)->count(),
            'upcoming' => ListeningSession::with('case.child', 'professional')
                ->whereIn('case_id', $visibleIds)
                ->where('status', SessionStatus::Agendada)
                ->where('scheduled_at', '>=', now()->startOfDay())
                ->orderBy('scheduled_at')->limit(8)->get(),
            'overdueReferrals' => Referral::with('case.child')
                ->whereIn('case_id', $visibleIds)
                ->whereNotIn('status', [ReferralStatus::ComRetorno->value, ReferralStatus::Pendente->value])
                ->whereDate('due_at', '<', now())
                ->orderBy('due_at')->limit(8)->get(),
        ]);
    }
}
