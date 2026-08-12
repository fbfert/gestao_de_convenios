## ADDED Requirements

### Requirement: Modelo padrão da conexão
O sistema SHALL permitir definir um modelo padrão na conexão com a OpenAI, usado quando o prompt não indicar um próprio.

#### Scenario: Escolher pela lista
- **WHEN** o usuário listar os modelos disponíveis e clicar em um deles
- **THEN** o sistema SHALL preenchê-lo como modelo padrão, aguardando o salvamento

#### Scenario: Prompt sem modelo próprio
- **WHEN** uma leitura usar um prompt que não define modelo
- **THEN** o sistema SHALL usar o modelo padrão da conexão

#### Scenario: Prompt com modelo próprio
- **WHEN** o prompt definir um modelo
- **THEN** o sistema SHALL usar o do prompt, ignorando o padrão da conexão

#### Scenario: Nada configurado
- **WHEN** nem o prompt nem a conexão indicarem modelo
- **THEN** o sistema SHALL usar um modelo padrão interno, sem falhar

#### Scenario: Salvar sem reescrever a credencial
- **WHEN** o usuário salvar a conexão deixando a API key em branco
- **THEN** o sistema SHALL gravar o modelo padrão preservando a chave já existente

### Requirement: Diagnóstico da recusa da OpenAI
O sistema SHALL apresentar o motivo relatado pela OpenAI quando a listagem de modelos for recusada.

#### Scenario: Requisição recusada
- **WHEN** a OpenAI recusar a listagem
- **THEN** o sistema SHALL exibir o código de status e a mensagem devolvida por ela

#### Scenario: Campos de identificação
- **WHEN** o usuário abrir os campos de organização e projeto
- **THEN** o sistema SHALL indicar que esperam o identificador, e não o nome
