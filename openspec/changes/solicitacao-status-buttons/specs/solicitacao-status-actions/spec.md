## ADDED Requirements

### Requirement: Ações de status na lista de solicitações
O sistema SHALL disponibilizar, para cada solicitação listada, ações para definir o status como `under_review`, `approved` ou `denied`, exibindo os rótulos traduzidos em português.

#### Scenario: Exibir três ações de status
- **WHEN** uma solicitação for exibida na lista
- **THEN** o sistema SHALL exibir as ações Em análise, Aprovado e Negado

#### Scenario: Confirmar alteração de status
- **WHEN** o operador clicar em uma ação de status diferente do status atual
- **THEN** o sistema SHALL solicitar confirmação antes de enviar a alteração para a API

#### Scenario: Alterar solicitação negada para aprovada
- **WHEN** uma solicitação com status `denied` for alterada para `approved` e o operador confirmar a ação
- **THEN** o sistema SHALL persistir o novo status como `approved` e atualizar a lista sem recarregar manualmente o navegador

#### Scenario: Recolocar solicitação em análise
- **WHEN** uma solicitação com status `approved` ou `denied` for alterada para `under_review` e o operador confirmar a ação
- **THEN** o sistema SHALL persistir o novo status como `under_review` e atualizar a lista sem recarregar manualmente o navegador
