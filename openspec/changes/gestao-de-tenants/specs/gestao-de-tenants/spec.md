## ADDED Requirements

### Requirement: Acesso restrito à administração de clínicas
O sistema SHALL restringir a gestão de clínicas a usuários marcados como administradores do sistema, por um mecanismo fora do catálogo de permissões.

#### Scenario: Usuário comum tenta acessar
- **WHEN** um usuário sem a marca de administrador do sistema chamar qualquer operação de gestão de clínicas
- **THEN** o sistema SHALL recusar com 403, em leitura e em escrita

#### Scenario: Administrador de um tenant não pode se autoconceder
- **WHEN** o administrador de uma clínica editar as permissões dos papéis do seu tenant
- **THEN** o sistema SHALL NOT oferecer nenhuma permissão que conceda a gestão de clínicas

#### Scenario: Item de menu
- **WHEN** um usuário sem a marca abrir o sistema
- **THEN** o sistema SHALL NOT exibir a entrada de Clínicas no menu

### Requirement: Listagem de clínicas
O sistema SHALL listar todas as clínicas cadastradas para o administrador do sistema, independentemente do tenant a que ele pertence.

#### Scenario: Ver as clínicas
- **WHEN** o administrador do sistema abrir a tela de Clínicas
- **THEN** o sistema SHALL exibir nome, identificador, CNPJ, situação e a quantidade de usuários de cada clínica

#### Scenario: Identificar a própria clínica
- **WHEN** a listagem incluir a clínica à qual a conta do usuário pertence
- **THEN** o sistema SHALL destacá-la

### Requirement: Criação de clínica pronta para uso
O sistema SHALL criar, numa única operação, a clínica, seus papéis padrão com as permissões correspondentes e o primeiro usuário administrador.

#### Scenario: Criar clínica
- **WHEN** o administrador do sistema informar os dados da clínica e do administrador inicial
- **THEN** o sistema SHALL criar a clínica, os papéis padrão com as permissões do catálogo e o usuário administrador vinculado à clínica nova, com o papel de administrador atribuído

#### Scenario: Primeiro acesso possível
- **WHEN** a clínica for criada
- **THEN** o administrador informado SHALL conseguir entrar no sistema e enxergar as telas do seu papel

#### Scenario: Falha não deixa clínica pela metade
- **WHEN** qualquer etapa da criação falhar
- **THEN** o sistema SHALL NOT persistir a clínica, os papéis nem o usuário

#### Scenario: Papéis isolados por clínica
- **WHEN** a clínica for criada
- **THEN** o sistema SHALL criar os papéis vinculados a ela, sem produzir papel global compartilhado entre clínicas

#### Scenario: Identificador repetido
- **WHEN** o identificador informado já pertencer a outra clínica
- **THEN** o sistema SHALL recusar a criação

#### Scenario: E-mail já usado
- **WHEN** o e-mail do administrador inicial já pertencer a qualquer usuário do sistema
- **THEN** o sistema SHALL recusar a criação e explicar que o e-mail é único entre todas as clínicas

### Requirement: Edição e desativação
O sistema SHALL permitir alterar nome, CNPJ e situação de uma clínica, e SHALL NOT permitir excluí-la.

#### Scenario: Alterar dados
- **WHEN** o administrador do sistema salvar nome ou CNPJ de uma clínica
- **THEN** o sistema SHALL gravar a alteração

#### Scenario: Identificador imutável
- **WHEN** uma clínica for editada
- **THEN** o sistema SHALL manter o identificador original

#### Scenario: Desativar uma clínica
- **WHEN** o administrador do sistema desativar uma clínica
- **THEN** o sistema SHALL impedir o login de todos os usuários daquela clínica, preservando os dados

#### Scenario: Não desativar a própria clínica
- **WHEN** o administrador do sistema tentar desativar a clínica à qual a sua conta pertence
- **THEN** o sistema SHALL recusar, porque a operação o trancaria para fora do sistema

#### Scenario: Sem exclusão
- **WHEN** o administrador do sistema abrir a gestão de clínicas
- **THEN** o sistema SHALL NOT oferecer exclusão de clínica
