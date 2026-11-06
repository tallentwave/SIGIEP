<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Ibge;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipality extends Model
{
    use Auditable, HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'accepts_public_reports' => 'boolean'];
    }

    public function flowSteps(): HasMany
    {
        return $this->hasMany(FlowStep::class)->orderBy('position')->orderBy('id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CaseRecord::class);
    }

    /**
     * O que falta configurar para o município operar de ponta a ponta.
     *
     * @return array<string, bool>
     */
    public function readiness(): array
    {
        return [
            'Código IBGE válido' => filled($this->ibge_code) && Ibge::isValid($this->ibge_code),
            'Unidade notificadora do SINAN' => filled($this->sinan_unit_name),
            'Fluxo de atendimento (ao menos 1 etapa ativa)' => $this->flowSteps()->where('active', true)->exists(),
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function fullName(): string
    {
        return "{$this->name}/{$this->uf}";
    }
}
