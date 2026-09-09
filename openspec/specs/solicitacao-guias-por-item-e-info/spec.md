# solicitacao-guias-por-item-e-info Specification

## Purpose
TBD - created by archiving change solicitacao-guias-por-item-e-info. Update Purpose after archive.

## Requirements

### Requirement: Guia exposta em cada item da solicitação
O sistema SHALL expor, em cada item da solicitação, os dados da guia correspondente — identificador, número e status — e SHALL NOT exigir consulta adicional para isso.

#### Scenario: Item com guia traz os dados dela
- **WHEN** a solicitação for consultada e um item tiver guia
- **THEN** o sistema SHALL devolver naquele item o identificador, o número e o status da guia

#### Scenario: Item sem guia
- **WHEN** um item ainda não tiver guia
- **THEN** o sistema SHALL devolver o campo de guia vazio para esse item

#### Scenario: A listagem não ganha consulta nova
- **WHEN** a listagem de solicitações for consultada
- **THEN** o sistema SHALL executar o mesmo número de consultas de antes desta mudança

### Requirement: Guias apresentadas por item
O sistema SHALL apresentar as guias de uma solicitação a partir dos itens dela, uma apresentação por item, e SHALL NOT usar a relação direta entre solicitação e guia para essa apresentação.

#### Scenario: Duas especialidades com duas guias
- **WHEN** uma solicitação tiver dois itens, ambos com guia
- **THEN** o sistema SHALL apresentar duas abas

#### Scenario: Três especialidades com uma guia
- **WHEN** uma solicitação tiver três itens e apenas um com guia
- **THEN** o sistema SHALL apresentar três abas
- **AND** as duas sem guia SHALL informar que a guia ainda será gerada

#### Scenario: Rótulo identifica a especialidade
- **WHEN** uma aba for apresentada
- **THEN** o rótulo SHALL conter o nome da especialidade do item

#### Scenario: Navegar para a guia
- **WHEN** o usuário acionar o número da guia dentro da aba
- **THEN** o sistema SHALL abrir a tela de detalhe daquela guia

#### Scenario: Solicitação antiga sem itens
- **WHEN** uma solicitação não tiver item nenhum
- **THEN** o sistema SHALL manter a mensagem de solicitação sem guia vinculada

### Requirement: Número da operadora em vez do identificador interno
O sistema SHALL exibir o número da guia atribuído pela operadora, e SHALL NOT exibir o identificador interno da guia como se fosse esse número.

#### Scenario: Guia com número da operadora
- **WHEN** a guia tiver número atribuído pela operadora
- **THEN** o sistema SHALL exibir esse número

#### Scenario: Guia de convênio manual
- **WHEN** o número da guia for o valor de preenchimento gerado internamente para convênio manual
- **THEN** o sistema SHALL tratá-lo como ausência de número
- **AND** SHALL NOT exibir esse valor como número da guia

#### Scenario: Guia ainda sem número
- **WHEN** a guia não tiver número
- **THEN** o sistema SHALL informar que a guia existe e que o número está pendente

#### Scenario: Ausência de número não sugere falha
- **WHEN** a guia existir sem número da operadora
- **THEN** o texto exibido SHALL afirmar que a guia foi gerada

### Requirement: Situação real da guia na listagem
O sistema SHALL exibir na listagem a situação atual da guia, traduzida, para qualquer convênio.

#### Scenario: Situação aparece em convênio manual
- **WHEN** um item tiver guia e o convênio não for automatizado
- **THEN** o sistema SHALL exibir a situação da guia

#### Scenario: Situação de guia histórica
- **WHEN** a guia estiver em uma das situações de histórico
- **THEN** o sistema SHALL exibir a tradução correspondente

### Requirement: Coluna de informação rápida
O sistema SHALL oferecer na listagem uma coluna com quatro indicadores — datas, observações, anexos e CID — cujo conteúdo é revelado sob demanda.

#### Scenario: Datas do pedido
- **WHEN** o usuário consultar o indicador de datas
- **THEN** o sistema SHALL mostrar a data da solicitação, a de cadastro e a da última atualização

#### Scenario: Anexos identificados por tipo e nome
- **WHEN** a solicitação ou seus itens tiverem anexos
- **THEN** o sistema SHALL mostrar o tipo e o nome de cada um

#### Scenario: CID com código e descrição
- **WHEN** a solicitação tiver CID
- **THEN** o sistema SHALL mostrar o código e a descrição de cada um

#### Scenario: Indicador sem conteúdo permanece na posição
- **WHEN** um indicador não tiver conteúdo
- **THEN** o sistema SHALL mantê-lo visível e apagado
- **AND** SHALL NOT torná-lo alcançável por navegação de teclado

#### Scenario: Situação não é comunicada só por cor
- **WHEN** os indicadores forem apresentados
- **THEN** o sistema SHALL NOT usar cor de perigo em indicador que não represente perigo

#### Scenario: Coluna identificada no modo cartão
- **WHEN** a listagem for exibida em largura reduzida
- **THEN** o sistema SHALL rotular a célula de informação como as demais

### Requirement: Data da solicitação visível na linha
O sistema SHALL exibir a data da solicitação junto ao nome do paciente na listagem, sem exigir interação.

#### Scenario: Data aparece sob o paciente
- **WHEN** a listagem for exibida
- **THEN** o sistema SHALL mostrar a data da solicitação abaixo do nome do paciente

### Requirement: Rótulo do médico consistente
O sistema SHALL usar o mesmo rótulo para a coluna do médico no cabeçalho, no filtro e na exibição em largura reduzida.

#### Scenario: Rótulo igual nas três superfícies
- **WHEN** a listagem for exibida em qualquer largura
- **THEN** o rótulo da coluna do médico SHALL ser o mesmo do cabeçalho e do filtro
