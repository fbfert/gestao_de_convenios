## ADDED Requirements

### Requirement: Várias folhas de registro por guia

O sistema SHALL aceitar mais de uma folha de registro de sessões para a mesma guia, porque uma guia de dez sessões costuma ser impressa em duas vias e preenchida em partes.

O sistema SHALL aceitar as folhas no mesmo envio da importação, várias de uma vez, e SHALL aceitar também anexar folhas depois, a uma guia que já teve sessões confirmadas.

O sistema SHALL guardar cada folha vinculada à guia que originou a remessa, na pasta do paciente, junto com a data do envio e quem a enviou.

O sistema SHALL apresentar na guia quais folhas já foram anexadas, e SHALL permitir removê-las enquanto a guia não tiver sido finalizada na operadora.

#### Scenario: Duas folhas no mesmo envio
- **WHEN** o usuário enviar duas folhas na mesma confirmação
- **THEN** o sistema SHALL guardar as duas, ambas vinculadas à mesma guia

#### Scenario: Anexar folha depois
- **WHEN** o usuário anexar uma folha a uma guia que já teve sessões confirmadas
- **THEN** o sistema SHALL guardá-la vinculada àquela guia, sem exigir nova confirmação de sessões

#### Scenario: Ver as folhas da guia
- **WHEN** alguém abrir uma guia com folhas anexadas
- **THEN** o sistema SHALL listar as folhas, com data de envio e quem enviou

#### Scenario: Remover folha antes de finalizar
- **WHEN** o usuário remover uma folha de guia ainda não finalizada na operadora
- **THEN** o sistema SHALL removê-la

#### Scenario: Guia já finalizada na operadora
- **WHEN** a guia já tiver sido finalizada na operadora
- **THEN** o sistema SHALL NOT permitir remover folha alguma, porque ela é o comprovante do que foi enviado

### Requirement: Exigência da folha na regional que a pede

O sistema SHALL continuar exigindo ao menos uma folha de registro na confirmação das sessões quando a carteirinha do paciente for da regional que a exige.

Uma folha SHALL bastar para a confirmação, ainda que as sessões tenham sido preenchidas em mais de uma via impressa: as demais podem ser anexadas depois, antes da finalização.

#### Scenario: Regional exige e nenhuma folha veio
- **WHEN** a carteirinha for da regional que exige a folha e a confirmação não trouxer nenhuma
- **THEN** o sistema SHALL recusar a confirmação, informando a exigência

#### Scenario: Uma folha basta para confirmar
- **WHEN** a confirmação trouxer ao menos uma folha
- **THEN** o sistema SHALL aceitá-la, sem exigir todas as vias de uma vez

### Requirement: Conferência de agenda antes de gravar as sessões

O sistema SHALL conferir as sessões da grade contra as regras de agenda antes de gravá-las, e SHALL recusar a confirmação enquanto houver conflito, apontando quais linhas conflitam e contra o quê.

O sistema SHALL permitir corrigir data e hora na própria grade de conferência e SHALL reconferir a cada correção.

A conferência de agenda SHALL ser independente da conferência do paciente entre a folha e a guia: uma divergência de paciente pode ser confirmada com justificativa, um conflito de agenda não.

#### Scenario: Grade com conflito
- **WHEN** duas linhas da grade violarem o intervalo mínimo entre sessões
- **THEN** o sistema SHALL recusar a confirmação e SHALL marcar as linhas envolvidas

#### Scenario: Conflito com sessão já gravada
- **WHEN** uma linha da grade conflitar com sessão já registrada em outra guia do mesmo paciente
- **THEN** o sistema SHALL recusar a confirmação, nomeando a guia e o horário existentes

#### Scenario: Corrigir na grade
- **WHEN** o usuário corrigir a data ou a hora da linha em conflito
- **THEN** o sistema SHALL reconferir e SHALL liberar a confirmação quando nenhum conflito restar

#### Scenario: Conflito de agenda não aceita justificativa
- **WHEN** o usuário tentar confirmar com conflito de agenda em aberto
- **THEN** o sistema SHALL recusar, e SHALL NOT oferecer justificativa como forma de prosseguir, ainda que a divergência de paciente aceite

#### Scenario: Grade sem conflito
- **WHEN** nenhuma linha violar as regras de agenda
- **THEN** o sistema SHALL seguir o fluxo de confirmação como hoje
