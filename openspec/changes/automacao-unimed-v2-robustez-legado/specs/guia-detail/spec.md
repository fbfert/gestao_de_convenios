## ADDED Requirements

### Requirement: Job legado não processa guias Unimed RDA
O sistema SHALL impedir que rotinas legadas de verificação diária processem guias de convênios configurados com `connector_driver = unimed_rda`, preservando o fluxo novo de automações Unimed.

#### Scenario: Guia Unimed ignorada pelo job legado
- **WHEN** `VerificarGuiasDiarioJob` encontrar guia cujo convênio usa `connector_driver = unimed_rda`
- **THEN** o job SHALL ignorar essa guia e SHALL NOT chamar o conector legado para ela

#### Scenario: Convênio não-Unimed preservado
- **WHEN** `VerificarGuiasDiarioJob` encontrar guia de convênio que não usa `unimed_rda`
- **THEN** o job SHALL manter o comportamento legado existente para esse convênio
