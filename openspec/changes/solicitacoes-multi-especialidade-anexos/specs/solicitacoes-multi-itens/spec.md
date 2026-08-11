## ADDED Requirements

### Requirement: Entrada de vários itens pela interface
O sistema SHALL permitir que o operador informe mais de uma especialidade em um mesmo pedido, escolhendo o profissional executante de cada uma, tanto no cadastro manual quanto no fluxo de leitura do pedido médico.

#### Scenario: Adicionar uma segunda especialidade
- **WHEN** o operador acrescentar uma segunda especialidade com o seu profissional
- **THEN** o sistema SHALL criar a Solicitação com dois itens, cada um com o seu profissional

#### Scenario: Profissional limitado à especialidade da linha
- **WHEN** o operador escolher a especialidade de uma linha
- **THEN** o sistema SHALL oferecer apenas profissionais daquela especialidade para aquela linha

#### Scenario: Pedido sem nenhuma especialidade
- **WHEN** restar uma única linha de especialidade no formulário
- **THEN** o sistema SHALL impedir a remoção dessa linha

#### Scenario: Especialidade repetida
- **WHEN** o operador informar a mesma especialidade em duas linhas
- **THEN** o sistema SHALL avisar que cada uma virará uma guia separada, sem impedir o envio

### Requirement: Código do procedimento visível na escolha da especialidade
O sistema SHALL exibir, junto do nome da especialidade, o código de procedimento definido para o convênio do pedido, quando houver mapeamento ativo.

#### Scenario: Convênio com mapeamento ativo
- **WHEN** o pedido tiver convênio com mapeamento ativo para a especialidade
- **THEN** o sistema SHALL exibir nome e código do procedimento na escolha da especialidade

#### Scenario: Convênio sem mapeamento
- **WHEN** não houver mapeamento ativo para a especialidade naquele convênio
- **THEN** o sistema SHALL exibir apenas o nome da especialidade

### Requirement: Anexos da Solicitação e por especialidade
O sistema SHALL permitir anexar Pedido Médico e Laudo Médico à Solicitação, e Plano Individualizado e Relatório de Evolução a uma especialidade do pedido, aceitando imagem ou PDF dentro do limite aceito pela operadora.

#### Scenario: Anexo por especialidade
- **WHEN** o operador anexar Plano Individualizado a uma especialidade do pedido
- **THEN** o sistema SHALL vincular o documento àquele item e mantê-lo fora dos demais itens

#### Scenario: Anexo de nível incompatível
- **WHEN** o operador enviar Plano Individualizado sem informar a especialidade, ou Laudo Médico informando uma
- **THEN** o sistema SHALL recusar a operação explicando o nível correto do anexo

#### Scenario: Arquivo fora do formato ou do tamanho aceito
- **WHEN** o operador enviar arquivo que não seja imagem ou PDF, ou maior que o limite da operadora
- **THEN** o sistema SHALL recusar o anexo informando o motivo

#### Scenario: Segundo Pedido Médico
- **WHEN** a Solicitação já tiver Pedido Médico e o operador enviar outro
- **THEN** o sistema SHALL recusar o novo arquivo e orientar a remoção do atual, sem sobrescrever o existente

#### Scenario: Especialidade de outra Solicitação
- **WHEN** o anexo referenciar especialidade que não pertence àquela Solicitação
- **THEN** o sistema SHALL recusar a operação

### Requirement: Anexos imutáveis depois da Guia
O sistema SHALL impedir a remoção de anexos cujo envio já resultou em Guia, preservando a evidência do que sustentou a autorização.

#### Scenario: Anexo de especialidade já com Guia
- **WHEN** o operador tentar remover anexo de uma especialidade que já tem Guia
- **THEN** o sistema SHALL recusar a remoção

#### Scenario: Anexo do pedido com alguma Guia gerada
- **WHEN** o operador tentar remover Pedido Médico ou Laudo e qualquer especialidade do pedido já tiver Guia
- **THEN** o sistema SHALL recusar a remoção

#### Scenario: Especialidade ainda sem Guia
- **WHEN** o operador remover anexo de uma especialidade que ainda não tem Guia
- **THEN** o sistema SHALL remover o documento normalmente

### Requirement: Anexos enviados à operadora por especialidade
O sistema SHALL enviar à operadora, para cada especialidade, os anexos da Solicitação e os daquela especialidade, sem incluir anexos das demais.

#### Scenario: Pedido com duas especialidades e planos distintos
- **WHEN** cada especialidade tiver o seu Plano Individualizado e a Solicitação tiver um Laudo
- **THEN** o sistema SHALL enviar, em cada envio, o Laudo da Solicitação e apenas o Plano da especialidade correspondente

## MODIFIED Requirements

### Requirement: Cadastro rápido de paciente no fluxo de pedido médico
O sistema SHALL exigir carteirinha ao criar paciente pelo cadastro rápido, aplicando a mesma regra do cadastro completo quando o convênio for automatizado, em vez de gerar identificador provisório.

#### Scenario: Cadastro rápido sem carteirinha
- **WHEN** o operador criar paciente pelo cadastro rápido sem informar carteirinha
- **THEN** o sistema SHALL recusar a criação

#### Scenario: Cadastro rápido em convênio Unimed
- **WHEN** o convênio for automatizado e a carteirinha não tiver os dígitos exigidos
- **THEN** o sistema SHALL recusar a criação informando o formato esperado
