## ADDED Requirements

### Requirement: Dropdown escuro compartilhado
O sistema SHALL usar um componente de seleção baseado em Headless UI para todos os campos de escolha do frontend, sem controles `<select>` nativos remanescentes.

#### Scenario: Abrir opções
- **WHEN** o usuário abrir um campo de seleção
- **THEN** o sistema SHALL exibir uma lista HTML com fundo escuro e texto claro, consistente com o tema da aplicação

#### Scenario: Navegação por teclado
- **WHEN** o usuário navegar um campo de seleção por teclado
- **THEN** o sistema SHALL fornecer o comportamento acessível padrão do Headless UI para setas, Enter e Esc
