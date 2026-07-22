## ADDED Requirements

### Requirement: Subaba de configurações de IA
O sistema SHALL disponibilizar uma subaba "Configurações de IA" dentro da página de Configurações.

#### Scenario: Abrir subaba
- **WHEN** o usuário acessar Configurações
- **THEN** o sistema SHALL permitir selecionar a subaba "Configurações de IA"

### Requirement: Conexão OpenAI por tenant
O sistema SHALL permitir salvar a configuração OpenAI por tenant com API key, base URL, organização, projeto e estado ativo.

#### Scenario: Salvar conexão OpenAI
- **WHEN** o operador preencher os dados de conexão OpenAI e salvar
- **THEN** o sistema SHALL persistir a configuração para o tenant atual

#### Scenario: Preservar API key existente
- **WHEN** a configuração já possuir API key salva e o operador salvar sem informar nova API key
- **THEN** o sistema SHALL preservar a API key existente

#### Scenario: Não expor API key salva
- **WHEN** o sistema carregar a configuração OpenAI
- **THEN** a resposta SHALL NOT incluir a API key em texto claro

### Requirement: Listagem de modelos OpenAI
O sistema SHALL listar modelos disponíveis da OpenAI usando a API key configurada no backend.

#### Scenario: Listar modelos
- **WHEN** o operador solicitar a listagem de modelos com API key configurada
- **THEN** o sistema SHALL consultar a OpenAI e retornar os identificadores de modelos disponíveis

#### Scenario: Falha de conexão
- **WHEN** a OpenAI retornar erro ou a conexão falhar
- **THEN** o sistema SHALL retornar erro tratado sem expor a API key

### Requirement: Prompts operacionais de IA
O sistema SHALL permitir cadastrar prompts de IA por tenant para ler solicitações médicas e sessões escaneadas.

#### Scenario: Prompt para solicitação médica
- **WHEN** o operador editar o prompt `ler_solicitacao_medica`
- **THEN** o sistema SHALL persistir modelo, prompt de sistema, prompt do usuário e estado ativo

#### Scenario: Prompt para sessões escaneadas
- **WHEN** o operador editar o prompt `ler_sessoes_escaneadas`
- **THEN** o sistema SHALL persistir modelo, prompt de sistema, prompt do usuário e estado ativo
