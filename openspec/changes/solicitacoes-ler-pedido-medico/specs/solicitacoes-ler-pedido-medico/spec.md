## ADDED Requirements

### Requirement: Acesso ao leitor de pedido médico
O sistema SHALL exibir o botão "Ler pedido médico" ao lado de "Novo" na tela de Solicitações e SHALL abrir a rota `/solicitacoes/ler-pedido-medico`.

#### Scenario: Abrir leitor
- **WHEN** o operador clicar em "Ler pedido médico"
- **THEN** o sistema SHALL navegar para `/solicitacoes/ler-pedido-medico`

### Requirement: Upload e análise por IA
O sistema SHALL permitir enviar PDF, JPG ou PNG de pedido médico e SHALL usar o prompt `ler_solicitacao_medica` configurado para extrair dados estruturados.

#### Scenario: Enviar arquivo aceito
- **WHEN** o operador enviar um arquivo PDF, JPG ou PNG
- **THEN** o sistema SHALL salvar o arquivo como upload pendente e retornar uma prévia de dados extraídos

#### Scenario: Arquivo não aceito
- **WHEN** o operador enviar outro tipo de arquivo
- **THEN** o sistema SHALL rejeitar o upload com erro de validação

### Requirement: Revisão antes de criar solicitação
O sistema SHALL pré-preencher o formulário de nova solicitação com dados extraídos e SHALL exigir confirmação do operador antes de criar a solicitação.

#### Scenario: Pré-preencher formulário
- **WHEN** a IA retornar dados reconhecidos do pedido
- **THEN** o sistema SHALL preencher data, observações e sugestões de paciente, especialidade e médico quando possível

#### Scenario: Criar solicitação revisada
- **WHEN** o operador confirmar convênio, paciente, profissional, especialidade, médico e data
- **THEN** o sistema SHALL criar a solicitação e vincular o pedido médico anexado

### Requirement: Sugestões e criação rápida de cadastros
O sistema SHALL sugerir até cinco pacientes e médicos por similaridade de nome e SHALL permitir criar rapidamente paciente, especialidade e médico quando não houver item adequado.

#### Scenario: Sugerir pacientes
- **WHEN** a IA identificar nome de paciente
- **THEN** o sistema SHALL mostrar até cinco pacientes semelhantes já cadastrados

#### Scenario: Criar novo paciente
- **WHEN** nenhuma sugestão de paciente for adequada
- **THEN** o sistema SHALL permitir criar paciente com nome e convênio informado

#### Scenario: Criar nova especialidade
- **WHEN** a especialidade extraída não existir na lista
- **THEN** o sistema SHALL permitir criar nova especialidade com nome ativo

#### Scenario: Criar novo médico
- **WHEN** nenhum médico sugerido for adequado
- **THEN** o sistema SHALL permitir criar médico solicitante com cadastro parcial a partir do nome

### Requirement: Acesso ao pedido médico anexado
O sistema SHALL manter o pedido médico anexado acessível ao abrir a solicitação pelo nome do paciente na listagem.

#### Scenario: Ver anexo na solicitação
- **WHEN** o operador clicar no nome do paciente de uma solicitação com pedido médico anexado
- **THEN** o sistema SHALL exibir opção para abrir o arquivo anexado
