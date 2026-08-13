## ADDED Requirements

### Requirement: Dica de ajuda por campo
O sistema SHALL oferecer um componente de dica ao lado do rótulo de um campo, acionável por mouse e por teclado, sem alterar o valor nem o envio do formulário.

#### Scenario: Consultar a dica com o mouse
- **WHEN** o usuário posicionar o ponteiro sobre o botão de dica de um campo
- **THEN** o sistema SHALL exibir o texto de ajuda daquele campo

#### Scenario: Consultar a dica pelo teclado
- **WHEN** o usuário focar o botão de dica navegando por teclado
- **THEN** o sistema SHALL exibir o mesmo texto de ajuda, sem depender do ponteiro

#### Scenario: Dica não interfere no formulário
- **WHEN** o usuário acionar o botão de dica dentro de um formulário
- **THEN** o sistema SHALL manter os valores preenchidos e SHALL NOT submeter o formulário

### Requirement: Explicação das opções de conector do convênio
O sistema SHALL explicar, na tela de cadastro e de edição de convênio, o que significam as opções `Manual`, `API` e `Scraping`, incluindo que apenas `Manual` tem conector implementado e que a automação da Unimed é ligada em Configurações → Unimed.

#### Scenario: Escolher o conector do convênio
- **WHEN** o usuário consultar a dica do campo de conector
- **THEN** o sistema SHALL descrever cada opção e SHALL avisar que `API` e `Scraping` não habilitam automação por si só
