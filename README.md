# Gestão de Convênios

Sistema de gestão da esteira de convênios para clínicas de terapia/reabilitação
(fisioterapia, ABA, etc.) — solicitação de guia, autorização, antecipação,
lançamento de sessões, finalização e conciliação financeira. Multi-tenant desde
o início; hoje convive com o Clínica Ágil (agenda/cadastro), na Etapa 2 o
substitui.

## Stack

- **API**: Laravel 11, MySQL/MariaDB, Sanctum (auth), Spatie Permission
- **Web**: React + TypeScript + Vite, TanStack Query, Zustand, Tailwind
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
| [`PROMPT-CLAUDE-CODE.md`](PROMPT-CLAUDE-CODE.md) | Prompt pronto pra rodar no Claude Code e gerar o esqueleto inicial |

## Como começar

1. Ler `docs/decisoes-arquitetura.md` primeiro — evita retrabalho.
2. Ajustar versões de PHP/Node no topo de `PROMPT-CLAUDE-CODE.md` pra bater com o
   que você já usa no FERTWAYS.
3. Rodar o prompt no Claude Code dentro de uma pasta vazia `gestao-convenios/`.
4. Validar o resultado contra `docs/roadmap-mvp.md` — cada item vira um commit.

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
