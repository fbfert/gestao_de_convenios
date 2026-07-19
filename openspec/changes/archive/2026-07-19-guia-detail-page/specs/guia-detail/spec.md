## ADDED Requirements

### Requirement: Acesso ao detalhe de guia
O sistema SHALL disponibilizar a rota autenticada `/guias/:id` e SHALL carregar a guia pelo endpoint existente `GET /api/guias/{id}`, sem criar endpoint adicional. A resposta de detalhe SHALL incluir os dados de paciente, convênio, profissional, especialidade, antecipações e conciliações já vinculados à guia.

#### Scenario: Abrir uma guia pela lista
- **WHEN** o usuário clicar no número de uma guia exibida na lista
- **THEN** o sistema SHALL navegar para `/guias/{id}` e apresentar os dados daquela guia

#### Scenario: Carregamento do detalhe
- **WHEN** a consulta da guia estiver em andamento
- **THEN** o sistema SHALL exibir um estado de carregamento visível

#### Scenario: Guia inexistente ou fora do tenant
- **WHEN** a API retornar 404 para o identificador informado na rota
- **THEN** o sistema SHALL exibir um estado de erro tratado em vez de uma tela em branco

### Requirement: Informações operacionais da guia
O sistema SHALL exibir número, status traduzido por `translateStatus('guias', ...)`, tipo de terapia, paciente e carteirinha, convênio, profissional, especialidade, datas de solicitação e finalização, senha e validade da senha.

#### Scenario: Exibir uma guia finalizada
- **WHEN** uma guia finalizada for carregada
- **THEN** o sistema SHALL apresentar a senha, a validade e a data de finalização retornadas pela API

#### Scenario: Destacar validade próxima
- **WHEN** a validade da senha estiver dentro de sete dias a partir da data atual
- **THEN** o sistema SHALL aplicar o mesmo destaque visual de prazo próximo usado na lista de guias

### Requirement: Vínculos financeiros da guia
O sistema SHALL exibir a cota `qtd_utilizada/qtd_autorizada` para antecipações vinculadas e o status traduzido para a conciliação vinculada, incluindo links às respectivas páginas de domínio.

#### Scenario: Guia com antecipação vinculada
- **WHEN** a guia possuir uma ou mais antecipações
- **THEN** o sistema SHALL exibir a cota de cada antecipação e um link para `/antecipacoes`

#### Scenario: Guia com conciliação gerada
- **WHEN** a guia possuir conciliação financeira
- **THEN** o sistema SHALL exibir seu status traduzido e um link para `/conciliacao`

### Requirement: Ações de status em telas de guia
O sistema SHALL disponibilizar as ações de finalizar e negar em uma guia em análise tanto na lista quanto no detalhe, reutilizando o mesmo fluxo de finalização e as mutations existentes.

#### Scenario: Finalizar pelo detalhe
- **WHEN** o usuário finalizar uma guia na página de detalhe com os dados exigidos
- **THEN** o sistema SHALL atualizar o status exibido no detalhe sem recarregar manualmente o navegador

#### Scenario: Negar pelo detalhe
- **WHEN** o usuário negar uma guia em análise na página de detalhe
- **THEN** o sistema SHALL atualizar o status exibido no detalhe sem recarregar manualmente o navegador
