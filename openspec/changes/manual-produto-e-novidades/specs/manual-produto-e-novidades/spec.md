## ADDED Requirements

### Requirement: Manual servido do repositório
O sistema SHALL servir o manual e o mapa mental a partir de arquivos versionados no repositório, iguais para todos os tenants.

#### Scenario: Manual é lido do arquivo
- **WHEN** um usuário abrir o manual
- **THEN** o sistema SHALL devolver o conteúdo do arquivo versionado

#### Scenario: Mapa mental é lido do arquivo
- **WHEN** um usuário abrir o mapa mental
- **THEN** o sistema SHALL devolver o conteúdo do arquivo versionado

#### Scenario: Tipo desconhecido é recusado
- **WHEN** for solicitado um tipo de documento que não existe
- **THEN** o sistema SHALL responder que não foi encontrado

### Requirement: Manual não é editável pela interface
O sistema SHALL NOT oferecer edição do manual pela aplicação, e SHALL NOT expor permissão para isso.

#### Scenario: Não há endpoint de edição
- **WHEN** for feita uma tentativa de alterar o manual pela API
- **THEN** o sistema SHALL recusar

#### Scenario: A permissão de editar manual não existe mais
- **WHEN** o catálogo de permissões for consultado
- **THEN** o sistema SHALL NOT listar a permissão de editar manual

### Requirement: Novidades versionadas
O sistema SHALL ler as novidades de arquivos markdown do repositório, cada um com título, tipo e data no frontmatter, e SHALL devolvê-las da mais recente para a mais antiga.

#### Scenario: Listar novidades
- **WHEN** o usuário consultar as novidades
- **THEN** o sistema SHALL devolver as novidades ordenadas da mais recente para a mais antiga

#### Scenario: Limitar a quantidade
- **WHEN** a consulta pedir um limite
- **THEN** o sistema SHALL devolver no máximo essa quantidade

#### Scenario: Arquivo sem frontmatter válido é ignorado
- **WHEN** um arquivo de novidade não tiver os campos obrigatórios
- **THEN** o sistema SHALL ignorá-lo sem falhar a listagem

### Requirement: Controle de leitura por usuário
O sistema SHALL registrar quais novidades cada usuário já leu, e SHALL informar quantas ainda não foram lidas.

#### Scenario: Marcar como lida
- **WHEN** o usuário marcar uma novidade como lida
- **THEN** o sistema SHALL registrar a leitura para aquele usuário

#### Scenario: Contagem de não lidas
- **WHEN** o usuário consultar as novidades
- **THEN** o sistema SHALL informar quantas ainda não foram lidas por ele

#### Scenario: Leitura é por usuário, não por tenant
- **WHEN** um usuário marcar uma novidade como lida
- **THEN** o sistema SHALL NOT considerá-la lida para os demais usuários

#### Scenario: Marcar de novo não duplica
- **WHEN** o usuário marcar como lida uma novidade que já havia lido
- **THEN** o sistema SHALL manter um único registro de leitura
