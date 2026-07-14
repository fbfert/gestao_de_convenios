## Why

Administradores precisam manter convênios, regras e valores dentro do sistema, preservando histórico e isolamento entre tenants.

## What Changes

- Adicionar gestão de convênios, regras por vigência e valores por vigência.
- Expor telas de lista e detalhe de convênio para administradores autorizados.
- Preservar o endpoint de referência já usado pelos formulários.

## Capabilities

### New Capabilities

- `convenio-management`: Administração isolada por tenant de convênios, regras e valores vigentes.

### Modified Capabilities

- Nenhuma.

## Impact

- API Laravel, permissões, React e dados financeiros configuráveis; sem exclusões físicas.
