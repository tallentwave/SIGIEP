<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('sigiep:criar-admin {email : E-mail do administrador} {--nome= : Nome completo}')]
#[Description('Cria o primeiro administrador do sistema (senha digitada oculta, troca obrigatória no 1º acesso)')]
class CriarAdministrador extends Command
{
    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        $nome = $this->option('nome') ?: $this->ask('Nome completo');

        $v = Validator::make(['email' => $email, 'nome' => $nome], ['email' => ['required', 'email'], 'nome' => ['required', 'string', 'max:255']]);
        if ($v->fails()) {
            $this->error($v->errors()->first());

            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error("Já existe um usuário com o e-mail {$email}.");

            return self::FAILURE;
        }

        // Digitada oculta: não fica no histórico do shell nem em log de processos.
        $senha = $this->secret('Senha (não aparece na tela)');
        if ($senha !== $this->secret('Repita a senha')) {
            $this->error('As senhas não conferem.');

            return self::FAILURE;
        }
        $check = Validator::make(['password' => $senha], ['password' => ['required', Password::min(10)->letters()->numbers()]]);
        if ($check->fails()) {
            $this->error('Senha fraca: use ao menos 10 caracteres, com letras e números.');

            return self::FAILURE;
        }

        User::create([
            'name' => $nome, 'email' => $email, 'password' => $senha, 'role' => Role::Administrador,
            'active' => true, 'municipality_id' => null, 'must_change_password' => true,
        ]);

        $this->info("Administrador {$email} criado. No primeiro acesso ele define uma nova senha e configura o 2FA (obrigatório).");

        return self::SUCCESS;
    }
}
