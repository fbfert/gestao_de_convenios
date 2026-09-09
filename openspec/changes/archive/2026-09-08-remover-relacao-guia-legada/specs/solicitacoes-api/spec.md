## REMOVED Requirements

### Requirement: Guia direta da solicitação no payload
**Reason**: a relação de origem é um `hasOne` sem ordenação, e devolvia uma guia arbitrária entre as
guias dos itens. Desde a multi-especialidade cada item tem a sua, e o payload por item já cobre o
caso.
**Migration**: quem lia `guia` na resposta de solicitações passa a ler `itens[].guia`.

O sistema expunha, na resposta de solicitações, uma chave `guia` com os dados de uma guia da
solicitação.

## ADDED Requirements

### Requirement: Existência de guia decidida pelo conjunto de guias da solicitação
O sistema SHALL decidir se uma solicitação já tem guia considerando todas as guias vinculadas a ela,
e SHALL NOT depender de uma guia eleita como principal.

#### Scenario: Guia vinculada a um item
- **WHEN** uma solicitação tiver ao menos um item com guia
- **THEN** o sistema SHALL considerar que a solicitação já tem guia

#### Scenario: Guia sem item vinculado
- **WHEN** uma solicitação tiver guia sem vínculo com item — registro antigo, anterior à
  multi-especialidade
- **THEN** o sistema SHALL considerar que a solicitação já tem guia

#### Scenario: Anexo do pedido travado pela guia
- **WHEN** a solicitação já tiver guia e o usuário tentar remover um anexo do pedido
- **THEN** o sistema SHALL recusar a remoção

#### Scenario: Solicitação sem guia nenhuma
- **WHEN** a solicitação não tiver guia
- **THEN** o sistema SHALL permitir a remoção do anexo

### Requirement: Listagem de solicitações sem carga de guia arbitrária
O sistema SHALL montar a listagem de solicitações sem carregar antecipações e conciliações de uma
guia da solicitação.

#### Scenario: Listagem não carrega o que não exibe
- **WHEN** a listagem de solicitações for consultada
- **THEN** o sistema SHALL NOT carregar antecipações e conciliações para montar a resposta

#### Scenario: A guia de cada item continua disponível
- **WHEN** a listagem de solicitações for consultada
- **THEN** cada item SHALL continuar trazendo os dados da sua guia
