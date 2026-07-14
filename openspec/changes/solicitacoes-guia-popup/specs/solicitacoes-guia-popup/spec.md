## ADDED Requirements

### Requirement: Popup de detalhes da guia na lista de solicitações
O sistema SHALL permitir abrir um popup/modal com os detalhes da guia vinculada quando o usuário clicar no nome do paciente na lista de Solicitações.

#### Scenario: Abrir popup com guia vinculada
- **WHEN** uma solicitação tiver guia vinculada e o usuário clicar no nome do paciente
- **THEN** o sistema SHALL abrir um popup com os dados resumidos da guia

#### Scenario: Ausência de guia vinculada
- **WHEN** a solicitação ainda não possuir guia vinculada
- **THEN** o sistema SHALL exibir um estado vazio explicando que não há guia para exibir

### Requirement: Estados visíveis do popup
O sistema SHALL exibir estados visíveis de carregamento e erro ao buscar os detalhes da guia no popup.

#### Scenario: Carregamento
- **WHEN** o usuário abrir o popup antes da resposta da consulta terminar
- **THEN** o sistema SHALL mostrar um estado de carregamento

#### Scenario: Erro de busca
- **WHEN** a consulta dos detalhes da guia falhar
- **THEN** o sistema SHALL mostrar uma mensagem de erro tratada
