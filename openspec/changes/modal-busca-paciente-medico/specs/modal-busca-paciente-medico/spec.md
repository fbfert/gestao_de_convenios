## ADDED Requirements

### Requirement: Seleção de paciente por modal de busca
Os campos de Paciente nos formulários de Solicitação e Guia SHALL usar um modal de busca em vez de um dropdown com a lista completa, permitindo localizar o paciente por nome, CPF ou carteirinha.

#### Scenario: Abrir o modal sem buscar
- **WHEN** o usuário abrir o modal de seleção de paciente sem digitar nada
- **THEN** o sistema SHALL exibir os pacientes mais recentemente usados em solicitações do tenant

#### Scenario: Buscar por nome, CPF ou carteirinha
- **WHEN** o usuário digitar 2 ou mais caracteres no campo de busca
- **THEN** o sistema SHALL, após um debounce de aproximadamente 500ms, consultar a API e exibir os pacientes cujo nome, CPF ou carteirinha correspondam ao termo digitado

#### Scenario: Selecionar um paciente da lista
- **WHEN** o usuário clicar em um paciente da lista de resultados
- **THEN** o sistema SHALL preencher o campo de Paciente do formulário com o paciente selecionado e fechar o modal

#### Scenario: Busca sem resultados
- **WHEN** a busca não retornar nenhum paciente
- **THEN** o sistema SHALL exibir a opção "Cadastrar novo", permitindo criar um paciente sem sair do formulário

### Requirement: Seleção de médico por modal de busca
Os campos de Médico nos formulários de Solicitação e Guia SHALL usar um modal de busca em vez de um dropdown com a lista completa, permitindo localizar o médico por nome, CRM ou especialidade.

#### Scenario: Abrir o modal sem buscar
- **WHEN** o usuário abrir o modal de seleção de médico sem digitar nada
- **THEN** o sistema SHALL exibir os médicos mais recentemente usados em solicitações do tenant

#### Scenario: Buscar por nome, CRM ou especialidade
- **WHEN** o usuário digitar 2 ou mais caracteres no campo de busca
- **THEN** o sistema SHALL, após um debounce de aproximadamente 500ms, consultar a API e exibir os médicos cujo nome, CRM ou especialidade correspondam ao termo digitado

#### Scenario: Selecionar um médico da lista
- **WHEN** o usuário clicar em um médico da lista de resultados
- **THEN** o sistema SHALL preencher o campo de Médico do formulário com o médico selecionado e fechar o modal

#### Scenario: Busca sem resultados
- **WHEN** a busca não retornar nenhum médico
- **THEN** o sistema SHALL exibir a opção "Cadastrar novo", permitindo criar um médico sem sair do formulário

### Requirement: Paginação das listagens de paciente e médico
As listagens de pacientes e médicos usadas pelos modais de busca SHALL suportar paginação, para não retornar a tabela inteira do tenant em uma única resposta.

#### Scenario: Buscar em uma base grande
- **WHEN** o tenant tiver mais registros do que cabem em uma página
- **THEN** a API SHALL retornar apenas a página solicitada, com informação suficiente para o cliente pedir a próxima

### Requirement: Permissão de leitura de médicos separada da de gestão
O sistema SHALL expor uma permissão `medicos.view`, distinta de `medicos.manage`, para consultar a listagem de médicos sem exigir a permissão de cadastrar/editar médicos.

#### Scenario: Usuário com apenas medicos.view
- **WHEN** um usuário tiver a permissão `medicos.view` mas não `medicos.manage`
- **THEN** o sistema SHALL permitir a chamada de listagem de médicos (`GET /medicos`) e SHALL continuar bloqueando criação/edição de médico

#### Scenario: Papéis padrão continuam funcionando
- **WHEN** um usuário tiver o papel padrão `admin` ou `funcionario`
- **THEN** o sistema SHALL conceder tanto `medicos.view` quanto `medicos.manage` a esse usuário, sem exigir reconfiguração manual
