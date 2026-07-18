## ADDED Requirements

### Requirement: Cadastro operacional de profissionais da clínica
O sistema SHALL permitir que usuários autorizados cadastrem, editem, listem e desativem profissionais executantes da clínica por tenant.

Cada profissional SHALL conter, no mínimo:
- nome
- especialidade vinculada
- conselho/registro
- percentual de repasse
- status ativo/inativo

#### Scenario: Criar profissional
- **WHEN** um usuário com permissão de gerenciamento enviar os dados válidos de um profissional
- **THEN** o sistema SHALL persistir o profissional vinculado ao tenant autenticado

#### Scenario: Editar profissional
- **WHEN** um usuário com permissão de gerenciamento atualizar um profissional existente
- **THEN** o sistema SHALL salvar as alterações e manter o vínculo com o tenant original

#### Scenario: Desativar profissional
- **WHEN** um usuário com permissão de gerenciamento desativar um profissional
- **THEN** o sistema SHALL preservar o cadastro para uso histórico e impedir que ele seja tratado como ativo nas listas operacionais

### Requirement: Referência de profissionais para fluxos operacionais
O sistema SHALL manter um endpoint de listagem de profissionais para uso em guias, solicitações, lançamentos, conciliação e demais telas operacionais.

Esse endpoint SHALL continuar retornando os dados necessários para seleção de profissionais sem exigir a permissão de manutenção.
Por padrão, o endpoint SHALL retornar apenas profissionais ativos. Quando solicitado explicitamente, o sistema SHALL permitir incluir profissionais inativos para a tela de administração.

#### Scenario: Formulário operacional consulta profissionais
- **WHEN** uma tela de solicitação ou guia carregar os profissionais disponíveis
- **THEN** o sistema SHALL retornar a lista para preenchimento dos selects operacionais

#### Scenario: Manutenção não quebra referência
- **WHEN** a clínica criar ou editar um profissional no CRUD
- **THEN** o sistema SHALL continuar atendendo os consumidores atuais do endpoint de referência

#### Scenario: Listagem operacional ignora inativos
- **WHEN** um consumidor chamar o endpoint de profissionais sem solicitar inativos
- **THEN** o sistema SHALL retornar apenas profissionais ativos

#### Scenario: Tela administrativa inclui inativos
- **WHEN** a tela de administração solicitar a listagem completa
- **THEN** o sistema SHALL incluir profissionais ativos e inativos na resposta

### Requirement: Restrição por tenant e permissão
O sistema SHALL restringir as ações de criação e edição de profissionais aos usuários autorizados do tenant autenticado.

#### Scenario: Bloquear usuário sem permissão
- **WHEN** um usuário sem permissão tentar criar ou alterar um profissional
- **THEN** o sistema SHALL negar a operação

#### Scenario: Isolamento por tenant
- **WHEN** um usuário de outro tenant tentar acessar um profissional
- **THEN** o sistema SHALL impedir acesso ou alteração fora do contexto do tenant autenticado
