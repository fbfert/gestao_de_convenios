## ADDED Requirements

### Requirement: Listagem e formulário em telas separadas
O sistema SHALL exibir a listagem de um CRUD em uma tela própria e SHALL exibir o formulário de criação em uma tela própria separada da listagem.

#### Scenario: Abrir listagem sem formulário embutido
- **WHEN** o usuário acessar uma página de listagem de CRUD
- **THEN** o sistema SHALL mostrar apenas a listagem e suas ações de consulta, sem renderizar o formulário inline de cadastro ou edição

#### Scenario: Abrir formulário em rota própria
- **WHEN** o usuário iniciar a criação ou edição de um registro
- **THEN** o sistema SHALL navegar para uma tela própria de formulário sem manter a listagem visível no mesmo layout principal

### Requirement: Ações de novo e inserir abrem formulário dedicado
O sistema SHALL abrir uma tela própria de cadastro quando o usuário clicar em `Novo` ou `Inserir` em qualquer CRUD afetado.

#### Scenario: Criar novo registro
- **WHEN** o usuário clicar em `Novo` ou `Inserir`
- **THEN** o sistema SHALL abrir a tela dedicada de criação correspondente ao módulo

#### Scenario: Preservar retorno para listagem
- **WHEN** o usuário cancelar ou concluir o cadastro
- **THEN** o sistema SHALL permitir retorno à listagem do módulo
