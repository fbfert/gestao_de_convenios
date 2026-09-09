## ADDED Requirements

### Requirement: Card de Guias com três linhas
O sistema SHALL apresentar no dashboard um card de Guias com três linhas — negadas, em análise e senha vencendo — cada uma com o número do que ainda exige ação e um detalhe do que ocorreu hoje e na semana.

#### Scenario: Card exibe as três linhas
- **WHEN** o usuário com permissão de guias abrir o dashboard
- **THEN** o sistema SHALL exibir as linhas de negadas, em análise e senha vencendo

#### Scenario: Linha abre a listagem já filtrada
- **WHEN** o usuário acionar uma das linhas
- **THEN** o sistema SHALL abrir a listagem de guias já filtrada por aquele critério

#### Scenario: Sem permissão de guias o card não aparece
- **WHEN** o usuário não tiver permissão de ver guias
- **THEN** o sistema SHALL omitir o card

### Requirement: Números contam o que exige ação
O sistema SHALL contar em "negadas" apenas as guias cujo alerta de negação ainda não foi ocultado, e SHALL NOT incluir guias históricas em nenhuma das linhas.

#### Scenario: Guia com alerta ocultado sai da contagem
- **WHEN** o alerta de negação de uma guia negada tiver sido ocultado
- **THEN** o sistema SHALL NOT contá-la na linha de negadas

#### Scenario: Guia histórica fica de fora
- **WHEN** uma guia estiver marcada como histórica
- **THEN** o sistema SHALL NOT contá-la em nenhuma das linhas do card

### Requirement: Recorte temporal pela data da transição
O sistema SHALL contar "hoje" e "na semana" pela data em que o status mudou, e SHALL NOT usar a data de criação da guia.

#### Scenario: Guia antiga negada hoje conta em hoje
- **WHEN** uma guia criada na semana passada for negada hoje
- **THEN** o sistema SHALL contá-la no "hoje" da linha de negadas

#### Scenario: Guia criada hoje e ainda não negada não conta
- **WHEN** uma guia for criada hoje e permanecer em análise
- **THEN** o sistema SHALL NOT contá-la no "hoje" da linha de negadas

#### Scenario: Mesma guia negada duas vezes no dia conta uma
- **WHEN** a mesma guia registrar duas transições para negada no mesmo dia
- **THEN** o sistema SHALL contá-la uma única vez

### Requirement: Senha vencendo usa a configuração do tenant
O sistema SHALL usar o número de dias configurado em configurações globais para definir a janela de senha vencendo, e SHALL NOT usar um prazo embutido no código.

#### Scenario: Janela segue a configuração
- **WHEN** a configuração de dias de alerta de senha for alterada
- **THEN** o sistema SHALL passar a considerar a nova janela sem alteração de código

#### Scenario: Senha já vencida não entra na janela
- **WHEN** a validade da senha de uma guia já tiver passado
- **THEN** o sistema SHALL NOT contá-la em senha vencendo

### Requirement: Filtros correspondentes na listagem de guias
O sistema SHALL aceitar na listagem de guias os filtros de negadas pendentes e de senha vencendo usados pelas linhas do card.

#### Scenario: Filtro de negadas pendentes
- **WHEN** a listagem for consultada com status negado e o filtro de pendentes
- **THEN** o sistema SHALL devolver apenas as guias negadas com alerta ainda visível

#### Scenario: Filtro de senha vencendo
- **WHEN** a listagem for consultada com o filtro de senha vencendo
- **THEN** o sistema SHALL devolver apenas as guias dentro da janela configurada no tenant
