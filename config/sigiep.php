<?php

use App\Enums\Role;

return [
    /*
    | Perfis obrigados a usar verificação em duas etapas (TOTP). Quem lê
    | conteúdo sigiloso ou administra o sistema não entra sem ela. A Recepção
    | pode ativar por conta própria.
    */
    // SIGIEP_2FA_REQUIRED: lista separada por vírgula (ex.: "psicologo,coordenacao"); vazio = ninguém
    // (só para desenvolvimento). Sem a variável, vale o padrão seguro abaixo.
    'two_factor_required_roles' => env('SIGIEP_2FA_REQUIRED') === null
        ? array_map(fn (Role $r) => $r->value, [
            Role::Administrador, Role::Coordenacao, Role::Psicologo, Role::AssistenteSocial,
        ])
        : array_values(array_filter(array_map('trim', explode(',', (string) env('SIGIEP_2FA_REQUIRED'))))),

    /*
    | Retenção. A anonimização é IRREVERSÍVEL, por isso só roda sozinha quando
    | SIGIEP_RETENTION_AUTO=true; sem isso, use `php artisan sigiep:retencao`
    | (com --dry-run para simular) ou a tela Retenção.
    | Os prazos por município (padrão provisório: 10 anos) e o da trilha de
    | auditoria (anos; 0 = nunca expurgar) devem ser validados com a
    | assessoria jurídica / encarregado (DPO).
    */
    'retention' => ['auto' => (bool) env('SIGIEP_RETENTION_AUTO', false)],
    'audit_retention_years' => (int) env('SIGIEP_AUDIT_RETENTION_YEARS', 5),

    /*
    | Prazos internos para notificar ao SINAN (dias após o recebimento do caso).
    | Violência sexual é de notificação imediata; as demais, semanal. CONFIRME
    | com a vigilância epidemiológica do município. 'agravo' é o CID-10 do campo
    | ID_AGRAVO da ficha de violência interpessoal/autoprovocada.
    */
    'sinan' => ['deadline_days' => ['sexual' => 1, 'default' => 7], 'agravo' => 'Y09'],
];
