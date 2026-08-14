## ADDED Requirements

### Requirement: Código da especialidade por convênio
O sistema SHALL permitir informar, no cadastro de uma especialidade, um código de procedimento para cada convênio cadastrado, e SHALL usar esse código como fonte única, compartilhada com a automação da operadora.

#### Scenario: Informar o código em cada convênio
- **WHEN** um usuário autorizado abrir o cadastro de uma especialidade
- **THEN** o sistema SHALL apresentar um campo de código para cada convênio cadastrado, preenchido com o código já gravado

#### Scenario: Convênio novo aparece em todas as especialidades
- **WHEN** um convênio for cadastrado
- **THEN** o sistema SHALL passar a oferecer o campo de código desse convênio em todas as especialidades, sem exigir qualquer migração

#### Scenario: Especialidade não atendida pelo convênio
- **WHEN** o código de um convênio for deixado em branco
- **THEN** o sistema SHALL registrar que aquele convênio não tem código para a especialidade

#### Scenario: Ajuste da automação preservado
- **WHEN** o código for alterado pelo cadastro da especialidade
- **THEN** o sistema SHALL preservar a quantidade padrão e a descrição da operadora configuradas na tela da automação

#### Scenario: Conferência na listagem
- **WHEN** um usuário abrir a listagem de especialidades
- **THEN** o sistema SHALL exibir os códigos já preenchidos de cada especialidade
