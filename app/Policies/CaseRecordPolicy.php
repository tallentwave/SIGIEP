<?php

namespace App\Policies;

use App\Models\CaseRecord;
use App\Models\User;

class CaseRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active;
    }

    public function create(User $user): bool
    {
        return $user->active;
    }

    /** Acesso ao caso: coordenação, responsável, criador ou membro da equipe. */
    public function view(User $user, CaseRecord $case): bool
    {
        if (! $user->active) {
            return false;
        }

        return $user->seesAllCases()
            || $case->responsible_id === $user->id
            || $case->created_by === $user->id
            || $case->team()->where('users.id', $user->id)->exists();
    }

    public function update(User $user, CaseRecord $case): bool
    {
        return $this->view($user, $case);
    }

    /** Leitura do relato sigiloso: só quem faz a escuta (ou coordenação) COM acesso ao caso. */
    public function readReports(User $user, CaseRecord $case): bool
    {
        return $this->view($user, $case) && ($user->isTechnical() || $user->isCoordination());
    }

    /** Define equipe/responsável e encerra casos. */
    public function manage(User $user, CaseRecord $case): bool
    {
        return $user->active && $user->isCoordination();
    }
}
