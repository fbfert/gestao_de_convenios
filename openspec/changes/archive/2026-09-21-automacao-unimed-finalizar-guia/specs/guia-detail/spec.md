## MODIFIED Requirements

### Requirement: Ações de status em telas de guia

O sistema SHALL disponibilizar as ações de aprovar e negar uma guia em análise tanto na lista quanto no detalhe, reutilizando as mesmas mutations.

O sistema SHALL NOT oferecer a finalização nas telas de guia: finalizar depende de haver sessão registrada, e por isso a ação vive na tela de Sessões, junto do grupo da guia.

A finalização oferecida na tela de Sessões SHALL depender do convênio da guia: guia de convênio com automação Unimed SHALL ser finalizada na operadora, e guia de convênio sem automação SHALL continuar sendo finalizada manualmente, com senha, validade e data.

O sistema SHALL refletir no detalhe a situação resultante de qualquer dessas ações sem exigir recarregar o navegador.

#### Scenario: Aprovar pelo detalhe
- **WHEN** o usuário aprovar uma guia em análise na página de detalhe
- **THEN** o sistema SHALL atualizar o status exibido no detalhe sem recarregar manualmente o navegador

#### Scenario: Negar pelo detalhe
- **WHEN** o usuário negar uma guia em análise na página de detalhe
- **THEN** o sistema SHALL atualizar o status exibido no detalhe sem recarregar manualmente o navegador

#### Scenario: Finalizar pelo detalhe
- **WHEN** o usuário abrir a lista ou o detalhe de uma guia
- **THEN** o sistema SHALL NOT oferecer ali a ação de finalizar, porque finalizar depende de sessão registrada e a ação vive na tela de Sessões

#### Scenario: Finalização de guia com automação
- **WHEN** a guia for de convênio com automação Unimed
- **THEN** o sistema SHALL oferecer, na tela de Sessões, a finalização na operadora, e SHALL NOT oferecer a finalização manual

#### Scenario: Finalização de guia sem automação
- **WHEN** a guia for de convênio sem automação Unimed
- **THEN** o sistema SHALL oferecer, na tela de Sessões, a finalização manual com os dados exigidos

#### Scenario: Detalhe reflete a finalização feita na operadora
- **WHEN** a finalização na operadora concluir para uma guia aberta no detalhe
- **THEN** o sistema SHALL passar a exibir a guia como finalizada, com senha, validade e data de finalização
