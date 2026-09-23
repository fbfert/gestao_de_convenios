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
