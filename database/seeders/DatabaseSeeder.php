<?php

namespace Database\Seeders;

use App\Enums\ReferralDestination;
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

        if ($m->flowSteps()->doesntExist()) {
            // Exemplo de fluxo municipal; cada município define o seu em Fluxo municipal.
            foreach ([
                ['Comunicar Conselho Tutelar', ReferralDestination::ConselhoTutelar, 1, null, false],
                ['Encaminhar à saúde (violência sexual)', ReferralDestination::Saude, 1, ['sexual'], false],
                ['Acompanhamento no CREAS', ReferralDestination::Creas, 15, null, false],
                ['Comunicar Delegacia (caso urgente)', ReferralDestination::Policia, 1, null, true],
            ] as $i => [$name, $dest, $days, $types, $urgent]) {
                $m->flowSteps()->create(['position' => $i + 1, 'name' => $name, 'destination' => $dest,
                    'due_days' => $days, 'violence_types' => $types, 'only_urgent' => $urgent]);
            }
        }

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
