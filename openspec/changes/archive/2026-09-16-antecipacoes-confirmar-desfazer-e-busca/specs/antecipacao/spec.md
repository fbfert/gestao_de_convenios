## MODIFIED Requirements

### Requirement: Dispensar uma solicitação elegível

O sistema SHALL permitir dispensar uma solicitação elegível sem gerar nada, registrando-a no histórico como ignorada.

O sistema SHALL exigir uma confirmação explícita do operador antes de dispensar, e essa confirmação SHALL identificar o paciente, o convênio e a data prevista da entrada, e SHALL aceitar um motivo opcional gravado junto ao registro.

#### Scenario: Dispensar sem gerar
- **WHEN** o operador dispensar uma entrada da fila de elegíveis
- **THEN** o sistema SHALL registrar a antecipação como ignorada e SHALL NOT criar nenhum item ou guia

#### Scenario: Confirmação antes de dispensar
- **WHEN** o operador acionar dispensar em uma entrada da fila
- **THEN** o sistema SHALL pedir confirmação identificando o paciente, o convênio e a data prevista, e SHALL NOT registrar nada enquanto a confirmação não for dada

#### Scenario: Desistir da confirmação
- **WHEN** o operador cancelar a confirmação de dispensa
- **THEN** o sistema SHALL manter a entrada na fila de elegíveis e SHALL NOT criar registro algum

#### Scenario: Motivo registrado junto
- **WHEN** o operador informar um motivo ao confirmar a dispensa
- **THEN** o sistema SHALL gravar esse motivo no registro da antecipação ignorada

#### Scenario: Data prevista preservada no registro
- **WHEN** uma entrada da fila for dispensada
- **THEN** o sistema SHALL gravar no registro a data prevista que a entrada exibia

### Requirement: Permissões separadas para ver e operar

O sistema SHALL exigir permissão de visualização para consultar a fila de elegíveis e o histórico, e permissão de gestão para gerar, dispensar, desfazer uma dispensa ou alterar antecipações.

#### Scenario: Sem permissão de visualização
- **WHEN** um usuário sem a permissão de visualizar antecipações consultar a fila ou o histórico
- **THEN** o sistema SHALL negar o acesso

#### Scenario: Somente visualização
- **WHEN** um usuário tiver apenas a permissão de visualizar
- **THEN** o sistema SHALL exibir a fila e o histórico, e SHALL negar gerar, dispensar, desfazer e alterar

### Requirement: Histórico de antecipações

O sistema SHALL manter o histórico das antecipações geradas e ignoradas, permitindo filtrar por status, e SHALL NOT permitir excluir um registro que tenha gerado itens ou guias.

O sistema SHALL permitir pesquisar o histórico por nome do paciente, por número de guia, por convênio e por período da data em que a antecipação foi gerada ou ignorada, combinando os critérios informados.

O sistema SHALL apresentar o histórico paginado, e a página e os critérios de pesquisa escolhidos SHALL sobreviver a sair da listagem e voltar a ela.

O sistema SHALL exibir, para cada registro do histórico, o detalhe da ação: o status, quando e por quem foi feita, a solicitação de origem, a data prevista, os itens e guias gerados quando houver, e o motivo registrado.

#### Scenario: Filtrar por status
- **WHEN** o operador filtrar o histórico por gerada ou ignorada
- **THEN** o sistema SHALL listar somente os registros daquele status

#### Scenario: Pesquisar por paciente
- **WHEN** o operador pesquisar por parte do nome de um paciente
- **THEN** o sistema SHALL listar somente as antecipações cuja solicitação de origem seja daquele paciente

#### Scenario: Pesquisar por número de guia
- **WHEN** o operador pesquisar por parte de um número de guia
- **THEN** o sistema SHALL listar as antecipações cuja solicitação de origem tenha uma guia com esse número, incluindo tanto as guias geradas pela antecipação quanto as que já existiam

#### Scenario: Pesquisar por convênio
- **WHEN** o operador escolher um convênio na pesquisa
- **THEN** o sistema SHALL listar somente as antecipações cuja solicitação de origem seja daquele convênio

#### Scenario: Pesquisar por período
- **WHEN** o operador informar uma data inicial, uma data final, ou as duas
- **THEN** o sistema SHALL listar somente as antecipações geradas ou ignoradas dentro desse intervalo, incluindo os dias das pontas

#### Scenario: Critérios combinados
- **WHEN** o operador informar mais de um critério de pesquisa ao mesmo tempo
- **THEN** o sistema SHALL listar somente os registros que satisfaçam todos eles

#### Scenario: Pesquisa volta para a primeira página
- **WHEN** o operador alterar qualquer critério de pesquisa
- **THEN** o sistema SHALL apresentar o resultado a partir da primeira página

#### Scenario: Página e pesquisa sobrevivem a abrir um item
- **WHEN** o operador abrir um item a partir do histórico e voltar para a listagem
- **THEN** o sistema SHALL reapresentar a mesma página com os mesmos critérios de pesquisa

#### Scenario: Registro do histórico não é excluído
- **WHEN** o operador tentar excluir um registro de antecipação
- **THEN** o sistema SHALL recusar, preservando o registro e os itens e guias que ele tenha criado

#### Scenario: Detalhe da ação disponível na linha
- **WHEN** o operador consultar o detalhe de um registro do histórico
- **THEN** o sistema SHALL apresentar o status, quem agiu e quando, a solicitação de origem, a data prevista, os itens e guias gerados quando houver, e o motivo registrado quando houver

## ADDED Requirements

### Requirement: Desfazer uma dispensa

O sistema SHALL permitir desfazer uma antecipação de status ignorada, removendo o registro e devolvendo a solicitação de origem à fila de elegíveis quando ela ainda satisfizer as condições de elegibilidade.

O sistema SHALL exigir confirmação explícita do operador antes de desfazer, e SHALL exigir a permissão de gestão de antecipações.

O sistema SHALL recusar desfazer uma antecipação de status gerada, porque a reversão implicaria apagar itens e guias já criados.

#### Scenario: Desfazer devolve a solicitação à fila
- **WHEN** o operador confirmar o desfazer de uma antecipação ignorada, e a solicitação de origem ainda tiver guia elegível
- **THEN** o sistema SHALL remover o registro do histórico e SHALL voltar a listar essa solicitação entre os elegíveis

#### Scenario: Confirmação antes de desfazer
- **WHEN** o operador acionar desfazer
- **THEN** o sistema SHALL pedir confirmação, e SHALL NOT remover o registro enquanto a confirmação não for dada

#### Scenario: Desistir de desfazer
- **WHEN** o operador cancelar a confirmação de desfazer
- **THEN** o sistema SHALL manter o registro no histórico inalterado

#### Scenario: Desfazer não se aplica a gerada
- **WHEN** o operador tentar desfazer uma antecipação de status gerada
- **THEN** o sistema SHALL recusar a operação e SHALL preservar o registro, os itens e as guias criados

#### Scenario: Solicitação que deixou de ser elegível não volta
- **WHEN** uma antecipação ignorada for desfeita e a solicitação de origem não satisfizer mais as condições de elegibilidade
- **THEN** o sistema SHALL remover o registro do histórico e SHALL NOT listar a solicitação entre os elegíveis

#### Scenario: Sem permissão de gestão
- **WHEN** um usuário sem a permissão de gerir antecipações tentar desfazer
- **THEN** o sistema SHALL negar a operação e SHALL preservar o registro

### Requirement: Origem de uma entrada elegível

O sistema SHALL permitir consultar, a partir de uma entrada da fila de elegíveis, os dados da solicitação que a originou, antes de decidir gerar ou dispensar.

A consulta SHALL apresentar o paciente, o convênio, a data do pedido, o médico solicitante, os CIDs, e cada item da solicitação com a sua especialidade, o profissional, a guia e o status da guia.

#### Scenario: Abrir a origem de uma entrada
- **WHEN** o operador acionar o paciente e convênio de uma entrada da fila de elegíveis
- **THEN** o sistema SHALL apresentar os dados da solicitação de origem sem gerar nem dispensar nada

#### Scenario: Consultar não age
- **WHEN** o operador fechar a consulta de origem
- **THEN** o sistema SHALL manter a entrada na fila, sem nenhum registro criado
