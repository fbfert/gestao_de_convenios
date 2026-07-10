# Bloco 7 - Domínio 2 - Resumo

Este fechamento substituiu o atalho local por dados reais de referência na tela
de Solicitações e entregou o domínio de Guias com o destaque visual do prazo de
senha, mantendo o padrão de "teste antes do próximo domínio".

## Peças entregues

### API de referência

- [`api/app/Http/Controllers/PacienteController.php`](../api/app/Http/Controllers/PacienteController.php)
- [`api/app/Http/Controllers/ProfissionalController.php`](../api/app/Http/Controllers/ProfissionalController.php)
- [`api/app/Http/Controllers/EspecialidadeController.php`](../api/app/Http/Controllers/EspecialidadeController.php)
- [`api/app/Http/Controllers/ConvenioController.php`](../api/app/Http/Controllers/ConvenioController.php)
- [`api/app/Http/Resources/PacienteResource.php`](../api/app/Http/Resources/PacienteResource.php)
- [`api/app/Http/Resources/ProfissionalResource.php`](../api/app/Http/Resources/ProfissionalResource.php)
- [`api/app/Http/Resources/EspecialidadeResource.php`](../api/app/Http/Resources/EspecialidadeResource.php)
- [`api/app/Http/Resources/ConvenioResource.php`](../api/app/Http/Resources/ConvenioResource.php)
- [`api/tests/Feature/ReferenciasApiTest.php`](../api/tests/Feature/ReferenciasApiTest.php)

### Frontend

- [`web/src/lib/queries/useReferenceData.ts`](../web/src/lib/queries/useReferenceData.ts)
- [`web/src/features/solicitacoes/SolicitacoesPage.tsx`](../web/src/features/solicitacoes/SolicitacoesPage.tsx)
- [`web/src/features/guias/GuiasPage.tsx`](../web/src/features/guias/GuiasPage.tsx)
- [`web/src/features/antecipacoes/AntecipacoesPage.tsx`](../web/src/features/antecipacoes/AntecipacoesPage.tsx)
- [`web/src/features/guias/useGuias.ts`](../web/src/features/guias/useGuias.ts)
- [`web/src/features/antecipacoes/useAntecipacoes.ts`](../web/src/features/antecipacoes/useAntecipacoes.ts)

## Decisões registradas

- `referenceData.ts` foi removido.
- Os selects de Solicitações agora usam endpoints reais de referência.
- O filtro `validade_senha_vencendo_em_dias` virou destaque visual na UI de Guias.
- A finalização de guia continua disparando a antecipação automaticamente.

## Validação

- `php artisan test`
- `npm run build`
- `npm run lint`
- browser real:
  - criar solicitação com dados reais de referência
  - aprovar e ver status mudar sem reload
  - criar guia
  - finalizar guia
  - ver a antecipação aparecer automaticamente no domínio 3

## Resultado

- backend: `46` testes, `218` assertions
- frontend: build e lint passando

## Marco atingido

O Bloco 7 agora usa referência real para os formulários e o domínio de Guias já
prova visualmente a automação do ciclo de antecipação.
