# Guia de implantação do SIGIEP

Para quem vai colocar o sistema no ar e para a coordenação que vai colocá-lo em uso.
Cada comando abaixo foi conferido contra o código (instalação sem dependências de desenvolvimento, caches, migração, criação do administrador e verificação de produção).

> Este guia é técnico e operacional. **Não é parecer jurídico.** Os pontos marcados com ⚖️ precisam ser decididos e validados pela assessoria jurídica e pelo encarregado de dados (DPO) do município.

## Sumário

1. [Antes de começar: decisões e responsáveis](#1-antes-de-começar-decisões-e-responsáveis)
2. [Requisitos](#2-requisitos)
3. [Instalação no servidor](#3-instalação-no-servidor)
4. [Configuração inicial dentro do sistema](#4-configuração-inicial-dentro-do-sistema)
5. [Teste ponta a ponta (antes de abrir ao público)](#5-teste-ponta-a-ponta-antes-de-abrir-ao-público)
6. [Treinamento da equipe](#6-treinamento-da-equipe)
7. [Rotina de operação](#7-rotina-de-operação)
8. [Backup e restauração](#8-backup-e-restauração)
9. [Atualizações](#9-atualizações)
10. [Incidente de segurança](#10-incidente-de-segurança)
11. [Checklist de entrada em produção](#11-checklist-de-entrada-em-produção)
12. [Problemas comuns](#12-problemas-comuns)

---

## 1. Antes de começar: decisões e responsáveis

Reúna estas pessoas **antes** de instalar. Boa parte da implantação é decisão, não tecnologia.

| Quem | Decide / fornece |
|---|---|
| **Coordenação do serviço** | Fluxo de atendimento do município (quem recebe encaminhamento e em quantos dias), quem são os usuários e seus perfis, metas de prazo |
| **Vigilância epidemiológica** | Unidade notificadora do SINAN (tipo, nome, código), como receberá o arquivo/ficha, prazos de notificação ⚖️ |
| **Encarregado de dados (DPO) e jurídico** ⚖️ | Base legal do tratamento, **prazo de guarda** (o padrão de 10 anos é provisório), regras de guarda legal, relatório de impacto (RIPD), termo de responsabilidade dos usuários |
| **TI** | Servidor, banco, HTTPS, e-mail, backup, atualização |
| **Gestão do município** | Quem responde por incidentes e pela comunicação oficial |

Levante também: o **código IBGE de 7 dígitos** do município, o **telefone** que aparecerá no formulário público, e a **lista de usuários** (nome, e-mail institucional, perfil, registro profissional CRP/CRESS).

### Perfis (resumo)

| Perfil | Vê | Faz |
|---|---|---|
| **Administrador** | Nada de caso (só configuração) | Cria municípios, usuários, fluxos; redefine 2FA; define prazo de guarda |
| **Coordenação** | Todos os casos do município | Distribui casos, define prazos e fluxo, estatísticas, auditoria, retenção, integrações |
| **Psicólogo / Assistente Social** | Os casos que atende + a lista de espera | Assume caso, agenda e registra a escuta, encaminha, gera ficha SINAN e comunicação ao Juízo |
| **Recepção** | Casos que cadastrou + lista de espera, **sem** o conteúdo sigiloso | Cadastra e agenda |

---

## 2. Requisitos

**Servidor** (a carga é pequena; 2 vCPU / 4 GB atendem um município de porte médio):

- Linux com **PHP 8.3 ou superior** e as extensões `mbstring`, `xml`, `dom`, `curl`, `zip`, `pdo` e o driver do seu banco (`pdo_mysql` ou `pdo_pgsql`).
- **MySQL 8+ ou PostgreSQL 14+.** Não use SQLite em produção.
- **Nginx (ou Apache) + PHP-FPM**, com **certificado HTTPS** válido. O sistema só deve ser acessado por HTTPS.
- **Composer 2** (somente no servidor ou na máquina de build).
- **Servidor SMTP** do órgão. Ele envia o link de redefinição de senha, os alertas diários e o aviso de nova denúncia.
- **Cron** (para o agendador).
- Disco do servidor e dos backups **criptografados**. O sistema criptografa relatos e CPF dentro do banco, mas nome da criança, nome da mãe e endereço ficam em texto no banco; a proteção deles depende do disco e do acesso ao banco.

**Acesso ao banco:** crie um usuário **exclusivo** do SIGIEP, com permissão apenas no banco do sistema, e **sem acesso de rede aberto** (só o servidor da aplicação).

---

## 3. Instalação no servidor

### 3.1 Código

```bash
cd /var/www
git clone https://github.com/tallentwave/SIGIEP sigiep
cd sigiep
git checkout <tag-ou-branch-aprovada>
composer install --no-dev --optimize-autoloader --no-interaction
```

### 3.2 Banco de dados

MySQL (exemplo):

```sql
CREATE DATABASE sigiep CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sigiep'@'localhost' IDENTIFIED BY '<senha-forte-e-unica>';
GRANT ALL PRIVILEGES ON sigiep.* TO 'sigiep'@'localhost';
```

### 3.3 Configuração (`.env`)

```bash
cp .env.example .env
php artisan key:generate --force
```

Edite o `.env`. Valores de produção:

```ini
APP_NAME=SIGIEP
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sigiep.seumunicipio.gov.br
APP_LOCALE=pt_BR
# Só se o município não usa o horário de Brasília (ex.: America/Manaus, America/Rio_Branco, America/Cuiaba)
# APP_TIMEZONE=America/Sao_Paulo

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sigiep
DB_USERNAME=sigiep
DB_PASSWORD=<a mesma senha do passo 3.2>

SESSION_DRIVER=database
SESSION_LIFETIME=30
SESSION_SECURE_COOKIE=true

MAIL_MAILER=smtp
MAIL_HOST=smtp.seuorgao.gov.br
MAIL_PORT=587
MAIL_USERNAME=<usuario>
MAIL_PASSWORD=<senha>
MAIL_FROM_ADDRESS=sigiep@seuorgao.gov.br
MAIL_FROM_NAME="SIGIEP"

# Logs diários, só avisos e erros, guardados por pouco tempo (ver nota abaixo)
LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14

# Atrás de balanceador/proxy reverso: IPs ou CIDR dele (ou * se o servidor só é acessível pelo proxy)
# TRUSTED_PROXIES=10.0.0.5

# Retenção
SIGIEP_AUDIT_RETENTION_YEARS=5
SIGIEP_RETENTION_AUTO=false
```

**Não** defina `SIGIEP_2FA_REQUIRED` em produção: o padrão já exige 2FA de administrador, coordenação, psicólogo e assistente social.

> ⚠️ **Guarde a `APP_KEY` em local seguro e separado do banco** (cofre de senhas do órgão). Sem ela os relatos, fichas e CPFs criptografados **ficam irrecuperáveis**, inclusive nos backups.

> ⚠️ **Logs:** uma falha de banco pode registrar no log trechos da consulta com dados pessoais. Restrinja o acesso à pasta `storage/logs`, mantenha poucos dias de retenção (`LOG_DAILY_DAYS`) e não envie esses arquivos a terceiros sem revisar.

### 3.4 Banco, caches e permissões

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R u+rwX,g+rwX storage bootstrap/cache
```

> Depois de **qualquer** mudança no `.env`, rode `php artisan config:cache` de novo. Com o cache ligado, o `.env` não é relido.

**Não rode `php artisan db:seed` em produção.** O seeder cria usuários de demonstração com senha conhecida e se recusa a rodar com `APP_ENV=production`.

### 3.5 Servidor web (Nginx)

O `root` deve apontar para a pasta `public/`, **nunca** para a raiz do projeto:

```nginx
server {
    listen 443 ssl;
    server_name sigiep.seumunicipio.gov.br;
    ssl_certificate     /etc/ssl/sigiep/fullchain.pem;
    ssl_certificate_key /etc/ssl/sigiep/privkey.pem;

    root /var/www/sigiep/public;
    index index.php;
    client_max_body_size 2m;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
    location ~ /\.(?!well-known) { deny all; }
}
server { listen 80; server_name sigiep.seumunicipio.gov.br; return 301 https://$host$request_uri; }
```

### 3.6 Agendador (alertas e retenção)

Uma linha no cron do usuário do servidor web:

```cron
* * * * * cd /var/www/sigiep && php artisan schedule:run >> /dev/null 2>&1
```

Ela dispara os alertas diários às 7h30 (`sigiep:alertas`). A anonimização só entra no agendamento se `SIGIEP_RETENTION_AUTO=true` (ver seção 7).

### 3.7 Primeiro administrador

```bash
php artisan sigiep:criar-admin admin@seuorgao.gov.br --nome="Nome Completo"
```

O comando pede a senha **sem mostrá-la na tela** (mínimo 10 caracteres, letras e números). No primeiro acesso o administrador troca a senha e configura o 2FA.

### 3.8 Verificação final do servidor

```bash
php artisan sigiep:verificar --strict
```

Todos os itens críticos devem aparecer com ✓ (debug desligado, HTTPS, cookie seguro, e-mail real, 2FA obrigatório, banco que não é SQLite). O comando termina com erro se algo crítico estiver errado, então serve também no deploy automatizado. Verifique ainda que `https://seu-endereco/up` responde (verificação de saúde).

---

## 4. Configuração inicial dentro do sistema

Faça nesta ordem, logado como **Administrador** (passos 1 a 4) e depois pela **Coordenação**.

1. **Entrar, trocar a senha e ativar o 2FA** (aplicativo autenticador). Guarde os 8 códigos de recuperação em local seguro; eles aparecem uma única vez.
2. **Municípios → Novo município.** Informe nome, UF, **código IBGE** (o sistema valida o dígito verificador e se combina com a UF), telefone de contato, **anos de guarda** ⚖️ e a **unidade notificadora do SINAN** (tipo, nome e código). Deixe "recebe denúncias pelo formulário público" **desmarcado** até o teste da seção 5.
3. **Usuários → Novo usuário**, nesta ordem: coordenação do município, profissionais (psicólogo e assistente social, com registro CRP/CRESS), recepção. Defina uma **senha provisória** para cada um e entregue por canal seguro (nunca por e-mail junto com o login). Cada pessoa troca a senha e configura o 2FA no primeiro acesso.
4. **Perfil de cada pessoa:** confirme que cada um tem município e perfil corretos. O Administrador não vê casos.
5. **Fluxo municipal** (Coordenação → *Fluxo municipal*): cadastre as etapas que viram encaminhamentos automáticos. Para cada uma: nome, destino (Conselho Tutelar, CREAS, saúde, MP, delegacia…), unidade do município, prazo de retorno em dias e, se for o caso, só para certos tipos de violência ou só para casos urgentes. Exemplo de raciocínio: *toda violência* → comunicar o Conselho Tutelar em 1 dia; *violência sexual* → encaminhar à saúde em 1 dia; *urgente* → comunicar a delegacia em 1 dia.
6. **Prazos** (Coordenação → *Prazos*): notificação ao SINAN (violência sexual × demais; padrão 1 e 7 dias, confirme com a vigilância ⚖️), meta da 1ª escuta (padrão 7 dias) e dias na fila até o alerta (padrão 3).
7. **Conferência:** o painel da coordenação mostra "Configuração do município pendente" enquanto faltar IBGE, unidade do SINAN ou fluxo. Só avance quando o aviso sumir.

---

## 5. Teste ponta a ponta (antes de abrir ao público)

Use **dados fictícios**. Execute com pessoas reais de cada perfil.

| # | Teste | Resultado esperado |
|---|---|---|
| 1 | Recepção cadastra um caso fictício | Protocolo `SIG-AAAA-00001`; encaminhamentos pendentes gerados pelo fluxo |
| 2 | Recepção abre o caso | **Não** vê relato inicial nem ficha |
| 3 | Coordenação abre a *Lista de espera* | O caso aparece; atribui a um profissional; o profissional recebe e-mail com o protocolo |
| 4 | Profissional agenda a escuta | Caso sai da lista de espera; tentar marcar o mesmo horário/sala é recusado |
| 5 | Profissional registra o relato da escuta | Coordenação consegue ver na *Auditoria* a leitura do relato |
| 6 | Outro profissional (sem acesso) tenta abrir o caso por endereço | Acesso negado (aparece na auditoria) |
| 7 | Gerar **ficha SINAN (PDF)** e **arquivo CSV** | Sem pendências obrigatórias; a vigilância confirma que consegue usar |
| 8 | Vincular um número de processo CNJ válido e gerar a comunicação ao Juízo | PDF sem o relato da escuta |
| 9 | **Esqueci minha senha** com um e-mail real | Chega o e-mail; o link funciona uma vez |
| 10 | Marcar *recebe denúncias* no município e enviar uma denúncia pelo formulário público | Protocolo exibido; a coordenação recebe e-mail; o caso entra na lista de espera |
| 11 | Perder o celular (simulado): administrador redefine o 2FA | Usuário reconfigura no próximo acesso |
| 12 | `php artisan sigiep:retencao --dry-run` | Lista o que seria anonimizado (nada, no início); não altera dados |
| 13 | Restaurar um backup em ambiente de teste | Banco sobe e os relatos são legíveis com a `APP_KEY` guardada |

Depois do teste: apague os casos fictícios (ou use uma instalação de homologação separada) e **só então** abra o canal público e divulgue o endereço `https://seu-endereco/denuncia/<slug-do-município>`.

---

## 6. Treinamento da equipe

Sugestão de uma sessão de **2 horas** por perfil, com o ambiente de teste.

**Para todos (30 min): sigilo**

- Os dados são de crianças em situação de violência. **Nunca** compartilhe senha, tire foto/print de tela com dados, nem envie relato por aplicativo de mensagem ou e-mail pessoal.
- Bloqueie a tela ao sair. Não deixe o sistema aberto em computador compartilhado.
- Tudo é auditado: quem abriu qual caso, quando, e de onde. O acesso negado também fica registrado.
- **Não** acesse caso por curiosidade. Acessar sem necessidade de serviço é falta funcional.
- A escuta especializada evita a revitimização: o relato da criança é registrado uma vez, só por quem a escuta, e o que vai a terceiros é o **mínimo necessário**.
- 2FA: guarde os códigos de recuperação separados do celular.

**Cola de uma página por perfil**

- **Recepção:** cadastrar caso (busque antes em *Atendidos* para não duplicar); marcar *urgente* só em risco imediato; agendar escuta; não tentar abrir relatos (o sistema não mostra).
- **Profissional:** começar o dia pela *Lista de espera* (assumir casos), depois escutas do dia; registrar o relato logo após a escuta; atualizar encaminhamentos (retorno recebido, sem retorno); gerar ficha SINAN para casos de vítima dentro do prazo; registrar o nº devolvido.
- **Coordenação:** *Lista de espera* (distribuir, olhar carga da equipe); *Integrações* (SINAN pendente/atrasado); *Estatísticas*; *Auditoria* (acessos negados e falhas de login); *Retenção* (vencimentos e guarda legal); *Fluxo* e *Prazos* quando a rede mudar.
- **Administrador:** usuários (ativar/inativar quando alguém sai do serviço **no mesmo dia**), redefinir 2FA, municípios.

**Quem sai do serviço:** a coordenação pede ao administrador para **inativar** o usuário **no mesmo dia**. Os casos dele **não mudam de responsável sozinhos**: os que ainda aguardam a 1ª escuta aparecem na *Lista de espera* (filtro "Com responsável, sem escuta marcada") e podem ser reatribuídos em lote; os demais precisam ser reatribuídos um a um, em *Gestão do caso* (campo *Responsável*). Faça isso antes da saída, quando possível.

---

## 7. Rotina de operação

| Quando | O quê | Quem |
|---|---|---|
| **Todo dia** | Lista de espera: casos urgentes e meta vencida; e-mail de alertas das 7h30 | Coordenação |
| **Todo dia** | Encaminhamentos "sem retorno" após o prazo: cobrar | Profissional / coordenação |
| **Toda semana** | Integrações → SINAN: pendentes e atrasados; Auditoria: filtre "Acesso negado" e "Falha de login" | Coordenação |
| **Todo mês** | `php artisan sigiep:retencao --dry-run` e *Retenção*: conferir o que vence nos próximos 90 dias e aplicar **guarda legal** onde houver processo em curso | Coordenação + TI |
| **Todo mês** | Revisar usuários ativos × quadro de pessoal | Coordenação + administrador |
| **Trimestre** | Testar a **restauração** de backup (seção 8) | TI |
| **Trimestre** | Revisar o fluxo e os prazos de cada município com a rede | Coordenação |

**Anonimização (irreversível):** por padrão ela **não roda sozinha**. Antes de ligar `SIGIEP_RETENTION_AUTO=true`, valide o prazo de guarda com o jurídico/DPO ⚖️, rode várias vezes o `--dry-run` e confirme que os casos sob processo estão com **guarda legal**. Anonimizar remove nomes, documentos, endereços, relatos e responsáveis, e mantém só dados estatísticos. Backups anteriores à anonimização **ainda contêm os dados**: defina com o jurídico por quanto tempo os backups são mantidos ⚖️.

**SINAN:** o sistema não envia nada ao SINAN. Gere o arquivo/ficha, entregue à vigilância epidemiológica e registre aqui o número de notificação que ela devolver. Os prazos são cobrados no painel e nos alertas.

---

## 8. Backup e restauração

**O que guardar (e onde):**

| Item | Frequência | Onde |
|---|---|---|
| **Banco de dados** | Diário (e antes de cada atualização) | Armazenamento externo, **criptografado**, com retenção definida ⚖️ |
| **`APP_KEY`** | Uma vez (e a cada troca) | **Cofre de senhas, separado do banco.** Sem ela o banco restaurado não abre relatos nem CPFs |
| **`.env`** | A cada mudança | Cofre de senhas |

Exemplo de backup criptografado diário do MySQL (adapte à sua infraestrutura):

```bash
#!/bin/bash
set -euo pipefail
DEST=/backup/sigiep
STAMP=$(date +%F)
# credenciais do banco em ~/.my.cnf (permissão 600) do usuário que roda o script, nunca na linha de comando
mysqldump --single-transaction --quick sigiep \
  | gzip \
  | gpg --encrypt --recipient backup@seuorgao.gov.br \
  > "$DEST/sigiep-$STAMP.sql.gz.gpg"
find "$DEST" -name 'sigiep-*.gpg' -mtime +30 -delete   # ajuste o prazo conforme o jurídico
```

**Restauração (teste trimestral):**

1. Em um servidor de teste, instale o sistema (seção 3) com o **mesmo `.env` e a mesma `APP_KEY`**.
2. Restaure o dump: `gpg --decrypt sigiep-AAAA-MM-DD.sql.gz.gpg | gunzip | mysql sigiep`.
3. Entre no sistema, abra um caso e confirme que **o relato aparece legível**. Se aparecer erro, a `APP_KEY` está errada.
4. Apague o ambiente de teste e o dump restaurado quando terminar.

---

## 9. Atualizações

```bash
cd /var/www/sigiep
php artisan down --retry=60          # página de manutenção
# backup do banco aqui
git fetch --tags && git checkout <nova-tag>
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan sigiep:verificar --strict
php artisan up
```

- Atualize **primeiro em homologação** e rode o roteiro da seção 5.
- **Reversão:** `php artisan down`, volte o código (`git checkout <tag-anterior>`), restaure o backup feito antes se a migração alterou dados, `composer install`, caches, `php artisan up`.
- Dependências: o repositório abre PRs semanais de atualização para **revisão humana**. Atualizações de segurança do PHP, do sistema operacional e do banco são responsabilidade da TI.

---

## 10. Incidente de segurança

Suspeita de acesso indevido, perda de celular com 2FA, senha vazada ou vazamento de dados:

1. **Contenha:** o administrador **inativa** o usuário envolvido e redefine o 2FA e a senha. Se for algo maior, ponha o sistema em manutenção (`php artisan down`).
2. **Preserve evidências:** não apague logs. Exporte a *Auditoria* filtrada pelo usuário e pelo período (a trilha registra quem leu qual caso e relato, exportações e acessos negados).
3. **Avalie o alcance:** na *Auditoria*, veja quais casos foram abertos pelo usuário ou na janela de tempo. Os relatos são criptografados no banco, mas qualquer pessoa autenticada com acesso ao caso os lê pela tela.
4. **Acione o encarregado (DPO)** imediatamente. Cabe a ele e ao jurídico avaliar a comunicação à **ANPD** e aos titulares. A ANPD estabelece prazo curto para comunicar incidentes com risco relevante (a regulamentação vigente fala em 3 dias úteis; **confirme o prazo atual com o DPO**) ⚖️.
5. **Corrija a causa** (senha fraca, dispositivo perdido, acesso indevido) e registre a lição aprendida.

---

## 11. Checklist de entrada em produção

**Servidor**
- [ ] HTTPS válido; HTTP redireciona para HTTPS
- [ ] `php artisan sigiep:verificar --strict` sem nenhum item crítico
- [ ] `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`
- [ ] E-mail real testado (redefinição de senha chega)
- [ ] `TRUSTED_PROXIES` definido se houver balanceador (conferir o IP real na Auditoria)
- [ ] Cron do agendador ativo
- [ ] Banco só aceita conexão do servidor da aplicação
- [ ] `storage/logs` com acesso restrito e retenção curta

**Dados e continuidade**
- [ ] `APP_KEY` e `.env` guardados em cofre, **fora** do servidor e do backup
- [ ] Backup diário criptografado funcionando
- [ ] **Restauração testada** com leitura de um relato
- [ ] Prazo de backup alinhado com a política de retenção ⚖️

**Configuração do município**
- [ ] IBGE válido, unidade do SINAN, telefone de contato
- [ ] Fluxo municipal cadastrado e revisado pela coordenação
- [ ] Prazos (SINAN, 1ª escuta, fila) confirmados
- [ ] Todos os usuários criados, com 2FA ativo; nenhum usuário de demonstração
- [ ] Prazo de guarda validado pelo jurídico/DPO ⚖️

**Pessoas e governança**
- [ ] Equipe treinada (sigilo + perfil)
- [ ] Termo de responsabilidade assinado pelos usuários ⚖️
- [ ] Encarregado (DPO) nomeado e relatório de impacto (RIPD) elaborado ⚖️
- [ ] Responsável por incidentes definido; contato do DPO à mão
- [ ] Roteiro da seção 5 executado com sucesso
- [ ] Canal público de denúncia só aberto **depois** de tudo acima

---

## 12. Problemas comuns

| Sintoma | Causa provável | O que fazer |
|---|---|---|
| Página "Page Expired" (419) | Sessão expirou (30 min sem uso) ou cookie bloqueado | Entrar de novo. Em HTTPS, confirme `SESSION_SECURE_COOKIE=true` e `APP_URL` com `https://` |
| E-mail não chega (redefinição, alertas) | `MAIL_MAILER=log` ou SMTP errado | Ajuste `MAIL_*`, `php artisan config:cache`; com `log`, o conteúdo vai para `storage/logs` |
| Mudei o `.env` e nada mudou | Configuração em cache | `php artisan config:cache` |
| "Código inválido" no 2FA | Hora do celular desajustada ou código já usado | Ativar hora automática no celular; o mesmo código não vale duas vezes (espere o próximo) |
| Perdi o celular e os códigos | — | Administrador: *Usuários → Editar → Redefinir 2FA* |
| Muitas tentativas (429) no login | 6 erros em 1 minuto no mesmo e-mail e IP | Aguardar 1 minuto |
| Todos aparecem com o mesmo IP na auditoria | Atrás de proxy sem `TRUSTED_PROXIES` | Definir `TRUSTED_PROXIES` e `php artisan config:cache` |
| Erro 500 ao abrir um relato | `APP_KEY` diferente da usada para gravar | Restaurar a `APP_KEY` correta; não gere uma nova chave num sistema com dados |
| Horário das escutas diferente do local | Fuso do município | Definir `APP_TIMEZONE` e `config:cache` |
| PDF sai com caracteres estranhos | Falta de fontes no servidor | Instalar o pacote de fontes DejaVu do sistema operacional |
| "Município sem código IBGE" ao gerar ficha SINAN | Cadastro incompleto | *Municípios → Editar*: IBGE e unidade notificadora |
| Usuário não vê um caso | Caso já está com outro profissional | Coordenação reatribui pela *Lista de espera* |
