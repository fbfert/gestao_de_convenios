## ADDED Requirements

### Requirement: Registro de componentes por tenant
O sistema SHALL manter um registro de componentes de saúde por tenant, com chave, nome, tipo (`worker`, `scheduler`, `queue`, `smtp` ou `connector`), convênio opcional, intervalo esperado entre heartbeats em segundos e estado ativo.

#### Scenario: Registrar componente novo sem alterar código
- **WHEN** uma linha de componente for inserida para um tenant
- **THEN** o sistema SHALL passar a considerá-la em `GET /saude` e no card de saúde sem exigir alteração de código do dashboard

#### Scenario: Chave única por tenant
- **WHEN** for inserido um componente com chave já existente no mesmo tenant
- **THEN** o sistema SHALL rejeitar o registro

#### Scenario: Componente de automação vinculado a convênio
- **WHEN** o componente for do tipo `worker` ou `connector`
- **THEN** o sistema SHALL permitir associá-lo a um convênio, de modo que cada convênio automatizado tenha seu próprio componente

#### Scenario: Isolamento entre tenants
- **WHEN** um usuário consultar a saúde dos componentes
- **THEN** o sistema SHALL retornar apenas os componentes do tenant do usuário autenticado

### Requirement: Heartbeat empurrado pelo componente
O sistema SHALL registrar um heartbeat quando o componente concluir seu trabalho com sucesso, atualizando a data do último heartbeat, o último status e a última mensagem.

#### Scenario: Execução bem-sucedida registra heartbeat
- **WHEN** o worker de automação concluir uma execução com sucesso
- **THEN** o sistema SHALL atualizar a data do último heartbeat do componente correspondente

#### Scenario: Agendador registra heartbeat a cada rodada
- **WHEN** o agendador executar uma rodada
- **THEN** o sistema SHALL atualizar a data do último heartbeat do componente do agendador

#### Scenario: Envio de e-mail registra heartbeat
- **WHEN** um e-mail for enviado com sucesso
- **THEN** o sistema SHALL atualizar a data do último heartbeat do componente de SMTP

#### Scenario: Heartbeat de componente desativado é registrado
- **WHEN** chegar um heartbeat de um componente com estado ativo falso
- **THEN** o sistema SHALL registrar o heartbeat normalmente, ainda que o componente não seja exibido

### Requirement: Estado derivado do heartbeat
O sistema SHALL derivar o estado do componente comparando o tempo decorrido desde o último heartbeat com o intervalo esperado, e SHALL NOT permitir que o estado seja gravado diretamente.

#### Scenario: Heartbeat dentro do intervalo esperado
- **WHEN** o último heartbeat for mais recente que o intervalo esperado do componente
- **THEN** o sistema SHALL derivar o estado `healthy`

#### Scenario: Heartbeat atrasado até três vezes o intervalo
- **WHEN** o último heartbeat for mais antigo que o intervalo esperado e mais recente que três vezes esse intervalo
- **THEN** o sistema SHALL derivar o estado `warning`

#### Scenario: Heartbeat além de três vezes o intervalo
- **WHEN** o último heartbeat for mais antigo que três vezes o intervalo esperado
- **THEN** o sistema SHALL derivar o estado `down`

#### Scenario: Componente que nunca registrou heartbeat
- **WHEN** o componente não possuir nenhum heartbeat registrado
- **THEN** o sistema SHALL derivar o estado `down`

### Requirement: Consulta de saúde dos componentes
O sistema SHALL expor `GET /saude`, autenticado, devolvendo os componentes ativos do tenant com o estado derivado, a data do último heartbeat e a última mensagem de cada um.

#### Scenario: Listar componentes com estado derivado
- **WHEN** o usuário autenticado consultar `GET /saude`
- **THEN** o sistema SHALL retornar cada componente ativo do tenant com seu estado derivado

#### Scenario: Componente desativado fora da listagem
- **WHEN** um componente tiver estado ativo falso
- **THEN** o sistema SHALL omiti-lo da resposta de `GET /saude`

### Requirement: Componentes no endpoint público de saúde
O sistema SHALL preencher o array `componentes` do `GET /api/health` com a chave e o estado derivado de cada componente, e SHALL NOT incluir ali qualquer informação que identifique tenant, usuário ou volume de dados.

#### Scenario: Endpoint público lista chave e estado
- **WHEN** o monitor externo consultar `GET /api/health`
- **THEN** o sistema SHALL incluir, para cada componente, apenas a chave e o estado derivado

#### Scenario: Endpoint público não identifica tenant
- **WHEN** o monitor externo consultar `GET /api/health`
- **THEN** a resposta SHALL NOT conter nome de tenant, identificador de tenant, e-mail ou contagem de registros

### Requirement: Card de saúde no dashboard
O sistema SHALL exibir no dashboard um card de saúde com uma linha por componente, indicando o estado e há quanto tempo o componente respondeu.

#### Scenario: Uma linha por componente
- **WHEN** o dashboard for carregado e o tenant possuir componentes ativos
- **THEN** o sistema SHALL exibir uma linha por componente, com o estado e o tempo desde o último heartbeat

#### Scenario: Estado não é comunicado só por cor
- **WHEN** o card exibir o estado de um componente
- **THEN** o sistema SHALL comunicar esse estado também por texto ou ícone, além da cor

#### Scenario: Todos os componentes saudáveis
- **WHEN** todos os componentes do tenant estiverem `healthy`
- **THEN** o sistema SHALL exibir o card de forma discreta, em uma linha de resumo, sem ocultá-lo

#### Scenario: Tenant sem componente nenhum
- **WHEN** o tenant não possuir nenhum componente ativo
- **THEN** o sistema SHALL omitir o card do dashboard

#### Scenario: Componente fora não oferece reinício
- **WHEN** um componente estiver no estado `down`
- **THEN** o sistema SHALL informar o horário desde quando ele não responde
- **AND** o sistema SHALL NOT oferecer qualquer ação de reiniciar o componente

### Requirement: Componentes padrão para tenant novo
O sistema SHALL criar os componentes padrão de agendador, fila e SMTP ao popular um tenant novo.

#### Scenario: Carga inicial cria os componentes padrão
- **WHEN** um tenant for populado com os dados iniciais
- **THEN** o sistema SHALL criar os componentes dos tipos `scheduler`, `queue` e `smtp` para esse tenant
