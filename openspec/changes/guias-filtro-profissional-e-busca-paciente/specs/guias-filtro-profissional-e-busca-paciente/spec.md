## ADDED Requirements

### Requirement: Filtro de Guias por profissional executante
O sistema SHALL permitir filtrar a lista de Guias por profissional executante através de um dropdown com os profissionais ativos do tenant, e SHALL repassar o filtro para a API via parâmetro `profissional_id`.

#### Scenario: Filtrar por um profissional
- **WHEN** o usuário selecionar um profissional no dropdown de filtro e clicar em "Aplicar"
- **THEN** o sistema SHALL exibir apenas as guias cujo `profissional_id` corresponda ao selecionado

#### Scenario: Sem profissional selecionado
- **WHEN** o dropdown de filtro estiver na opção "Todos"
- **THEN** o sistema SHALL NOT restringir a lista por profissional

### Requirement: Busca de Guias por nome do paciente
O sistema SHALL permitir buscar a lista de Guias por nome do paciente vinculado através de um campo de texto, com correspondência parcial e sem diferenciar maiúsculas de minúsculas, e SHALL repassar o termo para a API via parâmetro `paciente_nome`.

#### Scenario: Buscar por um trecho do nome
- **WHEN** o usuário digitar parte do nome de um paciente no campo de busca e clicar em "Aplicar"
- **THEN** o sistema SHALL exibir apenas as guias cujo paciente vinculado tenha esse trecho no nome, independente de maiúsculas/minúsculas

#### Scenario: Campo de busca vazio
- **WHEN** o campo de busca por nome estiver vazio
- **THEN** o sistema SHALL NOT restringir a lista por nome de paciente
