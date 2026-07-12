## Why

Hoje `pacientes` só existe como endpoint de referência para selects e filtros. Falta uma tela administrativa para cadastrar e manter pacientes do tenant, o que impede operar o fluxo com dados reais sem mexer direto no banco.

## What Changes

- Adiciona CRUD de pacientes para o tenant autenticado.
- Expõe API para listar, criar, atualizar e desativar pacientes.
- Mantém isolamento por tenant e binding cross-tenant em `GET /api/pacientes/{id}`.
- Adiciona tela de pacientes no frontend com lista, formulário e ações básicas.
- Reusa o contrato atual de referência de pacientes sem alterar o restante do fluxo de convênios.

## Capabilities

### New Capabilities
- `patients-crud`: gerenciamento de pacientes na Etapa 1, incluindo listagem, criação, edição, desativação e busca por nome/carteirinha.

### Modified Capabilities
- `reference-data`: o endpoint de pacientes continua servindo como referência, mas passa a ser complementado por um CRUD explícito.

## Impact

- Backend Laravel: `PacienteController`, `PacienteResource`, requests de create/update, rotas e bindings tenant-scoped.
- Frontend React: nova tela `Pacientes`, menu no shell e hooks React Query.
- Testes: feature tests HTTP para listagem, validação, isolamento cross-tenant e operações de criação/edição/desativação.
