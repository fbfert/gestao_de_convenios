## ADDED Requirements

### Requirement: Visibilidade de blocos do Dashboard por perfil
O sistema SHALL permitir que o administrador defina, por papel/perfil, quais blocos do Dashboard ficam visíveis para aquele perfil.

#### Scenario: Configurar visibilidade do perfil
- **WHEN** um administrador alterar as permissões de um papel
- **THEN** o sistema SHALL salvar quais blocos do Dashboard aquele papel pode ver

#### Scenario: Respeitar a configuração no Dashboard
- **WHEN** um usuário fizer login com um papel configurado com visibilidade parcial
- **THEN** o Dashboard SHALL exibir somente os blocos permitidos para aquele papel

### Requirement: Catálogo fixo de permissões do Dashboard
O sistema SHALL manter o conjunto de permissões do Dashboard em um catálogo fixo no código e SHALL apresentar essas permissões na tela de gestão de permissões sem permitir criação ad hoc de novos nomes.

#### Scenario: Novo bloco catalogado
- **WHEN** uma nova funcionalidade do Dashboard for criada
- **THEN** o sistema SHALL exigir uma permissão catalogada no código antes de expor o bloco na tela de permissões

#### Scenario: Sem criação livre
- **WHEN** o administrador abrir a tela de permissões
- **THEN** o sistema SHALL permitir apenas selecionar permissões já catalogadas
