# Roadmap de Execução — Etapa 1 (MVP)

Ordem sugerida. Cada bloco vira um ou mais commits; não pular pra UI antes do
backend estar testado.

## 0. Repositório e ambiente

- [ ] Estrutura de pastas `api/` + `web/` + `docs/` + `docker-compose.yml`
- [ ] `docker-compose.yml` com MariaDB (+ phpMyAdmin opcional)
- [ ] `.editorconfig`, `.gitignore` raiz
- [ ] `gestao-convenios.code-workspace` (api + web como pastas do workspace)
- [ ] `.vscode/extensions.json` recomendando Intelephense, Laravel Extra
      Intellisense, Prettier, ESLint, Tailwind CSS IntelliSense

## 1. Backend — fundação

- [ ] `composer create-project laravel/laravel api`
- [ ] Instalar `laravel/sanctum`, `spatie/laravel-permission`, `laravel/pint`
- [ ] Criar `TenantScope` (Global Scope) + trait `BelongsToTenant`
- [ ] Middleware de resolução de tenant (a partir do usuário autenticado)

## 2. Backend — migrations (nessa ordem de dependência)

- [ ] `tenants`
- [ ] `users`
- [ ] `especialidades`, `profissionais`
- [ ] `convenios`
- [ ] `convenio_regras`
- [ ] `pacientes`
- [ ] `solicitacoes`
- [ ] `guias`
- [ ] `tabela_valores`
- [ ] `antecipacoes`
- [ ] `lancamentos`
- [ ] `conciliacoes_financeiras`
- [ ] `conector_execucoes`
- [ ] `audit_logs`

## 3. Backend — dados de teste

- [ ] Seeders: 1 tenant, 2-3 convênios com regras diferentes, pacientes e
      profissionais fictícios — o suficiente pra testar o fluxo ponta a ponta
      sem esperar dado real do cliente

## 4. Backend — regras de negócio isoladas (com teste antes da tela)

- [ ] `TabelaValoresService` (cascata de prioridade — ADR-07) + teste unitário
- [ ] `GuiaService` / `AntecipacaoService` (consumo de cota) + teste feature
- [ ] `ConciliacaoService` + teste feature
- [ ] `ManualConnector` (implementação inicial da interface de conector)
- [ ] `VerificarGuiasDiarioJob` agendado
  - em produção, o cron do sistema deve executar `php artisan schedule:run` a
    cada minuto

## 5. Backend — API

- [ ] Endpoints REST por domínio (Solicitação, Guia, Antecipação, Lançamento,
      Conciliação), com Form Requests + API Resources
- [ ] Collection Postman/Insomnia exportada em `docs/`

## 6. Frontend — fundação

- [ ] `npm create vite@latest web -- --template react-ts`
- [ ] Instalar `@tanstack/react-query`, `axios`, `zustand`,
      `react-router-dom`, `tailwindcss`
- [ ] `statusLabels.ts` central (tradução de status — ADR-06)
- [ ] Estrutura `src/features/<dominio>` espelhando os módulos do backend

## 7. Frontend — telas (só depois da API testada)

- [ ] Solicitações (lista + criação)
- [ ] Guias (lista + acompanhamento de status)
- [ ] Antecipação (painel de liberados pra agendar)
- [ ] Lançamentos (registro de sessão)
- [ ] Conciliação financeira (agrupável por convênio/especialidade/profissional)

## 8. Validação com o cliente

- [ ] Rodar o fluxo ponta a ponta com dado fictício de 1 convênio real
- [ ] Levantar as regras reais dos 7-8 convênios e preencher
      `convenio_regras` / `tabela_valores`
- [ ] Ajustar antes de migrar dado real de paciente/carteirinha
