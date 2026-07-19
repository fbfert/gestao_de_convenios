## ADDED Requirements

### Requirement: Gestão administrativa de especialidades
O sistema SHALL disponibilizar uma área autenticada para listar, criar, editar e inativar especialidades da clínica, com isolamento por tenant.

#### Scenario: Listar especialidades do tenant
- **WHEN** um usuário autorizado abrir a tela de especialidades
- **THEN** o sistema SHALL exibir apenas as especialidades do tenant logado

#### Scenario: Criar especialidade
- **WHEN** um usuário autorizado enviar um novo nome de especialidade
- **THEN** o sistema SHALL criar o registro associado ao tenant e SHALL retorná-lo na listagem

#### Scenario: Editar especialidade
- **WHEN** um usuário autorizado alterar o nome ou o status de uma especialidade
- **THEN** o sistema SHALL salvar a alteração sem afetar especialidades de outros tenants

### Requirement: Inativação lógica de especialidades
O sistema SHALL tratar exclusão de especialidade como inativação lógica, preservando o histórico e os vínculos já existentes.

#### Scenario: Inativar especialidade
- **WHEN** um usuário autorizado remover uma especialidade da gestão
- **THEN** o sistema SHALL marcar o registro como inativo sem apagá-lo fisicamente

#### Scenario: Reativar especialidade
- **WHEN** um usuário autorizado reativar uma especialidade inativa
- **THEN** o sistema SHALL voltar a exibi-la como disponível para uso operacional

### Requirement: Unicidade do nome por tenant
O sistema SHALL impedir que existam duas especialidades com o mesmo nome dentro do mesmo tenant.

#### Scenario: Nome duplicado na criação
- **WHEN** o usuário tentar criar uma especialidade com nome já existente no tenant
- **THEN** o sistema SHALL rejeitar a operação com erro de validação

#### Scenario: Nome duplicado na edição
- **WHEN** o usuário tentar renomear uma especialidade para um nome já usado no tenant
- **THEN** o sistema SHALL rejeitar a operação com erro de validação

### Requirement: Consumo operacional de especialidades ativas
O sistema SHALL manter os formulários operacionais consumindo apenas especialidades ativas por padrão.

#### Scenario: Combos operacionais
- **WHEN** os formulários de guias, solicitações, profissionais ou valores requisitarem especialidades
- **THEN** o sistema SHALL exibir apenas especialidades ativas, salvo quando a tela administrativa solicitar inativas explicitamente
