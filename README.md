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
| **Segurança da conta** | Recuperação de senha por e-mail (link de 60 min, uso único, resposta idêntica exista o e-mail ou não, derruba sessões abertas) e **2FA por aplicativo autenticador** (TOTP) com códigos de recuperação. Obrigatório para Administrador, Coordenação, Psicólogo e Assistente Social; opcional para Recepção |
| **Auditoria** | Login/falhas, leitura de caso e relato, alterações (só nomes dos campos), exportações, acessos negados, denúncias públicas |

| **Retenção e anonimização** | Prazo de guarda por município (padrão provisório de 10 anos após o encerramento), **guarda legal** que suspende a anonimização, anonimização irreversível automática (opt-in) ou manual, e expurgo da trilha de auditoria antiga. Mantém só o estatístico |
| **SINAN** | Gera arquivo (CSV, UTF-8) com os **nomes de campo e códigos oficiais** da ficha de Violência Interpessoal/Autoprovocada (dicionário SINAN NET v5.1), prazo interno de notificação, avisos do que foi enviado como "Ignorado" e controle do nº de notificação devolvido |
| **Sistema de Justiça** | Processos vinculados ao caso com **validação do dígito verificador** (numeração única CNJ, Res. 65/2008) e comunicação ao Juízo em PDF com o mínimo necessário (sem o relato da escuta) |

### O que as integrações fazem — e o que não fazem

- **SINAN não tem API pública de envio.** A notificação é digitada/importada pela vigilância epidemiológica do município. O SIGIEP gera o arquivo mapeado para os campos oficiais; **a vigilância precisa conferir e carregar** (o CSV não é o arquivo de transferência nativo do SINAN NET). Campos sem dado na ficha saem como `9 – Ignorado` e aparecem como aviso. Para reduzir erro de digitação, cada caso também gera uma **ficha numerada em PDF**, na ordem do dicionário (campos 1 a 65), com o código a digitar ao lado de cada valor. As opções da ficha do SIGIEP seguem as categorias do SINAN (relação do provável autor, ciclo de vida do autor). Confirme com a vigilância apenas o nome da coluna `REL_IRMAO` (o dicionário a imprime com til).
- **CNJ/MNI não está ligado.** O Modelo Nacional de Interoperabilidade é um webservice por tribunal e exige adesão/credenciais (convênio). Sem isso não há como enviar, então o sistema **não finge** uma conexão: valida e registra o processo, gera o documento e controla o nº do ofício/protocolo. Quando um tribunal aderir, a integração entra sobre esses mesmos registros.
- Nenhum conteúdo gerado é guardado: só o *hash* e o status (o resumo ao Juízo é criptografado).

### Retenção: como operar

```bash
php artisan sigiep:retencao --dry-run   # simula, não altera nada
php artisan sigiep:retencao             # anonimiza o que venceu e expurga auditoria antiga
```

A execução automática (dia 1º, 03:00) só existe com `SIGIEP_RETENTION_AUTO=true`, porque a anonimização **não tem volta**. Prazos: `retention_years` em *Municípios* e `SIGIEP_AUDIT_RETENTION_YEARS` (padrão 5; 0 = nunca expurgar). **Valide os prazos com a assessoria jurídica e o encarregado (DPO)**: o padrão é provisório.

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

Em desenvolvimento, para não configurar 2FA a cada usuário demo, use `SIGIEP_2FA_REQUIRED=` (vazio) no `.env`.
O seeder **não** cadastra fluxo: cada município o insere em *Fluxo municipal*.

Usuários demo: `coordenacao@`, `psicologo@`, `social@`, `recepcao@` + `sigiep.test`.

## Produção: checklist

- `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `APP_KEY` com backup, banco MySQL/PostgreSQL.
- Configurar `MAIL_*` (em dev o e-mail vai para o log) e o cron do agendador: `* * * * * php artisan schedule:run`.
- **Não** rodar o seeder em produção; crie o primeiro administrador via `php artisan tinker`.
- 2FA: o padrão já é seguro; **não** defina `SIGIEP_2FA_REQUIRED` em produção. Quem perder o celular e os códigos pede ao administrador (Usuários → Editar → Redefinir 2FA).
- Revisar o fluxo de cada município em *Fluxo municipal* antes de abrir o canal público.

- Em *Municípios*, informe o código IBGE (validado pelo dígito verificador e pela UF) e a unidade notificadora do SINAN. A tela mostra o que falta, e o painel da coordenação avisa enquanto houver pendência.
- Teste `sigiep:retencao --dry-run` antes de ligar `SIGIEP_RETENTION_AUTO`.

## Pendente / próximos passos

Adesão ao MNI/CNJ por tribunal, carga direta no SINAN (se a vigilância fornecer o layout de transferência), consulta de andamento pelo denunciante (hoje deliberadamente inexistente por sigilo).
