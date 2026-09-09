## ADDED Requirements

### Requirement: Guia para cada item em convênio sem automação
O sistema SHALL gerar uma guia para cada item ainda sem guia quando a solicitação de convênio sem automação for marcada como pronta, e cada guia SHALL nascer com um número distinto das demais guias da mesma solicitação.

#### Scenario: Três especialidades geram três guias
- **WHEN** uma solicitação de convênio sem automação com três itens for marcada como pronta
- **THEN** o sistema SHALL gerar três guias, uma por item

#### Scenario: Números não colidem
- **WHEN** mais de uma guia for gerada para a mesma solicitação
- **THEN** cada guia SHALL receber um número distinto das outras

#### Scenario: Item que já tem guia não é tocado
- **WHEN** parte dos itens já tiver guia
- **THEN** o sistema SHALL gerar guia apenas para os itens sem guia
- **AND** SHALL NOT alterar as guias existentes

#### Scenario: Repetir a sincronização não duplica
- **WHEN** a sincronização for executada duas vezes seguidas
- **THEN** o sistema SHALL manter uma guia por item

### Requirement: Envio à operadora decidido por item
O sistema SHALL decidir a permissão de envio à operadora item a item, e SHALL aplicar a mesma decisão na interface e no servidor.

#### Scenario: Item novo em solicitação já aprovada
- **WHEN** uma solicitação aprovada receber um item sem guia
- **THEN** o sistema SHALL permitir o envio desse item

#### Scenario: Solicitação ainda em análise
- **WHEN** a solicitação estiver em análise
- **THEN** o sistema SHALL recusar o envio
- **AND** a recusa SHALL valer também para quem chamar a operação diretamente, sem passar pela tela

#### Scenario: Solicitação negada ou histórica
- **WHEN** a solicitação estiver negada ou em histórico
- **THEN** o sistema SHALL recusar o envio

#### Scenario: Item já resolvido ou em andamento
- **WHEN** o item já tiver guia ou já tiver um envio em andamento
- **THEN** o sistema SHALL recusar um novo envio

### Requirement: Situação da solicitação reflete as guias dos itens
O sistema SHALL derivar a situação da solicitação do conjunto de itens, podendo retroceder quando surgir item sem guia, e SHALL NOT alterar solicitações em análise, negadas ou históricas.

#### Scenario: Surge item sem guia
- **WHEN** uma solicitação com guia gerada ou aprovada receber um item sem guia
- **THEN** o sistema SHALL devolvê-la à situação de pronta para automatização

#### Scenario: Todos com guia, nem todas autorizadas
- **WHEN** todos os itens tiverem guia e nem todas estiverem autorizadas
- **THEN** o sistema SHALL marcar a solicitação como guia gerada

#### Scenario: Todas autorizadas
- **WHEN** todas as guias dos itens estiverem autorizadas ou finalizadas
- **THEN** o sistema SHALL marcar a solicitação como aprovada

#### Scenario: Situações que não mudam
- **WHEN** a solicitação estiver em análise, negada ou em histórico
- **THEN** o sistema SHALL manter a situação inalterada, mesmo recebendo item novo

### Requirement: Acrescentar item a uma solicitação existente
O sistema SHALL permitir acrescentar item a uma solicitação já criada, sem exigir nova solicitação, e SHALL exigir permissão de gestão de solicitações.

#### Scenario: Situações que aceitam item novo
- **WHEN** a solicitação estiver em análise, pronta para automatização, com guia gerada ou aprovada
- **THEN** o sistema SHALL aceitar o item novo

#### Scenario: Situações que recusam item novo
- **WHEN** a solicitação estiver negada ou em histórico
- **THEN** o sistema SHALL recusar o item novo

#### Scenario: Repetir especialidade e profissional é permitido
- **WHEN** o item novo repetir a especialidade e o profissional de um item existente
- **THEN** o sistema SHALL aceitá-lo

#### Scenario: Situação é reavaliada após acrescentar
- **WHEN** um item for acrescentado
- **THEN** o sistema SHALL reavaliar a situação da solicitação

#### Scenario: Sem permissão
- **WHEN** quem pedir não tiver permissão de gestão de solicitações
- **THEN** o sistema SHALL recusar a operação

### Requirement: Vínculo de renovação entre itens
O sistema SHALL registrar, no item criado por repetição, o vínculo com o item de origem da cadeia, e SHALL deixar o vínculo vazio quando for especialidade nova.

#### Scenario: Repetição registra a origem
- **WHEN** o item for criado repetindo um item existente
- **THEN** o sistema SHALL registrar o vínculo com o item de origem da cadeia

#### Scenario: Renovar a partir de uma renovação
- **WHEN** o item de partida já for ele mesmo uma renovação
- **THEN** o sistema SHALL registrar o vínculo com a origem da cadeia, e não com o item de partida

#### Scenario: Especialidade nova não tem vínculo
- **WHEN** o item for de especialidade nova
- **THEN** o vínculo SHALL ficar vazio

#### Scenario: Vínculo de outra solicitação
- **WHEN** o vínculo apontar para item de outra solicitação
- **THEN** o sistema SHALL recusar a operação

### Requirement: Quantidade padrão vem da regra do convênio
O sistema SHALL obter a quantidade padrão de sessões da regra vigente do convênio, e SHALL NOT trazer quantidade quando não houver regra vigente com esse valor.

#### Scenario: Regra vigente define o padrão
- **WHEN** o convênio tiver regra vigente com sessões por guia definidas
- **THEN** o sistema SHALL usar esse valor como quantidade padrão

#### Scenario: Sem regra vigente
- **WHEN** o convênio não tiver regra vigente, ou a regra não definir sessões por guia
- **THEN** a quantidade SHALL vir vazia
- **AND** o sistema SHALL NOT arbitrar um número

#### Scenario: Nenhum limite de convênio no código
- **WHEN** a quantidade padrão for determinada
- **THEN** o valor SHALL vir de dado configurável, e SHALL NOT estar fixo no código

### Requirement: Avisos ao acrescentar sessões
O sistema SHALL apresentar, antes da confirmação, os avisos aplicáveis ao item que está sendo acrescentado, e nenhum aviso SHALL impedir a confirmação.

#### Scenario: Especialidade e profissional repetidos
- **WHEN** já existir item com a mesma especialidade e o mesmo profissional
- **THEN** o sistema SHALL avisar sobre a repetição
- **AND** SHALL permitir concluir

#### Scenario: Idade do pedido médico
- **WHEN** a solicitação tiver data de pedido
- **THEN** o sistema SHALL informar há quanto tempo o pedido foi feito

#### Scenario: Limite do ciclo
- **WHEN** a regra vigente do convênio definir sessões por guia
- **THEN** o sistema SHALL informar esse limite

#### Scenario: Soma já pedida na cadeia
- **WHEN** houver itens na mesma cadeia de renovação
- **THEN** o sistema SHALL informar quantas sessões já foram pedidas nela

#### Scenario: Aviso sem fonte não aparece
- **WHEN** não houver dado que sustente um aviso
- **THEN** o sistema SHALL omiti-lo

#### Scenario: Regra de convênio é decidida no servidor
- **WHEN** os avisos forem apresentados
- **THEN** os valores SHALL ser calculados no servidor

### Requirement: Item de renovação identificado como continuação
O sistema SHALL identificar visualmente os itens de renovação pela posição na cadeia, na listagem e no detalhe da solicitação.

#### Scenario: Segunda remessa
- **WHEN** um item for a segunda da sua cadeia
- **THEN** o sistema SHALL apresentá-lo como continuação, com a posição

#### Scenario: Item sem renovação
- **WHEN** o item não pertencer a nenhuma cadeia
- **THEN** o sistema SHALL apresentá-lo sem indicação de continuação

#### Scenario: Itens repetidos são distinguíveis
- **WHEN** dois itens tiverem a mesma especialidade e o mesmo profissional
- **THEN** o sistema SHALL permitir distinguir um do outro pela posição na cadeia
