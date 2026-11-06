<?php

namespace App\Models;

use App\Enums\CaseStatus;
use App\Enums\Condition;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

#[Table('cases')]
class CaseRecord extends Model
{
    use Auditable, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => CaseStatus::class,
            'condition' => Condition::class,
            'violence_types' => 'array',
            'received_at' => 'date',
            'violence_date' => 'date',
            'closed_at' => 'datetime',
            'urgent' => 'boolean',
            'legal_hold' => 'boolean',
            'legal_hold_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'initial_report' => 'encrypted',
            'ficha' => 'encrypted:array',
        ];
    }

    public function auditCaseId(): ?int
    {
        return $this->getKey();
    }

    /** Protocolo sequencial por ano: SIG-2026-00001. */
    public static function nextProtocol(): string
    {
        return DB::transaction(function () {
            $year = now()->year;
            $prefix = "SIG-{$year}-";
            $last = static::withTrashed()->where('protocol', 'like', $prefix.'%')
                ->lockForUpdate()->max('protocol');
            $seq = $last ? ((int) substr($last, -5)) + 1 : 1;

            return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Cria o caso com protocolo sequencial. Se outro cadastro simultâneo pegar o mesmo
     * número (a coluna é única), tenta de novo com o próximo em vez de falhar com erro 500.
     * A tentativa roda em savepoint para não abortar uma transação externa (PostgreSQL).
     */
    public static function createWithProtocol(array $attributes, int $tries = 5): static
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn () => static::create([...$attributes, 'protocol' => static::nextProtocol()]));
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= $tries) {
                    throw $e;
                }
            }
        }
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function team(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'case_user', 'case_id', 'user_id')->withTimestamps();
    }

    public function processes(): HasMany
    {
        return $this->hasMany(JudicialProcess::class, 'case_id');
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(IntegrationDispatch::class, 'case_id');
    }

    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ListeningSession::class, 'case_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'case_id');
    }

    /**
     * Casos que o usuário pode ver:
     *  - Administrador: nenhum (gerencia o sistema, não lê casos);
     *  - Coordenação: todos do seu município;
     *  - Demais: do seu município onde é responsável/criador/equipe, mais a FILA
     *    (casos sem responsável), que podem ser assumidos.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || ! $user->municipality_id) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('cases.municipality_id', $user->municipality_id);

        if ($user->seesAllCases()) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->whereNull('cases.responsible_id')
            ->orWhere('cases.responsible_id', $user->id)
            ->orWhere('cases.created_by', $user->id)
            ->orWhereHas('team', fn (Builder $t) => $t->where('users.id', $user->id)));
    }

    public function isInQueue(): bool
    {
        return $this->responsible_id === null && $this->status !== CaseStatus::Encerrado;
    }
}
