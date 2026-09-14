## ADDED Requirements

### Requirement: Credencial de automação por convênio
O sistema SHALL armazenar, para cada tenant, no máximo uma credencial de automação por convênio,
identificada pelo par `tenant_id + convenio_id`, e SHALL permitir que um mesmo tenant tenha
credenciais de convênios diferentes ao mesmo tempo.

#### Scenario: Cadastrar credencial de um segundo convênio
- **WHEN** o usuário salvar uma credencial para um convênio que ainda não tem credencial no tenant
- **THEN** o sistema SHALL criar a credencial vinculada àquele convênio

#### Scenario: Uma credencial por convênio
- **WHEN** o usuário salvar uma credencial para um convênio que já possui credencial no tenant
- **THEN** o sistema SHALL atualizar a credencial existente
- **AND** SHALL NOT criar uma segunda linha para o mesmo par tenant e convênio

#### Scenario: Isolamento entre tenants
- **WHEN** um usuário consultar as credenciais de convênio
- **THEN** o sistema SHALL retornar apenas as credenciais do tenant do usuário

### Requirement: Credencial não liga automação
O sistema SHALL tratar o driver da credencial como informação da credencial, independente do
conector do convênio, e SHALL NOT habilitar qualquer automação, verificação ou fluxo de portal
em consequência do cadastro de uma credencial.

#### Scenario: Cadastrar credencial de convênio sem automação
- **WHEN** uma credencial for cadastrada para um convênio cujo conector não está configurado
- **THEN** o sistema SHALL persistir a credencial
- **AND** SHALL manter o convênio no fluxo manual, incluindo a verificação diária de guias

#### Scenario: Conector permanece a chave da automação
- **WHEN** o sistema avaliar se uma Solicitação ou Guia entra em fluxo automatizado
- **THEN** o sistema SHALL decidir pelo conector configurado no Convênio
- **AND** SHALL NOT decidir pela existência de credencial

### Requirement: Campos definidos pelo driver
O sistema SHALL obter do catálogo de drivers a definição dos campos de cada credencial — chave,
rótulo, tipo, obrigatoriedade e dica — e SHALL usar essa mesma definição para renderizar o
formulário e para validar o que é enviado.

#### Scenario: Formulário do driver Unimed RDA
- **WHEN** o usuário selecionar um convênio cujo driver de credencial é `unimed_rda`
- **THEN** o sistema SHALL apresentar os campos login, senha, URL base e nome do contratado

#### Scenario: Driver sem campos definidos
- **WHEN** o usuário selecionar um convênio cujo driver ainda não tem campos no catálogo
- **THEN** o sistema SHALL informar que a forma de autenticação daquele convênio está pendente de definição
- **AND** SHALL NOT apresentar campos de credencial
- **AND** SHALL NOT impedir o uso da tela para os demais convênios

#### Scenario: Campo fora do catálogo
- **WHEN** a requisição de salvamento trouxer uma chave que não pertence ao catálogo do driver escolhido
- **THEN** o sistema SHALL rejeitar a requisição com erro de validação

#### Scenario: Campo obrigatório ausente
- **WHEN** a requisição de salvamento omitir um campo marcado como obrigatório no catálogo
- **THEN** o sistema SHALL rejeitar a requisição com erro de validação

### Requirement: Proteção dos segredos da credencial
O sistema SHALL armazenar os valores da credencial cifrados em repouso, SHALL NOT devolver em
nenhuma resposta da API o valor de campo do tipo `password`, e SHALL NOT registrar esses valores
na auditoria.

#### Scenario: Consultar credencial já cadastrada
- **WHEN** o usuário abrir a tela de credenciais de um convênio já configurado
- **THEN** o sistema SHALL indicar que o campo secreto está preenchido
- **AND** SHALL NOT devolver o valor do campo

#### Scenario: Auditoria de alteração de credencial
- **WHEN** uma credencial for alterada
- **THEN** o sistema SHALL registrar na auditoria que houve alteração
- **AND** SHALL NOT registrar os valores dos campos da credencial

### Requirement: Automação usa a credencial do convênio em execução
O sistema SHALL selecionar a credencial pelo convênio do item ou da guia em execução, e SHALL
falhar com erro tratado quando esse convênio não tiver credencial ativa.

#### Scenario: Executar automação com credencial do convênio
- **WHEN** uma execução de automação for disparada para um item de um convênio
- **THEN** o sistema SHALL usar a credencial cadastrada para aquele convênio no tenant

#### Scenario: Convênio sem credencial ativa
- **WHEN** uma execução de automação for disparada para um convênio sem credencial ativa
- **THEN** o sistema SHALL recusar a execução com erro identificável
- **AND** SHALL NOT usar a credencial de outro convênio

### Requirement: Preservação das credenciais existentes na migração
O sistema SHALL migrar as credenciais gravadas em `unimed_rda_credentials` para a nova estrutura
sem perda de valor e sem exigir novo cadastro pelo usuário.

#### Scenario: Credencial Unimed existente
- **WHEN** a migração for executada num tenant que possui credencial Unimed e convênio de conector `unimed_rda`
- **THEN** o sistema SHALL criar a credencial equivalente vinculada àquele convênio, com driver `unimed_rda`
- **AND** a automação SHALL continuar funcionando com os mesmos valores, sem novo cadastro

#### Scenario: Credencial sem convênio correspondente
- **WHEN** a migração encontrar credencial num tenant sem convênio de conector `unimed_rda`
- **THEN** o sistema SHALL deixar essa credencial sem migrar
- **AND** SHALL registrar a ocorrência no log da migração

### Requirement: Acesso à tela de credenciais de convênio
O sistema SHALL exigir permissão específica para consultar e alterar credenciais de convênio, e
SHALL manter o acesso de quem já administrava a automação da Unimed.

#### Scenario: Usuário sem permissão
- **WHEN** um usuário sem a permissão de gerenciar convênios acessar as rotas de credenciais
- **THEN** o sistema SHALL negar o acesso

#### Scenario: Permissão anterior preservada
- **WHEN** um papel que já possuía a permissão de configurar a automação da Unimed for avaliado
- **THEN** o sistema SHALL conceder a ele o acesso às credenciais de convênio
