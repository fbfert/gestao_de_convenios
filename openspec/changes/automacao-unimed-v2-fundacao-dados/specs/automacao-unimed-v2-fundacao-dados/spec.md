## ADDED Requirements

### Requirement: CID da Solicitação
O sistema SHALL permitir registrar um CID opcional na Solicitação e SHALL expor esse valor nas respostas de leitura para uso futuro como indicação clínica no portal Unimed.

#### Scenario: Criar Solicitação com CID
- **WHEN** o operador criar uma Solicitação informando CID
- **THEN** o sistema SHALL persistir o CID no tenant da Solicitação e retorná-lo na API

#### Scenario: Criar Solicitação sem CID
- **WHEN** o operador criar uma Solicitação sem CID
- **THEN** o sistema SHALL manter a Solicitação válida e retornar CID nulo ou vazio conforme o padrão da API

### Requirement: Mapeamento de Especialidade por Convênio
O sistema SHALL permitir configurar, por tenant, convênio e especialidade, o código de procedimento e dados auxiliares exigidos pela operadora.

#### Scenario: Criar mapeamento de especialidade
- **WHEN** um usuário autorizado criar mapeamento para convênio e especialidade do mesmo tenant
- **THEN** o sistema SHALL persistir `codigo_procedimento`, quantidade padrão, flags de descrição genérica e estado ativo

#### Scenario: Bloquear duplicidade de especialidade por convênio
- **WHEN** já existir mapeamento para o mesmo tenant, convênio e especialidade
- **THEN** o sistema SHALL rejeitar novo mapeamento duplicado

#### Scenario: Isolamento de tenant no mapeamento de especialidade
- **WHEN** o usuário tentar referenciar convênio ou especialidade de outro tenant
- **THEN** o sistema SHALL rejeitar a operação

### Requirement: Mapeamento de Profissional por Convênio
O sistema SHALL permitir configurar, por tenant, convênio e profissional, o código da operadora usado no portal Unimed.

#### Scenario: Criar mapeamento de profissional
- **WHEN** um usuário autorizado criar mapeamento para convênio e profissional do mesmo tenant
- **THEN** o sistema SHALL persistir `codigo_operadora`, nome da operadora opcional e estado ativo

#### Scenario: Bloquear duplicidade de profissional por convênio
- **WHEN** já existir mapeamento para o mesmo tenant, convênio e profissional
- **THEN** o sistema SHALL rejeitar novo mapeamento duplicado

#### Scenario: Isolamento de tenant no mapeamento de profissional
- **WHEN** o usuário tentar referenciar convênio ou profissional de outro tenant
- **THEN** o sistema SHALL rejeitar a operação

### Requirement: Guia compatível com retorno Unimed v2
O sistema SHALL permitir que Guias armazenem número ausente, protocolo da operadora, sessões solicitadas, sessões autorizadas e status operacionais adicionais sem quebrar status legados.

#### Scenario: Guia sem número por restrição
- **WHEN** uma Guia local representar retorno `needs_verification`
- **THEN** o sistema SHALL permitir `numero_guia` nulo e exibir marcador claro sem inventar número

#### Scenario: Status Unimed adicional
- **WHEN** uma Guia receber status `approved`, `denied`, `canceled` ou `needs_verification`
- **THEN** o sistema SHALL persistir o status aceito e a UI SHALL exibir tradução operacional adequada

#### Scenario: Campos de sessões e protocolo
- **WHEN** a API retornar uma Guia com sessões e protocolo da operadora
- **THEN** a listagem e o detalhe SHALL exibir esses valores quando presentes

### Requirement: Carteirinha Unimed segmentada
O sistema SHALL exibir e validar a carteirinha em blocos 4+4+6+2+1 para pacientes cujo convênio usa `connector_driver = unimed_rda`, preservando campo livre para demais convênios.

#### Scenario: Criar paciente Unimed com cinco blocos
- **WHEN** o operador cadastrar paciente de convênio Unimed RDA
- **THEN** o sistema SHALL exigir blocos numéricos com comprimentos 4, 4, 6, 2 e 1 e salvar o valor normalizado único

#### Scenario: Editar paciente Unimed existente
- **WHEN** o operador editar paciente Unimed com carteirinha normalizada de 17 dígitos
- **THEN** a UI SHALL decompor o valor salvo nos cinco blocos visuais

#### Scenario: Convênio não-Unimed
- **WHEN** o paciente pertencer a convênio sem driver Unimed RDA
- **THEN** o sistema SHALL preservar o comportamento atual de carteirinha em campo texto livre

### Requirement: Permissão dedicada de configurações Unimed
O sistema SHALL proteger configuração, credenciais, healthcheck e reativação Unimed com permissão dedicada atribuída apenas ao papel administrativo do tenant.

#### Scenario: Usuário sem permissão
- **WHEN** usuário autenticado sem permissão dedicada acessar rota Unimed protegida
- **THEN** a API SHALL retornar 403

#### Scenario: Usuário com permissão
- **WHEN** usuário administrativo com permissão dedicada acessar rota Unimed protegida
- **THEN** a API SHALL permitir a operação dentro do tenant autenticado

#### Scenario: Frontend sem permissão
- **WHEN** o usuário não possuir permissão dedicada
- **THEN** o frontend SHALL ocultar ou desabilitar ações de edição de configuração Unimed
