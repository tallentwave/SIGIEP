<?php

namespace App\Policies;

use App\Models\CaseRecord;
use App\Models\User;

class CaseRecordPolicy
{
    private function member(User $user): bool
    {
        return $user->active && ! $user->isAdmin() && $user->municipality_id !== null;
    }

    public function viewAny(User $user): bool
    {
        return $this->member($user);
    }

    public function create(User $user): bool
    {
        return $this->member($user);
    }

    public function view(User $user, CaseRecord $case): bool
    {
        if (! $this->member($user) || $case->municipality_id !== $user->municipality_id) {
            return false;
        }

        return $user->seesAllCases()
            || $case->responsible_id === null // fila do município
            || $case->responsible_id === $user->id
            || $case->created_by === $user->id
            || $case->team()->where('users.id', $user->id)->exists();
    }

    /** Caso anonimizado é somente leitura (só estatística). */
    public function update(User $user, CaseRecord $case): bool
    {
        return ! $case->isAnonymized() && $this->view($user, $case);
    }

    /** Conteúdo sigiloso (ficha, relatos): profissional técnico ou coordenação com acesso. */
    public function readReports(User $user, CaseRecord $case): bool
    {
        return $this->view($user, $case) && ($user->isTechnical() || $user->isCoordination());
    }

    /** Profissional técnico assume caso da fila. */
    public function claim(User $user, CaseRecord $case): bool
    {
        return $this->view($user, $case) && $user->isTechnical() && $case->isInQueue();
    }

    /** Define equipe/responsável, situação e fluxo. */
    public function manage(User $user, CaseRecord $case): bool
    {
        return ! $case->isAnonymized() && $this->member($user) && $user->isCoordination()
            && $case->municipality_id === $user->municipality_id;
    }

    /** Guarda legal e anonimização manual: coordenação do município, caso ainda não anonimizado. */
    public function retain(User $user, CaseRecord $case): bool
    {
        return $this->manage($user, $case);
    }
}
