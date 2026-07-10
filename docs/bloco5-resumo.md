# Bloco 5 — Resumo

Este bloco expôs os Services do Bloco 4 via API REST, sem reimplementar regra
de negócio no Controller. O padrão mantido foi: `Form Request` para escrita,
`Resource` para saída, `Route::bind()` explícito para isolamento HTTP e teste
de feature antes de seguir para o domínio seguinte.

## Peças entregues

### Domínio 1 - Solicitações

- [`api/app/Http/Controllers/SolicitacaoController.php`](../api/app/Http/Controllers/SolicitacaoController.php)
- [`api/app/Http/Requests/StoreSolicitacaoRequest.php`](../api/app/Http/Requests/StoreSolicitacaoRequest.php)
- [`api/app/Http/Requests/MutateSolicitacaoStatusRequest.php`](../api/app/Http/Requests/MutateSolicitacaoStatusRequest.php)
- [`api/app/Http/Resources/SolicitacaoResource.php`](../api/app/Http/Resources/SolicitacaoResource.php)
- [`api/app/Services/SolicitacaoService.php`](../api/app/Services/SolicitacaoService.php)
- [`api/app/Exceptions/SolicitacaoStatusInvalidoException.php`](../api/app/Exceptions/SolicitacaoStatusInvalidoException.php)
- [`api/tests/Feature/SolicitacoesApiTest.php`](../api/tests/Feature/SolicitacoesApiTest.php)

### Domínio 2 - Guias

- [`api/app/Http/Controllers/GuiaController.php`](../api/app/Http/Controllers/GuiaController.php)
- [`api/app/Http/Requests/StoreGuiaRequest.php`](../api/app/Http/Requests/StoreGuiaRequest.php)
- [`api/app/Http/Requests/MutateGuiaStatusRequest.php`](../api/app/Http/Requests/MutateGuiaStatusRequest.php)
- [`api/app/Http/Resources/GuiaResource.php`](../api/app/Http/Resources/GuiaResource.php)
- [`api/app/Services/GuiaService.php`](../api/app/Services/GuiaService.php)
- [`api/tests/Feature/GuiasApiTest.php`](../api/tests/Feature/GuiasApiTest.php)

### Domínio 3 - Antecipações

- [`api/app/Http/Controllers/AntecipacaoController.php`](../api/app/Http/Controllers/AntecipacaoController.php)
- [`api/app/Http/Resources/AntecipacaoResource.php`](../api/app/Http/Resources/AntecipacaoResource.php)
- [`api/app/Services/AntecipacaoService.php`](../api/app/Services/AntecipacaoService.php)
- [`api/tests/Feature/AntecipacoesApiTest.php`](../api/tests/Feature/AntecipacoesApiTest.php)

### Domínio 4 - Lançamentos

- [`api/app/Http/Controllers/LancamentoController.php`](../api/app/Http/Controllers/LancamentoController.php)
- [`api/app/Http/Requests/StoreLancamentoRequest.php`](../api/app/Http/Requests/StoreLancamentoRequest.php)
- [`api/app/Http/Resources/LancamentoResource.php`](../api/app/Http/Resources/LancamentoResource.php)
- [`api/app/Services/LancamentoService.php`](../api/app/Services/LancamentoService.php)
- [`api/tests/Feature/LancamentosApiTest.php`](../api/tests/Feature/LancamentosApiTest.php)

### Domínio 5 - Conciliação Financeira

- [`api/app/Http/Controllers/ConciliacaoController.php`](../api/app/Http/Controllers/ConciliacaoController.php)
- [`api/app/Http/Requests/ListConciliacaoRequest.php`](../api/app/Http/Requests/ListConciliacaoRequest.php)
- [`api/app/Http/Resources/ConciliacaoFinanceiraResource.php`](../api/app/Http/Resources/ConciliacaoFinanceiraResource.php)
- [`api/app/Services/ConciliacaoService.php`](../api/app/Services/ConciliacaoService.php)
- [`api/app/Exceptions/ConciliacaoStatusInvalidoException.php`](../api/app/Exceptions/ConciliacaoStatusInvalidoException.php)
- [`api/tests/Feature/ConciliacoesApiTest.php`](../api/tests/Feature/ConciliacoesApiTest.php)

### Infra comum do bloco

- [`api/app/Providers/AppServiceProvider.php`](../api/app/Providers/AppServiceProvider.php)
  - `Route::bind()` explícito para `solicitacao`, `guia`, `antecipacao` e `conciliacao`
  - isolamento HTTP cross-tenant com `404`
- [`api/bootstrap/app.php`](../api/bootstrap/app.php)
  - handler central convertendo exceptions de negócio em JSON
- [`api/routes/api.php`](../api/routes/api.php)
  - rotas REST dos 5 domínios sob `auth:sanctum`

## Decisões registradas

- **ADR-13 - Binding explícito para lookup HTTP por tenant**
  - o route-model-binding do Laravel roda antes do `ResolveTenant`
  - `TenantScope` sozinho não basta para `GET /{id}` e `PATCH /{id}`
  - cada model exposto por rota precisa de `Route::bind()` explícito filtrando por `tenant_id`
- **Conciliação financeira com trava intencional de status**
  - `pending -> reviewed -> paid`
  - não é permitido marcar como `paid` algo que não foi conferido antes

## Resultado do bloco

- `4` testes no domínio 1
- `3` testes no domínio 2
- `2` testes no domínio 3
- `4` testes no domínio 4
- `4` testes no domínio 5
- suíte inteira: `37` testes, `162` assertions

## Artefato de API

- [`docs/api-collection.json`](./api-collection.json)

## Marco atingido

O Bloco 5 expôs o núcleo do sistema por HTTP com isolamento multi-tenant de
ponta a ponta, mantendo o contrato do domínio nas `Services` e usando os
Controllers só como camada de transporte.
