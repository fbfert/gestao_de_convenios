# Bloco 8 - E2E

Resumo final da camada Playwright para o MVP.

## O que ficou pronto

- `@playwright/test` em [`web/package.json`](../web/package.json)
- Script `test:e2e` em [`web/package.json`](../web/package.json) que recria o banco de teste antes da suíte
- Configuração em [`web/playwright.config.ts`](../web/playwright.config.ts) com dois `webServer`:
  - Vite em `http://127.0.0.1:4174`
  - API em `http://127.0.0.1:8001`
- Remoção do bootstrap manual anterior:
  - sem `global-setup.ts`
  - sem `global-teardown.ts`
- Suite E2E em [`web/tests/e2e/mvp-flow.spec.ts`](../web/tests/e2e/mvp-flow.spec.ts)

## Banco de teste

- O ambiente E2E usa [`api/.env.testing`](../api/.env.testing)
- Banco separado:
  - `DB_CONNECTION=sqlite`
  - `DB_DATABASE=database/testing.sqlite`
- O banco é recriado e semeado a cada execução do `npm run test:e2e`

## Testes cobertos

- Fluxo completo de negócio
- Isolamento cross-tenant via browser
- Guard de rota sem autenticação

## Validação final

- Execução 1:
  - `3 passed`
  - `31.1s`
- Execução 2:
  - `3 passed`
  - `31.4s`
- As duas execuções produziram o mesmo caminho feliz:
  - login
  - finalização de guia
  - abertura automática de antecipação
  - lançamento
  - conciliação
  - conferência
  - pagamento
  - logout
- O runner encerrou de forma limpa na segunda execução monitorada, sem teardown manual.

## Comando local

```bash
cd web
npm run test:e2e
```

## Observação

- A suíte ficou determinística porque o banco de teste é recriado antes de cada execução e a API/Vite sobem como servidores nativos do Playwright.
