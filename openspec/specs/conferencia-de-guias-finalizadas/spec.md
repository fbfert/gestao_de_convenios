# conferencia-de-guias-finalizadas Specification

## Purpose
Descobrir, perguntando ao portal da Unimed, quais guias a clínica já finalizou lá antes de a automação existir — e registrar isso no Gescon sem inventar o histórico de sessões que nunca foi digitado aqui.

## Requirements

### Requirement: Conferência de uma guia no portal

O sistema SHALL consultar a tela de exames finalizados do portal da Unimed para descobrir se uma guia já foi finalizada na operadora.

A consulta SHALL limpar o filtro de data inicial antes de buscar: o portal o traz preenchido com uma data recente, e guias antigas — que são justamente as que interessam — ficariam de fora.

A consulta SHALL buscar pelo número da guia registrado no Gescon e SHALL decidir pela presença da guia no resultado: encontrada significa finalizada na operadora.

O sistema SHALL NOT alterar nada no portal durante a conferência. Ela é uma pergunta.

#### Scenario: Guia aparece entre os exames finalizados
- **WHEN** a busca pelo número devolver a guia
- **THEN** o sistema SHALL registrar que a operadora confirmou a finalização, com a data da conferência

#### Scenario: Guia não aparece
- **WHEN** a busca não devolver a guia
- **THEN** o sistema SHALL registrar que a guia foi conferida e não está finalizada, com a data da conferência

#### Scenario: Data inicial do filtro
- **WHEN** a tela de exames finalizados trouxer a data inicial preenchida
- **THEN** o sistema SHALL limpá-la antes de filtrar

#### Scenario: A tela não abre
- **WHEN** a tela de exames finalizados não puder ser alcançada
- **THEN** o sistema SHALL encerrar a conferência como falha, SHALL NOT registrar desfecho algum na guia e SHALL informar onde parou

#### Scenario: Guia sem número da operadora
- **WHEN** a guia não tiver número da operadora
- **THEN** o sistema SHALL NOT tentar a conferência, porque não há o que buscar

### Requirement: Disparo avulso e em lote

O sistema SHALL permitir conferir uma guia específica, a pedido do operador.

O sistema SHALL permitir conferir em lote as guias de convênio com automação Unimed que ainda não foram conferidas, numa única ida ao portal.

O sistema SHALL permitir também um lote que **inclua as guias já conferidas**. Sem isso, um lote que corresse errado — filtrando pelo período errado, ou numa tela diferente da esperada — marcaria dezenas de guias como conferidas de uma vez e as tiraria do lote seguinte, e desfazer isso exigiria uma conferência avulsa por guia.

O lote SHALL percorrer as guias uma a uma e SHALL prosseguir para a seguinte quando uma delas falhar, porque uma guia que não pôde ser conferida não é motivo para deixar as outras sem resposta.

O sistema SHALL NOT conferir guias por conta própria, sem alguém pedir.

#### Scenario: Conferir uma guia
- **WHEN** o operador acionar a conferência de uma guia
- **THEN** o sistema SHALL enfileirar a conferência e SHALL apresentar o resultado quando terminar

#### Scenario: Conferir em lote
- **WHEN** o operador acionar a conferência em lote
- **THEN** o sistema SHALL conferir as guias ainda não conferidas numa única sessão de portal e SHALL registrar o desfecho de cada uma

#### Scenario: Conferir em lote incluindo as já conferidas
- **WHEN** o operador acionar a conferência em lote pedindo incluir as já conferidas
- **THEN** o sistema SHALL conferir também as que já têm data de conferência, e SHALL atualizar o desfecho de cada uma

#### Scenario: Uma guia falha no meio do lote
- **WHEN** a conferência de uma guia falhar durante o lote
- **THEN** o sistema SHALL registrar a falha daquela guia e SHALL continuar pelas demais

#### Scenario: Nada a conferir
- **WHEN** não houver guia elegível
- **THEN** o sistema SHALL informar isso e SHALL NOT abrir o portal

#### Scenario: Guia de convênio sem automação
- **WHEN** a guia for de convênio sem automação Unimed
- **THEN** o sistema SHALL NOT oferecer a conferência

### Requirement: Um lote cobre um convênio

O lote SHALL cobrir as guias de um único convênio por vez, porque a credencial do portal é por convênio e um lote misturando convênios não teria com qual login entrar.

O sistema SHALL informar qual convênio o lote cobriu, e SHALL avisar quando restarem guias elegíveis de outro convênio — senão metade do passivo ficaria para trás sem que ninguém percebesse.

#### Scenario: Um convênio automatizado
- **WHEN** houver um único convênio com automação Unimed
- **THEN** o sistema SHALL conferir as guias dele e SHALL NOT avisar sobre convênios restantes

#### Scenario: Mais de um convênio automatizado
- **WHEN** houver guias elegíveis em mais de um convênio
- **THEN** o sistema SHALL conferir as de um convênio, SHALL nomeá-lo, e SHALL informar quantas guias de outros convênios continuam esperando

### Requirement: O que a marca significa

O sistema SHALL registrar a finalização na operadora como uma marca própria da guia, com a data, e SHALL manter o status da guia como está.

O sistema SHALL NOT tratar a marca como equivalente a finalizar a guia pelo fluxo normal: a guia não tem sessões registradas, e a diferença entre "finalizada com as sessões lançadas aqui" e "finalizada na operadora, sem registro neste sistema" é justamente o que a marca existe para preservar.

A marca SHALL NOT alterar cota, antecipação, conciliação ou qualquer contagem que hoje dependa do status da guia.

#### Scenario: Marca não muda o status
- **WHEN** a conferência confirmar a finalização na operadora
- **THEN** o sistema SHALL registrar a marca e SHALL deixar o status da guia inalterado

#### Scenario: Marca não inventa sessões
- **WHEN** a guia marcada não tiver sessão registrada
- **THEN** o sistema SHALL NOT criar sessão alguma e SHALL NOT alterar a cota da guia

#### Scenario: Conferir de novo uma guia já marcada
- **WHEN** o operador conferir uma guia que já está marcada
- **THEN** o sistema SHALL atualizar a data da conferência e SHALL manter a marca

#### Scenario: Guia deixa de aparecer no portal
- **WHEN** uma guia antes marcada não aparecer mais entre os exames finalizados
- **THEN** o sistema SHALL retirar a marca e SHALL registrar a data da conferência, porque a marca afirma o que o portal diz agora

### Requirement: A marca nas telas

O sistema SHALL exibir a marca de finalizada na operadora junto do status da guia, de forma distinguível do próprio status, onde a guia aparecer.

O sistema SHALL permitir filtrar as guias por essa marca.

O sistema SHALL apresentar a data da conferência junto da marca, para que quem a vê saiba quando ela foi verdade.

#### Scenario: Guia marcada na listagem
- **WHEN** uma guia marcada aparecer numa listagem
- **THEN** o sistema SHALL exibir a marca ao lado do status

#### Scenario: Filtrar pelas marcadas
- **WHEN** o usuário filtrar pelas guias finalizadas na operadora
- **THEN** o sistema SHALL devolver apenas as marcadas

#### Scenario: Guia sem marca
- **WHEN** a guia não estiver marcada
- **THEN** o sistema SHALL NOT exibir a marca

### Requirement: Item recolhido em Solicitações

O sistema SHALL apresentar de forma recolhida, na listagem de solicitações, o item cuja guia está finalizada na operadora: o essencial para reconhecê-lo — especialidade, número da guia e a marca — sem as ações que não cabem mais.

O sistema SHALL permitir expandir o item recolhido.

O sistema SHALL apresentar recolhida a solicitação cujos itens estejam todos nessa situação.

#### Scenario: Item com guia finalizada na operadora
- **WHEN** a solicitação tiver item cuja guia está marcada
- **THEN** o sistema SHALL apresentar esse item recolhido, com especialidade, número da guia e a marca

#### Scenario: Expandir o item
- **WHEN** o usuário expandir um item recolhido
- **THEN** o sistema SHALL mostrar o item por inteiro

#### Scenario: Solicitação inteira finalizada
- **WHEN** todos os itens da solicitação tiverem guia marcada
- **THEN** o sistema SHALL apresentá-la recolhida

#### Scenario: Solicitação com itens em situações diferentes
- **WHEN** parte dos itens estiver marcada e parte não
- **THEN** o sistema SHALL recolher apenas os marcados e SHALL manter os demais como estão

### Requirement: Acompanhamento da conferência

O sistema SHALL apresentar o andamento da conferência enquanto ela corre, e o resultado quando terminar.

O sistema SHALL registrar cada conferência de forma consultável depois pela guia, com o desfecho e, havendo falha, o motivo em linguagem que diga ao operador o que fazer.

#### Scenario: Andamento visível
- **WHEN** a conferência estiver em execução
- **THEN** o sistema SHALL indicar isso e SHALL atualizar sozinho quando o resultado chegar

#### Scenario: Resultado do lote
- **WHEN** o lote terminar
- **THEN** o sistema SHALL informar quantas guias foram confirmadas como finalizadas, quantas não estavam e quantas falharam

#### Scenario: Consultar depois
- **WHEN** alguém abrir uma guia que já foi conferida
- **THEN** o sistema SHALL mostrar quando foi e qual foi o desfecho
