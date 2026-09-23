## ADDED Requirements

### Requirement: Número de guia lido sem correspondência

Quando a busca de guia for aberta com o número lido da folha e não encontrar guia com sessão disponível, o sistema SHALL orientar o operador a conferir se o número foi lido corretamente no arquivo de origem e ajustá-lo.

#### Scenario: Leitura com número errado
- **WHEN** a leitura trouxer um número de guia que não encontra guia com sessão disponível
- **THEN** o sistema SHALL exibir, junto à mensagem de nenhuma guia encontrada, "Confira se o número foi lido corretamente no arquivo de origem e ajuste-o."

### Requirement: Destaque do executante lido

O sistema SHALL apresentar o nome do executante lido na folha com destaque visual suficiente para a conferência, em fonte não menor que a do texto corrente do formulário, e SHALL continuar sem preencher o campo a partir da leitura.

#### Scenario: Executante lido exibido
- **WHEN** a leitura reconhecer o nome do executante
- **THEN** o sistema SHALL exibir o aviso em fonte do corpo do formulário, com o nome em destaque, e SHALL NOT selecionar profissional algum
