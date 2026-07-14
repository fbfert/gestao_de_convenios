## Why

As opções abertas de controles `<select>` nativos são renderizadas pelo navegador/SO e podem ignorar o tema escuro. O sistema precisa de uma lista HTML estilizada e acessível, consistente em todas as telas.

## What Changes

- Criar um componente reutilizável de seleção com Headless UI e tema escuro.
- Substituir todos os selects nativos do frontend por esse componente.
- Preservar os valores, filtros e lógica dos formulários existentes.

## Capabilities

### New Capabilities

- `dark-select`: Seleção acessível e estilizada de maneira consistente no frontend.

### Modified Capabilities

- Nenhuma.

## Impact

- Frontend React/Tailwind, dependência `@headlessui/react` e todas as telas que hoje usam selects nativos.
