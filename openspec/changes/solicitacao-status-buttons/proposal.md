## Why

Operadores precisam corrigir o status de uma solicitação diretamente na lista, inclusive reverter uma solicitação negada para aprovada. Hoje o fluxo bloqueia mudanças fora de `under_review` e a interface não oferece os três estados como ações explícitas.

## What Changes

- Exibir três ações de status na lista de solicitações: Em análise, Aprovado e Negado.
- Exigir confirmação do operador antes de aplicar qualquer mudança de status.
- Permitir que a API altere uma solicitação existente entre `under_review`, `approved` e `denied`.
- Manter os valores técnicos em inglês no API/banco e traduzir apenas na interface.

## Capabilities

### New Capabilities
- `solicitacao-status-actions`: Ações operacionais de status na lista de solicitações.

### Modified Capabilities

## Impact

- API Laravel: serviço, controller/rotas de solicitação e testes de transição de status.
- Web React: tela de solicitações e mutations de status.
- Sem nova dependência externa e sem migração de banco.
