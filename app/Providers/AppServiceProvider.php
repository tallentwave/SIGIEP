<?php

namespace App\Providers;

use App\Models\Municipality;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // CaseRecordPolicy é descoberta por convenção (App\Policies\{Model}Policy).
        Gate::define('admin', fn (User $u) => $u->active && $u->isAdmin());
        Gate::define('viewAudit', fn (User $u) => $u->active && ($u->isAdmin() || $u->isCoordination()));
        Gate::define('viewStats', fn (User $u) => $u->active && ($u->isAdmin() || $u->isCoordination()));
        // Fluxo do município: administrador (qualquer) ou coordenação do próprio município.
        Gate::define('manageFlow', fn (User $u, Municipality $m) => $u->active
            && ($u->isAdmin() || ($u->isCoordination() && $u->municipality_id === $m->id)));
    }
}
