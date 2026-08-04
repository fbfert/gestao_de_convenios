## ADDED Requirements

### Requirement: Ações Unimed separadas na guia
O sistema SHALL disponibilizar ações distintas para consultar status Unimed e buscar senha/validade Unimed, respeitando a elegibilidade e permissões do usuário.

#### Scenario: Consultar status manualmente
- **WHEN** uma guia Unimed tiver número e status elegível para consulta
- **THEN** a UI SHALL oferecer ação para enfileirar consulta de status e exibir retorno operacional da solicitação

#### Scenario: Buscar senha e validade manualmente
- **WHEN** uma guia Unimed estiver aprovada, tiver número e senha ou validade ausente
- **THEN** a UI SHALL oferecer ação separada para enfileirar captura de senha/validade

#### Scenario: Ação inelegível oculta ou desabilitada
- **WHEN** a guia não atender à elegibilidade da operação Unimed
- **THEN** a UI SHALL ocultar ou desabilitar a ação correspondente sem acionar operação errada

### Requirement: Estado operacional recente da consulta Unimed
O sistema SHALL exibir na lista e/ou detalhe de guia o horário da última consulta conclusiva e erro recente relacionado às operações Unimed quando disponíveis.

#### Scenario: Última consulta exibida
- **WHEN** uma guia possuir `unimed_last_checked_at`
- **THEN** a UI SHALL exibir o horário da última consulta de status de forma clara

#### Scenario: Erro recente exibido
- **WHEN** a operação Unimed recente retornar erro individual
- **THEN** a UI SHALL exibir um estado operacional compatível sem esconder os dados atuais da guia
