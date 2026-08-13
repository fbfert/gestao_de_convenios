## ADDED Requirements

### Requirement: Registro automático de alterações
O sistema SHALL registrar criação, alteração e exclusão das entidades auditadas sem depender de chamada explícita no controller, guardando o autor, o momento, a entidade, o identificador e os campos que mudaram, com valor anterior e novo.

#### Scenario: Alterar um registro
- **WHEN** um usuário alterar uma entidade auditada
- **THEN** o sistema SHALL gravar um evento com o autor, o momento e, para cada campo alterado, o valor anterior e o novo

#### Scenario: Alteração que não muda nada
- **WHEN** uma gravação não alterar nenhum campo
- **THEN** o sistema SHALL NOT gravar evento

#### Scenario: Ação disparada pelo sistema
- **WHEN** a alteração vier de um job agendado ou do worker de automação, sem usuário na requisição
- **THEN** o sistema SHALL gravar o evento atribuído ao sistema, e não a uma pessoa

### Requirement: Campos sensíveis nunca aparecem na trilha
O sistema SHALL registrar que um campo sensível foi alterado, sem gravar o valor anterior nem o novo, e SHALL aplicar essa regra tanto a uma lista declarada por entidade quanto a campos cujo nome indique credencial.

#### Scenario: Trocar uma senha de integração
- **WHEN** um usuário alterar a senha do portal da operadora, a chave da API de IA ou a senha do SMTP
- **THEN** o sistema SHALL registrar que o campo mudou, com autor e momento, e SHALL NOT gravar o valor anterior nem o novo

#### Scenario: Campo sensível novo sem declaração
- **WHEN** uma entidade auditada tiver um campo cujo nome indique credencial e que não esteja na lista declarada
- **THEN** o sistema SHALL tratá-lo como sensível

### Requirement: Cobertura da trilha
O sistema SHALL auditar alterações de configuração e segurança, de cadastros e de operação, além dos eventos de acesso.

#### Scenario: Alterar permissões de um papel
- **WHEN** um administrador alterar as permissões de um papel, criar, renomear ou excluir um papel, ou alterar um usuário
- **THEN** o sistema SHALL registrar o evento

#### Scenario: Alterar um convênio
- **WHEN** um usuário alterar um convênio, uma regra, um valor ou qualquer cadastro de referência
- **THEN** o sistema SHALL registrar o evento

#### Scenario: Entrar e sair do sistema
- **WHEN** um usuário fizer login, logout ou tiver uma requisição recusada por falta de permissão
- **THEN** o sistema SHALL registrar o evento com o IP e o navegador de origem

#### Scenario: Evento que não é de acesso
- **WHEN** o evento registrado não for de acesso
- **THEN** o sistema SHALL NOT guardar IP nem navegador

### Requirement: Importação em lote gera um evento
O sistema SHALL registrar um único evento por importação de analítico, com o arquivo, a quantidade de linhas e os totais, e SHALL registrar evento próprio para a alteração manual de uma linha feita depois.

#### Scenario: Importar um analítico
- **WHEN** um usuário importar um demonstrativo com muitas linhas
- **THEN** o sistema SHALL gravar um evento do lote, e não um evento por linha

#### Scenario: Corrigir uma linha à mão
- **WHEN** um usuário alterar manualmente uma linha já importada
- **THEN** o sistema SHALL registrar o evento dessa linha

### Requirement: Trilha somente-acréscimo
O sistema SHALL NOT oferecer alteração ou exclusão de registros de auditoria pela aplicação; apenas a rotina de retenção remove registros.

#### Scenario: Tentar apagar um registro
- **WHEN** qualquer usuário, em qualquer papel, tentar alterar ou excluir um registro de auditoria
- **THEN** o sistema SHALL recusar

### Requirement: Consulta com filtros e paginação
O sistema SHALL permitir consultar a trilha por período, usuário, entidade e ação, com resultado paginado e restrito ao tenant de quem consulta.

#### Scenario: Filtrar por período e usuário
- **WHEN** um usuário com permissão de ver auditoria filtrar por intervalo de datas e por usuário
- **THEN** o sistema SHALL devolver apenas os eventos correspondentes, paginados

#### Scenario: Ver o que mudou
- **WHEN** um usuário abrir o detalhe de um evento
- **THEN** o sistema SHALL mostrar cada campo alterado com o valor anterior e o novo, e SHALL indicar os campos sensíveis como alterados sem exibir valor

#### Scenario: Trilha de outro tenant
- **WHEN** um usuário consultar a trilha
- **THEN** o sistema SHALL restringir o resultado ao tenant dele

### Requirement: Exportação da consulta
O sistema SHALL permitir exportar em CSV exatamente o resultado dos filtros aplicados na consulta.

#### Scenario: Exportar resultado filtrado
- **WHEN** um usuário com permissão de ver auditoria exportar a consulta
- **THEN** o sistema SHALL gerar um CSV com os mesmos eventos que os filtros selecionaram, sem valores de campos sensíveis

### Requirement: Retenção com exportação antes do expurgo
O sistema SHALL manter os registros pelo prazo configurado na clínica, com padrão de 12 meses, e SHALL exportar em CSV o lote vencido antes de removê-lo.

#### Scenario: Expurgo diário
- **WHEN** a rotina diária encontrar registros mais antigos que o prazo configurado
- **THEN** o sistema SHALL gravar esses registros em CSV e só então removê-los

#### Scenario: Falha ao exportar
- **WHEN** a exportação do lote vencido falhar
- **THEN** o sistema SHALL NOT remover os registros

#### Scenario: Ajustar o prazo
- **WHEN** um usuário com permissão de configurar alterar o prazo de retenção
- **THEN** o sistema SHALL passar a usar o novo prazo na próxima execução da rotina
