## ADDED Requirements

### Requirement: Envio manual de item para Unimed
O sistema SHALL disponibilizar ação manual "Enviar para Unimed" para item elegível de Solicitação Unimed, sem disparar envio automático apenas pela aprovação da Solicitação.

#### Scenario: Item elegível
- **WHEN** o item possuir Solicitação aprovada, Pedido Médico, credencial Unimed ativa e mapeamentos obrigatórios
- **THEN** o sistema SHALL permitir ao operador enfileirar a operação de gerar Guia

#### Scenario: Item inelegível
- **WHEN** faltar documento, credencial, mapeamento ou houver execução em andamento
- **THEN** o sistema SHALL bloquear o envio e exibir motivo operacional claro

### Requirement: Criar Guia local somente a partir do resultado da automação
O sistema SHALL criar ou atualizar a Guia local de um item Unimed somente após resultado estruturado da automação ou confirmação idempotente.

#### Scenario: Guia gerada com sucesso
- **WHEN** o worker retornar número de Guia gerado para o item
- **THEN** o sistema SHALL criar a Guia local vinculada ao item e à execução

#### Scenario: Resultado incerto após submit
- **WHEN** ocorrer timeout ou resposta ambígua após ação que possa ter gerado Guia
- **THEN** o sistema SHALL marcar a execução como `uncertain` e SHALL NOT reenviar automaticamente

#### Scenario: Confirmação idempotente encontra Guia
- **WHEN** a rotina de confirmação encontrar a Guia já criada na Unimed
- **THEN** o sistema SHALL vincular ou atualizar a Guia local e bloquear novo envio duplicado

### Requirement: Sem dynaHash hardcoded
O worker SHALL navegar pelo portal Unimed usando elementos e links da sessão corrente, sem hardcodear URLs temporárias com dynaHash.

#### Scenario: Navegação no portal
- **WHEN** o worker precisar acessar uma página interna do portal
- **THEN** o worker SHALL seguir elementos, hrefs ou navegações geradas pela sessão corrente
