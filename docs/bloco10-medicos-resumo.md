# Bloco 10 - Medicos e solicitacoes

Resumo do ajuste de contrato que trocou `solicitacoes.medico_solicitante` por `solicitacoes.medico_id` com a nova tabela `medicos`.

## O que entrou no backend

- Nova tabela e model de medicos:
  - [`api/database/migrations/2026_07_11_200000_create_medicos_table.php`](../api/database/migrations/2026_07_11_200000_create_medicos_table.php)
  - [`api/app/Models/Medico.php`](../api/app/Models/Medico.php)
- Alteracao em solicitacoes:
  - [`api/database/migrations/2026_07_11_200001_add_medico_id_to_solicitacoes_table.php`](../api/database/migrations/2026_07_11_200001_add_medico_id_to_solicitacoes_table.php)
  - [`api/database/migrations/2025_01_01_000008_create_solicitacoes_table.php`](../api/database/migrations/2025_01_01_000008_create_solicitacoes_table.php)
- Model, service, request e resource atualizados:
  - [`api/app/Models/Solicitacao.php`](../api/app/Models/Solicitacao.php)
  - [`api/app/Services/SolicitacaoService.php`](../api/app/Services/SolicitacaoService.php)
  - [`api/app/Http/Requests/StoreSolicitacaoRequest.php`](../api/app/Http/Requests/StoreSolicitacaoRequest.php)
  - [`api/app/Http/Resources/SolicitacaoResource.php`](../api/app/Http/Resources/SolicitacaoResource.php)
  - [`api/app/Http/Controllers/SolicitacaoController.php`](../api/app/Http/Controllers/SolicitacaoController.php)
- Endpoint de referencia para selects:
  - [`api/app/Http/Controllers/MedicoController.php`](../api/app/Http/Controllers/MedicoController.php)
  - [`api/app/Http/Resources/MedicoResource.php`](../api/app/Http/Resources/MedicoResource.php)
  - [`api/routes/api.php`](../api/routes/api.php)
- Seeders e testes:
  - [`api/database/seeders/MedicoSeeder.php`](../api/database/seeders/MedicoSeeder.php)
  - [`api/database/seeders/DatabaseSeeder.php`](../api/database/seeders/DatabaseSeeder.php)
  - [`api/tests/Feature/MedicosApiTest.php`](../api/tests/Feature/MedicosApiTest.php)
  - [`api/tests/Feature/SolicitacoesApiTest.php`](../api/tests/Feature/SolicitacoesApiTest.php)
  - [`api/tests/Feature/FluxoConvenioCompletoTest.php`](../api/tests/Feature/FluxoConvenioCompletoTest.php)
- Ajuste de isolamento do tenant:
  - [`api/app/Http/Middleware/ResolveTenant.php`](../api/app/Http/Middleware/ResolveTenant.php)

## O que entrou no frontend

- Select real de medicos no formulario de solicitacoes:
  - [`web/src/features/solicitacoes/SolicitacoesPage.tsx`](../web/src/features/solicitacoes/SolicitacoesPage.tsx)
  - [`web/src/features/solicitacoes/types.ts`](../web/src/features/solicitacoes/types.ts)
  - [`web/src/features/solicitacoes/useSolicitacoes.ts`](../web/src/features/solicitacoes/useSolicitacoes.ts)
- Hook de referencia real:
  - [`web/src/lib/queries/useReferenceData.ts`](../web/src/lib/queries/useReferenceData.ts)
- E2E ajustado para o novo contrato:
  - [`web/tests/e2e/mvp-flow.spec.ts`](../web/tests/e2e/mvp-flow.spec.ts)

## Decisoes registradas

- `medico_id` virou a fonte de verdade em solicitacoes.
- `medico_solicitante` ficou apenas como coluna legada de transicao.
- O campo de texto livre da tela foi substituido por select com dados reais da API.
- O endpoint `GET /api/medicos` passou a servir como referencia para o formulario.
- O tenant context foi limpo no middleware para evitar vazamento entre requests e testes.

## Validacoes feitas

- Backend: `php artisan test` passou com `54 passed / 248 assertions`.
- Frontend: `npm run build` passou.
- Frontend: `npm run lint` passou.
- E2E: testes individuais passaram:
  - fluxo completo de negocio
  - isolamento cross-tenant via browser
  - guard de rota sem autenticacao
- A suite E2E completa ficou em estabilizacao antes do pedido de encerramento e esta pronta para retomar do ponto atual.

## Estado atual para teste manual

- API local em `http://127.0.0.1:8000`
- Frontend local em `http://127.0.0.1:4174`
- Login seedado continua disponivel para teste manual
