# Gestão de Convênios

Sistema de gestão da esteira de convênios para clínicas de terapia/reabilitação
(fisioterapia, ABA, etc.) — solicitação de guia, autorização, antecipação,
lançamento de sessões, finalização e conciliação financeira. Multi-tenant desde
o início; hoje convive com o Clínica Ágil (agenda/cadastro), na Etapa 2 o
substitui.

## Stack

- **API**: Laravel 11, MySQL/MariaDB, Sanctum (auth), Spatie Permission
- **Web**: React + TypeScript + Vite, TanStack Query, Zustand, Tailwind v4
- **Design system**: tokens do xiax-agenda 1.0.0 — ver `design-system-xiax-agenda.md`
- **Infra dev**: Docker Compose (banco), Laravel/Vite nativos na máquina

## Estrutura

```
gestao-convenios/
├── api/                  # Laravel 11 — API
├── web/                  # React + Vite + TS — frontend
├── docs/                 # documentação de arquitetura e roadmap (este diretório)
├── docker-compose.yml
└── gestao-convenios.code-workspace
```

## Documentação

| Arquivo | Conteúdo |
|---|---|
| [`docs/schema.md`](docs/schema.md) | Modelo de dados completo — Etapa 1 (MVP) e Etapa 2 |
| [`docs/decisoes-arquitetura.md`](docs/decisoes-arquitetura.md) | Decisões de arquitetura já validadas (ADRs) — não reabrir sem motivo forte |
| [`docs/roadmap-mvp.md`](docs/roadmap-mvp.md) | Checklist ordenado de execução da Etapa 1 |
| [`docs/resumo-entregas-2026-08-14.md`](docs/resumo-entregas-2026-08-14.md) | O que entrou em produção em 13 e 14/08/2026, com as pendências registradas |
| [`docs/resumo-entregas-2026-08-26.md`](docs/resumo-entregas-2026-08-26.md) | Design system, responsividade e contrato de design — 26/08/2026 |
| [`design-system-xiax-agenda.md`](design-system-xiax-agenda.md) | A receita da pele: tokens, tipografia, e as regras fiscalizadas por teste |
| [`PROMPT-CLAUDE-CODE.md`](PROMPT-CLAUDE-CODE.md) | Prompt pronto pra rodar no Claude Code e gerar o esqueleto inicial |

## Como começar

1. Ler `docs/decisoes-arquitetura.md` primeiro — evita retrabalho.
2. Ajustar versões de PHP/Node no topo de `PROMPT-CLAUDE-CODE.md` pra bater com o
   que você já usa no FERTWAYS.
3. Rodar o prompt no Claude Code dentro de uma pasta vazia `gestao-convenios/`.
4. Validar o resultado contra `docs/roadmap-mvp.md` — cada item vira um commit.

## Ambiente local

O banco de desenvolvimento roda em SQLite (`api/database/database.sqlite`); o `docker-compose.yml`
com MariaDB continua disponível para quem quiser o alvo de produção.

```bash
cd api  && composer install && php artisan key:generate
cd ../web && npm install
```

Carregar dados de demonstração — determinístico, com todos os status representados:

```bash
cd api
php artisan migrate:fresh --seed --force
php artisan db:seed --class=DemoDataSeeder --force
```

Contas: `admin@`, `funcionario@`, `profissional@` e `superadmin@clinica-exemplo.test`, senha
`password`. A última tem `users.super_admin`, exigida por `/clinicas`.

> **PHP 8.5.** O Laravel 11 ainda usa `PDO::MYSQL_ATTR_SSL_CA`, e o aviso de *deprecated* entra no
> corpo das respostas do servidor embutido, corrompendo o JSON. Suba a API com
> `php -d display_errors=0 -d error_reporting="E_ALL & ~E_DEPRECATED" -S 127.0.0.1:8000 -t . ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`
> a partir de `api/public`, ou use `php@8.4`.

## Contrato de design

```bash
cd web && npm run ds:check   # também roda dentro de `npm run lint`
```

Quatro guardas reprovam o build: contraste calculado a partir do CSS, valor mágico (hex, arbitrário,
escala crua de texto, sombra e camada de outro projeto), classe com cara de token que não existe, e
configuração do compositor de classes. Ver §11 do documento do design system e ADR-16 a ADR-22.

## Regra de ouro do projeto

Regras de convênio (frequência de lançamento, quantidade autorizada, validade
de senha, valor por profissional) são **dado configurável, nunca código**.
Nenhuma regra de negócio de convênio deve ser hardcoded em Service ou
Controller — sempre lida de `convenio_regras` / `tabela_valores`.

## Operação em produção

- O scheduler do Laravel já está registrado em `api/routes/console.php`.
- Em produção, o cron do sistema precisa executar:

```bash
* * * * * cd /caminho/do/projeto/api && php artisan schedule:run
```

- Isso é o que dispara o `VerificarGuiasDiarioJob` diariamente no horário
  configurado no scheduler.
