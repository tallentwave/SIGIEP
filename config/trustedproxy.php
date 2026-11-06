<?php

return [
    /*
    | Proxies/balanceadores confiáveis (IPs ou CIDR separados por vírgula; "*" confia em quem chama).
    | Lido pelo próprio framework a cada requisição. Declarado aqui (e não com env() solto no código)
    | porque, com `php artisan config:cache`, env() fora de config/ devolve NULL.
    */
    'proxies' => env('TRUSTED_PROXIES'),
];
