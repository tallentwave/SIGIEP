<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('children')]
class Child extends Model
{
    use Auditable, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'birth_date_estimated' => 'boolean',
            'has_disability' => 'boolean',
            'anonymized_at' => 'datetime',
            'cpf' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        // Índice cego: permite achar por CPF exato sem guardar o número em claro.
        static::saving(fn (Child $c) => $c->cpf_hash = filled($c->cpf) ? static::cpfHash($c->cpf) : null);
    }

    /** HMAC do CPF só com dígitos (com ou sem pontuação gera o mesmo valor). */
    public static function cpfHash(string $cpf): string
    {
        return hash_hmac('sha256', preg_replace('/\D/', '', $cpf), (string) config('app.key'));
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CaseRecord::class);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }

    public function displayName(): string
    {
        return $this->social_name ?: $this->name;
    }
}
