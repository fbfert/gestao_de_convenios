## ADDED Requirements

### Requirement: Permissões efetivas no payload de autenticação
O sistema SHALL devolver, no login e na rota de usuário autenticado, as permissões efetivas do usuário no tenant dele, além do papel.

#### Scenario: Entrar no sistema
- **WHEN** um usuário fizer login com credenciais válidas
- **THEN** o sistema SHALL incluir na resposta a lista de permissões concedidas pelo papel do usuário

#### Scenario: Recarregar o aplicativo
- **WHEN** o aplicativo web for aberto com uma sessão já autenticada
- **THEN** o sistema SHALL reconsultar papel e permissões, de modo que uma alteração feita por um administrador passe a valer sem exigir novo login

#### Scenario: Permissões de outro tenant
- **WHEN** o sistema montar as permissões de um usuário
- **THEN** o sistema SHALL considerar somente os papéis do tenant desse usuário

### Requirement: Menu coerente com as permissões
O sistema SHALL exibir no menu apenas os itens que o usuário tem permissão de acessar, e SHALL ocultar um grupo quando nenhum de seus itens estiver visível.

#### Scenario: Usuário sem acesso a um módulo
- **WHEN** um usuário sem a permissão de um módulo abrir o menu
- **THEN** o sistema SHALL NOT exibir o item correspondente

#### Scenario: Grupo inteiro indisponível
- **WHEN** nenhum item de um grupo estiver disponível para o usuário
- **THEN** o sistema SHALL ocultar o grupo inteiro do menu

#### Scenario: Menu não substitui a verificação da API
- **WHEN** um usuário acessar diretamente a URL de uma tela que o menu esconde
- **THEN** o sistema SHALL continuar recusando a operação na API por falta de permissão

### Requirement: Perfis e auditoria acessíveis pelo menu
O sistema SHALL oferecer, no submenu de Configurações, acesso à tela de perfis e permissões e à tela de logs de auditoria.

#### Scenario: Abrir a gestão de perfis
- **WHEN** um usuário com permissão de gerenciar permissões abrir o menu Configurações
- **THEN** o sistema SHALL oferecer o item de perfis e permissões

#### Scenario: Abrir os logs de auditoria
- **WHEN** um usuário com permissão de ver auditoria abrir o menu Configurações
- **THEN** o sistema SHALL oferecer o item de logs de auditoria

### Requirement: Papéis próprios por clínica
O sistema SHALL permitir a usuários com `permissoes.manage` criar, renomear e excluir papéis do próprio tenant, e SHALL permitir criar um papel a partir das permissões de outro já existente.

#### Scenario: Criar papel
- **WHEN** um usuário autorizado enviar um nome de papel inédito no tenant
- **THEN** o sistema SHALL criar o papel no tenant do usuário e retornar 201

#### Scenario: Duplicar permissões de um papel existente
- **WHEN** a criação indicar um papel de origem
- **THEN** o sistema SHALL copiar para o papel novo as permissões do papel de origem

#### Scenario: Nome repetido
- **WHEN** o nome informado já existir no tenant
- **THEN** o sistema SHALL recusar a operação com erro de validação

#### Scenario: Papel de outro tenant
- **WHEN** um usuário tentar alterar ou excluir um papel de outro tenant
- **THEN** o sistema SHALL retornar 404

### Requirement: Papéis de sistema protegidos
O sistema SHALL tratar `admin`, `funcionario` e `profissional` como papéis de sistema, impedindo renomear e excluir, e SHALL continuar permitindo alterar as permissões deles.

#### Scenario: Renomear ou excluir papel de sistema
- **WHEN** um usuário tentar renomear ou excluir um papel de sistema
- **THEN** o sistema SHALL recusar a operação

#### Scenario: Ajustar permissões de papel de sistema
- **WHEN** um usuário autorizado alterar as permissões de um papel de sistema
- **THEN** o sistema SHALL aplicar a alteração

### Requirement: Papel em uso não é excluído
O sistema SHALL recusar a exclusão de um papel que ainda tenha usuários vinculados, informando quantos são.

#### Scenario: Excluir papel com usuários
- **WHEN** um usuário tentar excluir um papel vinculado a pelo menos um usuário
- **THEN** o sistema SHALL recusar a exclusão e SHALL informar a quantidade de usuários vinculados

### Requirement: Clínica nunca fica sem quem administre permissões
O sistema SHALL impedir qualquer operação que deixe o tenant sem nenhum papel com `permissoes.manage`, e SHALL impedir que um usuário remova essa permissão do próprio papel.

#### Scenario: Remover a última administração do tenant
- **WHEN** uma alteração deixaria o tenant sem nenhum papel com `permissoes.manage`
- **THEN** o sistema SHALL recusar a operação e explicar o motivo

#### Scenario: Remover a própria administração
- **WHEN** um usuário retirar `permissoes.manage` do papel que ele mesmo possui
- **THEN** o sistema SHALL recusar a operação
