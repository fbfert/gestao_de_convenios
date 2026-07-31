## ADDED Requirements

### Requirement: Driver Unimed RDA por convênio
O sistema SHALL permitir configurar de forma canônica que um Convênio usa o driver Unimed RDA, sem depender de comparação textual do nome do convênio.

#### Scenario: Marcar convênio como Unimed RDA
- **WHEN** um usuário autorizado habilitar o driver Unimed RDA em um Convênio do tenant
- **THEN** o sistema SHALL persistir a configuração no contexto do tenant e do Convênio

#### Scenario: Identificar fluxo Unimed sem nome textual
- **WHEN** o sistema avaliar se uma Solicitação ou Guia pertence ao fluxo Unimed RDA
- **THEN** o sistema SHALL usar a configuração canônica do Convênio em vez de comparar `nome`

### Requirement: Credenciais Unimed seguras por tenant
O sistema SHALL permitir manter uma credencial Unimed por tenant, com senha criptografada em repouso e sem retorno da senha em responses.

#### Scenario: Salvar credencial Unimed
- **WHEN** um usuário autorizado salvar login e senha Unimed do tenant
- **THEN** o sistema SHALL criptografar a senha e persistir a configuração vinculada ao tenant

#### Scenario: Preservar senha existente
- **WHEN** um usuário autorizado atualizar a configuração sem informar nova senha
- **THEN** o sistema SHALL preservar a senha criptografada existente

#### Scenario: Não expor segredo
- **WHEN** a API retornar a configuração Unimed ao frontend
- **THEN** o sistema SHALL omitir a senha e qualquer token sensível

### Requirement: Auditoria de configuração Unimed
O sistema SHALL registrar alterações críticas da configuração Unimed em AuditLog sem incluir segredos.

#### Scenario: Alteração auditada
- **WHEN** um usuário autorizado alterar login, estado ativo ou vínculo Unimed
- **THEN** o sistema SHALL registrar tenant, usuário, ação, entidade e payload redigido sem senha
