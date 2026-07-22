## ADDED Requirements

### Requirement: Dashboard como página inicial
O sistema SHALL abrir o Dashboard como rota inicial após o login e SHALL expor o Dashboard como primeiro item do menu para usuários autenticados.

#### Scenario: Acesso pós-login
- **WHEN** um usuário autenticado entra no sistema
- **THEN** o sistema SHALL redirecionar para o Dashboard

#### Scenario: Menu principal
- **WHEN** o menu lateral for exibido
- **THEN** o primeiro item SHALL ser o Dashboard

### Requirement: Dashboard consolidado por blocos
O sistema SHALL exibir no Dashboard blocos de visão geral para convênios, guias, solicitações, antecipações, lançamentos, conciliações, pacientes, profissionais, médicos, usuários e auditoria.

#### Scenario: Blocos visíveis
- **WHEN** o Dashboard for carregado
- **THEN** o sistema SHALL mostrar os blocos configurados para o perfil do usuário

### Requirement: Dashboard com relatórios e atalhos
O sistema SHALL apresentar no Dashboard contagens, indicadores ou resumos úteis para operação diária e SHALL oferecer atalhos para as telas correspondentes e para a auditoria.

#### Scenario: Atalho para auditoria
- **WHEN** o usuário acessar o Dashboard
- **THEN** o sistema SHALL permitir abrir a auditoria a partir de um atalho visível

#### Scenario: Atalhos operacionais
- **WHEN** um bloco resumido indicar um domínio operacional
- **THEN** o Dashboard SHALL oferecer um link para a tela correspondente
