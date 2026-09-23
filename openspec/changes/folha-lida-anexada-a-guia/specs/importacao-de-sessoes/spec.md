## ADDED Requirements

### Requirement: Folha lida anexada à guia na confirmação

Quando as sessões vierem da leitura de um arquivo — enviado ou capturado pela webcam — o sistema SHALL anexar esse mesmo arquivo à guia ao confirmar as sessões, como folha de registro, sem exigir que o usuário o envie de novo.

O sistema SHALL guardar a folha em PDF: arquivo de imagem SHALL ser convertido em PDF antes de ser guardado, tanto na confirmação quanto ao anexar folha depois.

Na regional que exige a folha para lançar, a folha lida SHALL satisfazer a exigência.

O sistema SHALL NOT anexar nada quando as sessões vierem de texto colado, e SHALL NOT guardar a folha se a confirmação falhar.

#### Scenario: Confirmar sessões lidas de um PDF
- **WHEN** o usuário ler um PDF e confirmar as sessões
- **THEN** o sistema SHALL anexar o PDF à guia escolhida, na pasta do paciente, com data de envio e quem enviou

#### Scenario: Confirmar sessões lidas pela webcam
- **WHEN** o usuário capturar a folha pela webcam e confirmar as sessões
- **THEN** o sistema SHALL converter a captura em PDF e anexá-la à guia

#### Scenario: Regional que exige folha
- **WHEN** a carteirinha for da regional que exige a folha e as sessões tiverem vindo de um arquivo lido
- **THEN** o sistema SHALL aceitar a confirmação sem pedir outro arquivo

#### Scenario: Sessões de texto colado
- **WHEN** as sessões vierem de texto colado
- **THEN** o sistema SHALL NOT anexar folha alguma por conta própria

#### Scenario: Confirmação recusada
- **WHEN** a confirmação for recusada, por exemplo por conflito de agenda
- **THEN** o sistema SHALL NOT guardar a folha

### Requirement: Folha de registro na pasta do paciente

O sistema SHALL apresentar, na pasta do paciente, a guia a que cada folha de registro pertence.

O sistema SHALL aplicar à remoção pela pasta do paciente a mesma regra da remoção pela guia: a folha de uma guia finalizada na operadora SHALL NOT ser removida, por nenhum caminho.

#### Scenario: Folha identificada pela guia
- **WHEN** alguém abrir a pasta de um paciente com folhas de registro
- **THEN** o sistema SHALL exibir, em cada folha, o número da guia a que ela pertence

#### Scenario: Remover pela pasta folha de guia finalizada
- **WHEN** alguém tentar remover pela pasta do paciente a folha de uma guia já finalizada na operadora
- **THEN** o sistema SHALL recusar a remoção e SHALL manter o arquivo

#### Scenario: Remover pela pasta folha de guia em aberto
- **WHEN** alguém remover pela pasta do paciente a folha de uma guia ainda não finalizada
- **THEN** o sistema SHALL removê-la

### Requirement: Anexar folhas à mão ao registrar as sessões

O sistema SHALL oferecer, na tela de registro de sessões, um campo sempre visível para anexar à mão uma ou mais folhas de registro (PDF, JPG ou PNG), qualquer que seja a regional e qualquer que seja a origem das sessões — arquivo lido, webcam ou texto colado.

As folhas anexadas à mão SHALL ser guardadas na guia junto com a folha lida, na mesma confirmação, e SHALL seguir as mesmas regras: imagem convertida em PDF, nada guardado se a confirmação for recusada.

O sistema SHALL permitir retirar da lista uma folha anexada à mão antes de registrar, e SHALL limitar o total de folhas de uma confirmação a dez.

#### Scenario: Folha lida e folha anexada à mão
- **WHEN** o usuário ler uma folha, anexar à mão uma segunda via e registrar as sessões
- **THEN** o sistema SHALL guardar as duas na guia

#### Scenario: Sessões de texto colado com folha anexada à mão
- **WHEN** as sessões vierem de texto colado e o usuário anexar a folha à mão
- **THEN** o sistema SHALL guardá-la na guia ao registrar

#### Scenario: Retirar antes de registrar
- **WHEN** o usuário retirar da lista uma folha anexada à mão
- **THEN** o sistema SHALL NOT enviá-la ao registrar

#### Scenario: Limite de folhas
- **WHEN** a soma da folha lida com as anexadas à mão passar de dez
- **THEN** o sistema SHALL recusar as excedentes e informar o limite
