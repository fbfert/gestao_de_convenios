## MODIFIED Requirements

### Requirement: Credenciais seguras por convênio
O sistema SHALL permitir manter uma credencial de automação por convênio dentro de cada tenant,
com os valores criptografados em repouso e sem retorno de segredo em responses.

> Substitui "Credenciais Unimed seguras por tenant". A credencial deixa de ser única por tenant
> e passa a ser identificada pelo par `tenant_id + convenio_id`; a credencial Unimed existente é
> migrada para o convênio de driver `unimed_rda` sem novo cadastro.

#### Scenario: Salvar credencial de um convênio
- **WHEN** um usuário autorizado salvar a credencial de um convênio do tenant
- **THEN** o sistema SHALL criptografar os valores e persistir a configuração vinculada àquele convênio
- **AND** SHALL preservar, sem alteração, as credenciais dos demais convênios do tenant

#### Scenario: Preservar segredo existente
- **WHEN** um usuário autorizado atualizar a credencial sem informar novo valor para um campo secreto
- **THEN** o sistema SHALL preservar o valor criptografado existente

#### Scenario: Não expor segredo
- **WHEN** a API retornar a configuração de credenciais ao frontend
- **THEN** o sistema SHALL omitir o valor de todo campo secreto, indicando apenas que está preenchido

### Requirement: Pausa de automação restrita ao convênio
O sistema SHALL restringir a pausa automática por falha estrutural ao convênio em que a falha
ocorreu, e SHALL manter ativas as credenciais dos demais convênios do tenant.

> Requisito novo nesta capability. Até aqui o disjuntor recebia apenas o tenant e pausava a
> única credencial existente, o que em 14/09/2026 travou a automação de todos os itens do
> tenant a partir da falha de um item só.

#### Scenario: Falha estrutural em um convênio
- **WHEN** uma execução de automação falhar com erro classificado como estrutural
- **THEN** o sistema SHALL pausar a credencial do convênio daquela execução
- **AND** SHALL NOT alterar o estado das credenciais dos demais convênios do tenant

#### Scenario: Reativação após pausa
- **WHEN** um usuário autorizado reativar a automação de um convênio pausado
- **THEN** o sistema SHALL reativar apenas a credencial daquele convênio
- **AND** SHALL registrar a reativação na auditoria
