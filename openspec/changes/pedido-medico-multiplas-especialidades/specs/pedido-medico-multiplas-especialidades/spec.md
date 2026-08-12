## ADDED Requirements

### Requirement: Leitura de várias especialidades por pedido
O sistema SHALL extrair do pedido médico todas as especialidades citadas, e não apenas uma.

#### Scenario: Pedido multidisciplinar
- **WHEN** o documento citar mais de uma especialidade
- **THEN** o sistema SHALL devolver uma entrada por especialidade citada

#### Scenario: Pedido com uma especialidade
- **WHEN** o documento citar apenas uma especialidade
- **THEN** o sistema SHALL devolver uma única entrada, sem tratamento especial

#### Scenario: Especialidade repetida no documento
- **WHEN** a mesma especialidade for citada mais de uma vez
- **THEN** o sistema SHALL devolvê-la uma única vez

#### Scenario: Preenchimento do formulário
- **WHEN** o operador analisar um pedido com várias especialidades
- **THEN** o sistema SHALL criar uma linha do pedido para cada especialidade que tenha cadastro correspondente

### Requirement: Distinção entre cadastro correspondente e palpite
O sistema SHALL aplicar automaticamente apenas as especialidades cujo cadastro correspondente for encontrado com alta confiança, e SHALL oferecer o cadastro do termo lido nos demais casos.

#### Scenario: Correspondência clara
- **WHEN** um cadastro existente for suficientemente semelhante ao termo lido
- **THEN** o sistema SHALL acrescentá-lo ao pedido automaticamente

#### Scenario: Semelhança insuficiente
- **WHEN** nenhum cadastro atingir a confiança mínima
- **THEN** o sistema SHALL NOT aplicar nenhum automaticamente, e SHALL oferecer a criação de uma especialidade com o termo lido

#### Scenario: Palpite continua visível
- **WHEN** houver cadastros parecidos abaixo da confiança mínima
- **THEN** o sistema SHALL exibi-los como sugestões clicáveis, junto do convite para cadastrar

#### Scenario: Cadastrar a especialidade lida
- **WHEN** o operador aceitar cadastrar o termo lido
- **THEN** o sistema SHALL criar a especialidade e acrescentá-la ao pedido, sem remover as especialidades já escolhidas

### Requirement: Ordem dos campos no formulário
O sistema SHALL apresentar o médico solicitante antes das especialidades do pedido.

#### Scenario: Conferir o pedido lido
- **WHEN** o operador revisar o resultado da leitura
- **THEN** o sistema SHALL exibir o bloco do médico solicitante acima do bloco de especialidades
