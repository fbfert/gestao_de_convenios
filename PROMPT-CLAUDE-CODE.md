# Prompt de Execução — Claude Code

> Ajuste `[PHP_VERSION]` e `[NODE_VERSION]` antes de rodar, pra bater com o
> que você já usa no FERTWAYS. Cole o prompt abaixo (a partir de "Contexto")
> dentro de uma pasta vazia `gestao-convenios/`, com `docs/schema.md`,
> `docs/decisoes-arquitetura.md` e `docs/roadmap-mvp.md` já presentes.

---

## Contexto

Você vai montar o esqueleto inicial do projeto **Gestão de Convênios** — um
sistema multi-tenant para gestão da esteira de convênios de clínicas de
terapia/reabilitação (solicitação de guia → autorização → antecipação →
lançamento → finalização → conciliação financeira).

Antes de qualquer código, leia por completo:
- `docs/schema.md` — modelo de dados (Etapa 1 e Etapa 2)
- `docs/decisoes-arquitetura.md` — decisões já validadas (ADRs), não reabrir
- `docs/roadmap-mvp.md` — ordem de execução

Trate os ADRs como restrições rígidas, não sugestões. Em especial:
ADR-01 (tenant_id + Global Scope), ADR-02 (conectores via Strategy Pattern,
começando só com `manual`), ADR-03 (regras de convênio são dado, nunca
hardcode), ADR-06 (status em inglês no banco/API, tradução só na UI), ADR-07
(cascata de `tabela_valores` isolada em service próprio).

## Stack

- API: Laravel 11, PHP [PHP_VERSION], MySQL/MariaDB via Docker
- Web: React + TypeScript + Vite, Node [NODE_VERSION]
- Pacotes obrigatórios na API: `laravel/sanctum`, `spatie/laravel-permission`,
  `laravel/pint`
- Pacotes obrigatórios no Web: `@tanstack/react-query`, `axios`, `zustand`,
  `react-router-dom`, `tailwindcss`

## O que fazer, nesta ordem (siga `docs/roadmap-mvp.md` seção a seção)

1. Criar a estrutura de pastas do monorepo (`api/`, `web/`, `docs/` já existe,
   `docker-compose.yml` na raiz).
2. Criar `docker-compose.yml` com serviço MariaDB (porta configurável, volume
   persistente) — não subir Laravel/Vite em container, esses rodam nativos.
3. Rodar `composer create-project laravel/laravel api`, instalar os pacotes
   obrigatórios, configurar `.env` apontando pro MariaDB do compose.
4. Implementar `TenantScope` (Global Scope) + trait `BelongsToTenant` **antes**
   de qualquer migration de negócio — todo model criado depois já deve usar a
   trait.
5. Criar as migrations na ordem exata listada em `docs/schema.md` /
   `docs/roadmap-mvp.md` (respeitar dependência de FK).
6. Criar os Models correspondentes, cada um usando `BelongsToTenant` (exceto
   `Tenant` em si).
7. Criar seeders: 1 tenant, 2-3 convênios com `convenio_regras` diferentes
   entre si (pra provar que a regra é dado, não código), pacientes e
   profissionais fictícios.
8. Implementar `TabelaValoresService` com a cascata de prioridade do ADR-07,
   e escrever teste unitário cobrindo os 3 níveis de fallback antes de seguir.
9. Implementar `GuiaService`, `AntecipacaoService`, `ConciliacaoService` com
   teste de feature cobrindo o fluxo: solicitação aprovada → guia finalizada
   → antecipação aberta → lançamento consome cota → conciliação calcula valor
   via `TabelaValoresService`.
10. Implementar a interface `ConnectorInterface` + `ManualConnector` (única
    implementação real por enquanto) e o `VerificarGuiasDiarioJob` agendado
    de madrugada, rodando contra a interface.
11. Expor endpoints REST por domínio com Form Requests + API Resources.
    Gerar uma collection Postman/Insomnia em `docs/api-collection.json`.
12. Só depois de tudo isso testado: criar o projeto Vite (`web/`), configurar
    Tailwind, montar `src/features/<dominio>` espelhando os módulos da API,
    e criar `statusLabels.ts` central pro ADR-06.
13. Criar `gestao-convenios.code-workspace` e `.vscode/extensions.json`
    recomendando Intelephense, Laravel Extra Intellisense, Prettier, ESLint,
    Tailwind CSS IntelliSense.

## Regras de execução

- Não escreva nenhuma tela em React antes do backend correspondente ter teste
  automatizado passando.
- Não hardcode nenhuma regra de convênio (frequência, quantidade, validade,
  valor) em Controller/Service — sempre ler de `convenio_regras` /
  `tabela_valores`.
- Ao final de cada bloco numerado acima, rode os testes e pare pra eu revisar
  antes de seguir pro próximo — não execute os 13 passos de uma vez sem
  checkpoint.
- Se algo no `docs/schema.md` parecer incompleto ou ambíguo durante a
  implementação, pare e pergunte — não invente regra de negócio nova.
