## ADDED Requirements

### Requirement: Menu superior na ordem operacional acordada
O sistema SHALL exibir o menu superior na sequência operacional definida para a clínica, com os itens visíveis na ordem Dashboard, Pacientes, Solicitações, Guias, Sessões, Antecipações, Analíticos, Conciliação, Convênios, Profissionais, Médicos, Usuários e Configurações.

#### Scenario: Ordem visível do menu
- **WHEN** o usuário autenticado acessar qualquer tela autenticada
- **THEN** o menu superior SHALL apresentar os itens visíveis na ordem operacional acordada

### Requirement: Rótulos visíveis padronizados no menu
O sistema SHALL usar os rótulos visíveis acordados para cada item do menu superior, sem expor rótulos alternativos na navegação principal.

#### Scenario: Rótulo de solicitações
- **WHEN** o menu superior for renderizado
- **THEN** o item de solicitações SHALL ser exibido como "Solicitações"

#### Scenario: Rótulo de analíticos
- **WHEN** o menu superior for renderizado
- **THEN** o item dedicado ao novo fluxo SHALL ser exibido como "Analíticos"

### Requirement: Permissões fora do menu principal
O sistema SHALL remover o acesso global de Permissões do menu superior e SHALL expor esse acesso apenas dentro do CRUD de Usuários.

#### Scenario: Menu principal sem Permissões
- **WHEN** o usuário visualizar o menu superior
- **THEN** o item global de Permissões SHALL not appear

#### Scenario: Acesso contextual em Usuários
- **WHEN** o usuário acessar o CRUD de Usuários
- **THEN** o sistema SHALL oferecer um acionador contextual para a gestão de permissões
