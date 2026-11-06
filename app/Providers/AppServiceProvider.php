<?php

namespace App\Providers;

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
        Gate::define('viewAudit', fn (User $u) => $u->active && $u->isCoordination());
    }
}
