## ADDED Requirements

### Requirement: Menu superior agrupado
O sistema SHALL organizar o menu superior em grupos com submenu — Cadastros, Operação e Configurações — além dos itens simples Gestão de Convênios, Automações e Manual.

#### Scenario: Abrir o submenu de um grupo
- **WHEN** o usuário acionar a seta ao lado do rótulo de um grupo
- **THEN** o sistema SHALL exibir a lista de subitens do grupo sobre o conteúdo da página

#### Scenario: Clicar no rótulo do grupo
- **WHEN** o usuário clicar no rótulo de um grupo
- **THEN** o sistema SHALL navegar para a tela de visão geral daquele grupo, e não apenas revelar a lista

#### Scenario: Percorrer o submenu com o ponteiro
- **WHEN** o usuário mover o ponteiro do rótulo do grupo até um subitem da lista
- **THEN** o sistema SHALL manter a lista aberta durante todo o percurso

#### Scenario: Fechar o submenu
- **WHEN** o usuário pressionar Escape, clicar fora do menu ou navegar para outra rota
- **THEN** o sistema SHALL fechar a lista aberta

#### Scenario: Submenu sobre o conteúdo
- **WHEN** um submenu estiver aberto sobre o cartão de conteúdo principal
- **THEN** o sistema SHALL desenhar a lista à frente do conteúdo, com fundo opaco

### Requirement: Tela inicial
O sistema SHALL usar a visão geral operacional, rotulada Gestão de Convênios, como tela inicial.

#### Scenario: Entrar na raiz
- **WHEN** o usuário acessar `/`
- **THEN** o sistema SHALL exibir a visão geral operacional

#### Scenario: Rota desconhecida
- **WHEN** o usuário acessar uma rota que não existe
- **THEN** o sistema SHALL redirecionar para a tela inicial

### Requirement: Telas de visão geral de grupo
O sistema SHALL oferecer, para os grupos Cadastros e Operação, uma tela com um cartão explicativo por subitem e um bloco de métricas.

#### Scenario: Explicação por subitem
- **WHEN** o usuário abrir a tela de visão geral de um grupo
- **THEN** o sistema SHALL exibir, para cada subitem do submenu, um cartão com o nome e a explicação do que aquela tela guarda ou faz, na mesma ordem do submenu

#### Scenario: Ordem de uso em Operação
- **WHEN** o usuário abrir a visão geral de Operação
- **THEN** o sistema SHALL numerar os cartões na sequência em que as telas são usadas, do pedido médico até a conciliação

#### Scenario: Métricas conforme o papel
- **WHEN** o usuário abrir a visão geral de um grupo
- **THEN** o sistema SHALL exibir apenas as métricas que o papel do usuário tem permissão de ver

#### Scenario: Papel sem nenhuma métrica
- **WHEN** o papel do usuário não puder ver nenhuma métrica do grupo
- **THEN** o sistema SHALL informar isso no lugar do bloco de métricas, sem erro

### Requirement: Configurações por rota
O sistema SHALL servir cada área de Configurações em sua própria rota, alcançada pelo submenu.

#### Scenario: Entrar em Configurações
- **WHEN** o usuário clicar em Configurações no menu
- **THEN** o sistema SHALL exibir a escolha de aparência no topo e, abaixo, os cartões que explicam cada área do submenu

### Requirement: Legibilidade das listas suspensas
O sistema SHALL garantir contraste legível nas listas de opções dos campos de seleção, nos temas claro e escuro.

#### Scenario: Abrir uma lista de seleção
- **WHEN** o usuário abrir a lista de um campo de seleção em qualquer tela
- **THEN** o sistema SHALL exibir as opções com texto contrastante sobre fundo opaco, no tema em vigor

#### Scenario: Superfícies que imitam papel
- **WHEN** o tema claro estiver ativo e a tela exibir o manual ou o preview de impressão
- **THEN** o sistema SHALL manter o fundo dessas superfícies branco
