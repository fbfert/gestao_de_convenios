## MODIFIED Requirements

### Requirement: Acesso ao detalhe de guia
O sistema SHALL disponibilizar a rota autenticada `/guias/:id` e SHALL carregar a guia pelo endpoint existente `GET /api/guias/{id}`, sem criar endpoint adicional. A resposta de detalhe SHALL incluir os dados de paciente, convênio, profissional, especialidade, antecipações e conciliações já vinculados à guia. Quando a guia possuir vínculo com item de Solicitação ou execução de automação Unimed, a resposta SHALL incluir também esses vínculos operacionais sem remover os campos existentes.

#### Scenario: Abrir uma guia pela lista
- **WHEN** o usuário clicar no número de uma guia exibida na lista
- **THEN** o sistema SHALL navegar para `/guias/{id}` e apresentar os dados daquela guia

#### Scenario: Carregamento do detalhe
- **WHEN** a consulta da guia estiver em andamento
- **THEN** o sistema SHALL exibir um estado de carregamento visível

#### Scenario: Guia inexistente ou fora do tenant
- **WHEN** a API retornar 404 para o identificador informado na rota
- **THEN** o sistema SHALL exibir um estado de erro tratado em vez de uma tela em branco

#### Scenario: Guia vinculada a item e automação
- **WHEN** uma guia gerada por automação Unimed possuir `solicitacao_item_id` e execução relacionada
- **THEN** o endpoint `GET /api/guias/{id}` SHALL retornar os dados do item e um resumo da execução sem expor segredos
