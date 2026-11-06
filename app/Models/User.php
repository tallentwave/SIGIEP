<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'registro_profissional', 'active', 'municipality_id', 'must_change_password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'active' => 'boolean',
            'must_change_password' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    /** Perfis que leem conteúdo sigiloso ou administram o sistema precisam de 2FA. */
    public function requiresTwoFactor(): bool
    {
        return in_array($this->role->value, config('sigiep.two_factor_required_roles', []), true);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Administrador;
    }

    public function isCoordination(): bool
    {
        return $this->role === Role::Coordenacao;
    }

    public function isTechnical(): bool
    {
        return $this->role->isTechnical();
    }

    /** Coordenação vê todos os casos do PRÓPRIO município. */
    public function seesAllCases(): bool
    {
        return $this->isCoordination();
    }
}
