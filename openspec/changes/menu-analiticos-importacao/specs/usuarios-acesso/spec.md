## ADDED Requirements

### Requirement: Acesso contextual a permissões dentro de Usuários
O sistema SHALL disponibilizar a gestão de permissões dentro do CRUD de Usuários por meio de um acionador contextual.

#### Scenario: Abrir permissões a partir de um usuário
- **WHEN** o operador visualizar um usuário na lista ou no formulário de usuários
- **THEN** o sistema SHALL oferecer um acionador para gerenciar permissões daquele contexto

### Requirement: Remover dependência do menu global
O sistema SHALL permitir que a gestão de permissões seja acessada sem depender de uma entrada global no menu superior.

#### Scenario: Fluxo sem item global
- **WHEN** o usuário navegar pelo sistema
- **THEN** o acesso a permissões SHALL continuar disponível dentro do CRUD de Usuários mesmo sem item global no menu
