# SIGIEP — Sistema de Escuta Especializada

Sistema para registrar e acompanhar a **escuta especializada** de crianças e adolescentes
vítimas ou testemunhas de violência (Lei 13.431/2017). Laravel 13 · PHP 8.3 · SQLite (dev) / MySQL ou PostgreSQL (prod).

## Módulos

| Módulo | O que faz |
|---|---|
| **Municípios e fluxo** | Cada município tem usuários, casos, canal público e **fluxo próprio**: etapas (destino, prazo de retorno, filtro por tipo de violência / só urgentes) que viram encaminhamentos pendentes ao cadastrar o caso |
| **Denúncia pública** | `/denuncia/{município}`: formulário sem login, estilo ficha SINAN, anônimo opcional, honeypot e limite de 5 envios/min por IP. Entra na **fila** do município e avisa a coordenação (e-mail sem dados pessoais) |
| **Ficha de notificação** | Local/zona/reincidência, meio de agressão, tipo de violência sexual, perfil do provável autor, raça/cor, notificante. Mesmos campos no formulário interno e no público |
| **Cadastro** | Criança/adolescente (com histórico: vários casos na mesma criança), responsáveis ("suposto autor", "não contatar"), protocolo `SIG-AAAA-NNNNN` |
| **Escuta** | Agendamento com bloqueio de conflito (profissional e sala, dentro do município), relato e observações técnicas. **Sem anexos nem gravações** |
| **Rede de proteção** | Encaminhamentos com prazo, retorno e alerta de atraso |
| **Administração** | Usuários, municípios e fluxos pela tela; senha provisória com troca obrigatória no 1º acesso |
| **Estatísticas e PDF** | Indicadores agregados por período/município (tipo, faixa etária, situação, mês, órgão, tempo até 1ª escuta, retorno de encaminhamentos), PDF estatístico e PDF individual do caso |
| **Alertas** | `php artisan sigiep:alertas` (agendado 07:30): encaminhamentos atrasados, escutas das próximas 24h, casos parados na fila. Só protocolos por e-mail |
| **Auditoria** | Login/falhas, leitura de caso e relato, alterações (só nomes dos campos), exportações, acessos negados, denúncias públicas |

## Quem vê o quê

| Perfil | Casos | Conteúdo sigiloso (ficha, relatos) | Outros |
|---|---|---|---|
| Administrador | **nenhum** | não | usuários, municípios, fluxos, estatísticas e auditoria globais |
| Coordenação | todos do município | sim | gestão do caso, fluxo do município, estatísticas e auditoria do município |
| Psicólogo / Assistente Social | os seus (responsável/equipe/criador) + **fila** do município, que pode assumir | nos casos a que tem acesso | |
| Recepção | os que cadastrou + fila | **não** (nem edita) | cadastra e agenda |

Nenhum perfil enxerga outro município.

## Sigilo

- Relato inicial, ficha, relato da escuta, observações técnicas e resumos de encaminhamento são **criptografados em repouso** (`APP_KEY`: faça backup, sem ela os dados são irrecuperáveis).
- A auditoria guarda só os *nomes* dos campos alterados e é imutável pela aplicação; exclusões são lógicas.
- E-mails de alerta nunca levam nome, relato ou ficha.

## Rodando

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed   # só dev: cria usuários demo (senha "password")
php artisan serve
php artisan test
```

Usuários demo: `coordenacao@`, `psicologo@`, `social@`, `recepcao@` + `sigiep.test`.

## Produção: checklist

- `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `APP_KEY` com backup, banco MySQL/PostgreSQL.
- Configurar `MAIL_*` (em dev o e-mail vai para o log) e o cron do agendador: `* * * * * php artisan schedule:run`.
- **Não** rodar o seeder em produção; crie o primeiro administrador via `php artisan tinker`.
- Revisar o fluxo de cada município em *Fluxo municipal* antes de abrir o canal público.

## Pendente / próximos passos

Recuperação de senha por e-mail, 2FA para perfis técnicos, política de retenção/anonimização (LGPD),
integração SINAN/CNJ, consulta de andamento pelo denunciante (hoje deliberadamente inexistente por sigilo).
