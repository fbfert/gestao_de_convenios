## ADDED Requirements

### Requirement: Profissional atuando em várias especialidades
O sistema SHALL permitir registrar todas as especialidades em que um profissional atende, mantendo uma delas como principal.

#### Scenario: Registrar especialidades adicionais
- **WHEN** o usuário marcar especialidades além da principal no cadastro do profissional
- **THEN** o sistema SHALL gravar todas elas como especialidades em que o profissional atende

#### Scenario: Principal sempre incluída
- **WHEN** a lista enviada não contiver a especialidade principal
- **THEN** o sistema SHALL incluí-la mesmo assim

#### Scenario: Principal não pode ser desmarcada
- **WHEN** o usuário abrir o cadastro de um profissional
- **THEN** o sistema SHALL exibir a especialidade principal marcada e impedir que seja desmarcada

#### Scenario: Trocar as adicionais
- **WHEN** o usuário salvar uma lista diferente de especialidades adicionais
- **THEN** o sistema SHALL substituir as anteriores, preservando a principal

#### Scenario: Profissional criado fora da tela
- **WHEN** um profissional for criado por carga inicial ou por qualquer outro caminho que não o formulário
- **THEN** o sistema SHALL garantir que ele fique registrado na própria especialidade principal

### Requirement: Seleção e filtro por especialidade
O sistema SHALL considerar todas as especialidades em que o profissional atende ao filtrá-lo ou oferecê-lo por especialidade.

#### Scenario: Filtrar a lista de profissionais
- **WHEN** o usuário filtrar por uma especialidade
- **THEN** o sistema SHALL listar todos que atendem nela, inclusive quem a tem apenas como adicional

#### Scenario: Escolher o executante da solicitação
- **WHEN** o usuário escolher a especialidade de uma linha da solicitação
- **THEN** o sistema SHALL oferecer todos os profissionais que atendem naquela especialidade
