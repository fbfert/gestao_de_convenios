## ADDED Requirements

### Requirement: Alternância de tema
O sistema SHALL oferecer um controle na barra superior para alternar entre os temas disponíveis,
SHALL indicar qual está em vigor por texto além da forma, e SHALL preservar a escolha entre sessões
do mesmo navegador.

#### Scenario: Alternar o tema
- **WHEN** o usuário acionar o controle de tema
- **THEN** o sistema SHALL aplicar o outro tema imediatamente e SHALL atualizar o rótulo exibido

#### Scenario: Voltar ao sistema depois
- **WHEN** o usuário abrir o sistema de novo no mesmo navegador
- **THEN** o sistema SHALL aplicar o tema escolhido antes da primeira pintura, sem piscar o outro

#### Scenario: Tela estreita
- **WHEN** a barra superior estiver no formato de painel
- **THEN** o controle de tema SHALL continuar acessível dentro do painel

### Requirement: Distinguibilidade sob deficiência de visão de cor
No tema de alto contraste, o sistema SHALL usar cores de feedback que permaneçam distinguíveis entre
si quando simuladas para deuteranopia, protanopia e tritanopia.

#### Scenario: Estados opostos na mesma tela
- **WHEN** um registro aprovado e um negado forem exibidos lado a lado
- **THEN** os dois SHALL ser distinguíveis por cor além do rótulo, em qualquer das três simulações

#### Scenario: Cor nunca é canal único
- **WHEN** um estado for comunicado por cor
- **THEN** o sistema SHALL acompanhá-lo de rótulo em texto

### Requirement: Delimitação por borda
No tema de alto contraste, o sistema SHALL delimitar cada cartão, painel e controle com borda
espessa e de alto contraste, e SHALL usar a borda como portadora da cor de cada papel de feedback.

#### Scenario: Cartões adjacentes
- **WHEN** dois cartões estiverem lado a lado
- **THEN** a fronteira entre eles SHALL ser visível sem depender de diferença de preenchimento

#### Scenario: Chip de estado
- **WHEN** um chip de estado for exibido
- **THEN** ele SHALL ter borda na cor do papel, e o texto SHALL cumprir o piso de legibilidade
