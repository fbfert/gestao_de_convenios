## ADDED Requirements

### Requirement: Tabela em modo cartão
O sistema SHALL converter cada linha de tabela em um cartão de pares "rótulo → valor" quando a
largura disponível não comportar a tabela, e SHALL definir o ponto de corte por tabela conforme o
número de colunas.

#### Scenario: Listagem larga no celular
- **WHEN** uma listagem de seis ou mais colunas for aberta abaixo de 64rem
- **THEN** cada linha SHALL virar um cartão, com o nome da coluna ao lado de cada valor

#### Scenario: Listagem estreita no tablet
- **WHEN** uma listagem de até cinco colunas for aberta entre 48rem e 64rem
- **THEN** ela SHALL permanecer tabela

#### Scenario: Célula sem rótulo
- **WHEN** uma célula for de ações, seleção ou estado vazio
- **THEN** ela SHALL ocupar a linha inteira do cartão, sem rótulo

### Requirement: Página não rola na horizontal
O sistema SHALL manter a página sem rolagem horizontal em qualquer largura, e SHALL confinar a
rolagem lateral ao contêiner do conteúdo largo quando ela for necessária.

#### Scenario: Qualquer tela em 390px
- **WHEN** qualquer rota autenticada for aberta em 390 pixels de largura
- **THEN** a largura de rolagem do documento SHALL ser igual à largura visível

### Requirement: Navegação em tela estreita
O sistema SHALL apresentar a navegação em painel acionado por botão quando a barra horizontal não
couber, e SHALL fechar o painel ao navegar.

#### Scenario: Abrir o menu no celular
- **WHEN** o usuário tocar o botão de menu
- **THEN** o sistema SHALL exibir todos os grupos e itens permitidos, sem depender de *hover*

#### Scenario: Escolher um item
- **WHEN** o usuário tocar um item do painel
- **THEN** o sistema SHALL navegar e SHALL fechar o painel

### Requirement: CSS de modelo de impressão não vaza
O sistema SHALL isolar o HTML de modelo de impressão de modo que o CSS dele não afete a página, e
SHALL preservar a aparência do documento impresso.

#### Scenario: Modelo com seletores genéricos
- **WHEN** um modelo declarar regras para `.grid`, `table` ou `body`
- **THEN** essas regras SHALL valer apenas dentro do documento de impressão
