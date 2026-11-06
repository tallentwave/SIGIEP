<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Dados de DESENVOLVIMENTO. Nunca rodar em produção: senha conhecida.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Seeder de demonstração bloqueado em produção.');

            return;
        }

        $m = Municipality::firstOrCreate(['slug' => 'demonstracao-sp'], [
            'name' => 'Demonstração', 'uf' => 'SP', 'contact_phone' => '(00) 0000-0000',
        ]);

        // O fluxo de atendimento NÃO é semeado: cada município o cadastra em "Fluxo municipal".

        foreach ([
            ['Administrador Demo', 'admin@sigiep.test', Role::Administrador, null, null],
            ['Coordenação Demo', 'coordenacao@sigiep.test', Role::Coordenacao, $m->id, null],
            ['Psicóloga Demo', 'psicologo@sigiep.test', Role::Psicologo, $m->id, 'CRP 00/00000'],
            ['Assistente Social Demo', 'social@sigiep.test', Role::AssistenteSocial, $m->id, 'CRESS 00/00000'],
            ['Recepção Demo', 'recepcao@sigiep.test', Role::Recepcao, $m->id, null],
        ] as [$name, $email, $role, $mid, $registro]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name, 'password' => 'password', 'role' => $role, 'municipality_id' => $mid,
                'registro_profissional' => $registro, 'active' => true, 'must_change_password' => false,
            ]);
        }
    }
}
