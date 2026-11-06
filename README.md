# SIGIEP — Sistema de Escuta Especializada

Sistema para registrar e acompanhar a **escuta especializada** de crianças e adolescentes
vítimas ou testemunhas de violência (Lei 13.431/2017). Laravel 13 · PHP 8.3 · SQLite (dev) / MySQL ou PostgreSQL (prod).

## Módulos do MVP

| Módulo | O que faz |
|---|---|
| Cadastro | Criança/adolescente, responsáveis (com marcação de *suposto autor* / *não contatar*), caso com protocolo `SIG-AAAA-NNNNN`, tipos de violência, órgão demandante, urgência |
| Escuta | Agendamento com bloqueio de conflito (profissional **e** sala), registro da escuta, gravação audiovisual e local de custódia |
| Rede de proteção | Encaminhamentos (Conselho Tutelar, CREAS, saúde, MP, polícia…) com prazo de retorno e alerta de atraso |
| Perfis e sigilo | Coordenação, Psicólogo(a), Assistente Social, Recepção; acesso **por caso** (responsável, criador ou equipe) |
| Auditoria | Login, falhas, visualização de caso, leitura de relato, criação/alteração/exclusão e acessos negados |

## Regras de sigilo implementadas

- Relato inicial, relato da escuta, observações técnicas e resumos/retornos de encaminhamento são **criptografados em repouso** (`APP_KEY`; faça backup da chave — sem ela os relatos são irrecuperáveis).
- Recepção agenda, mas **não lê nem grava** o conteúdo da escuta.
- A auditoria guarda só os *nomes* dos campos alterados, nunca valores; registros são imutáveis pela aplicação.
- Acesso negado a caso/relato é registrado.
- Exclusões são lógicas (*soft delete*).

## Rodando

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed   # só dev: cria usuários demo (senha "password")
php artisan serve
php artisan test
```

Usuários demo: `coordenacao@`, `psicologo@`, `social@`, `recepcao@` + `sigiep.test`.

## Pendente / próximos passos

Cadastro de usuários pela interface, recuperação de senha, anexos/mídia, relatórios (PDF/estatísticos),
notificações de prazo, vínculo de múltiplos casos por criança, integração SINAN/CNJ e política de retenção (LGPD).
