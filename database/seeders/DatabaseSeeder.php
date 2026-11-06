<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Usuários de DESENVOLVIMENTO. Nunca rodar em produção: senha conhecida.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Seeder de demonstração bloqueado em produção.');

            return;
        }

        foreach ([
            ['Coordenação Demo', 'coordenacao@sigiep.test', Role::Coordenacao, null],
            ['Psicóloga Demo', 'psicologo@sigiep.test', Role::Psicologo, 'CRP 00/00000'],
            ['Assistente Social Demo', 'social@sigiep.test', Role::AssistenteSocial, 'CRESS 00/00000'],
            ['Recepção Demo', 'recepcao@sigiep.test', Role::Recepcao, null],
        ] as [$name, $email, $role, $registro]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name, 'password' => 'password', 'role' => $role,
                'registro_profissional' => $registro, 'active' => true,
            ]);
        }
    }
}
