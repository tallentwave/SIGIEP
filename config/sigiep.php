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
];
