## ADDED Requirements

### Requirement: Worker real de geração de guia Unimed
O sistema SHALL executar a operação `gerar_guia` no worker local por Playwright, preservando o contrato HTTP autenticado já usado pelo Laravel e sem acessar banco de dados diretamente.

#### Scenario: Executar operação gerar guia
- **WHEN** o Laravel chamar `POST /operations/gerar_guia` com `execution_id`, `idempotency_key` e payload da solicitação
- **THEN** o worker SHALL executar o fluxo Playwright e retornar um JSON de resultado compatível com `GerarGuiaUnimedService`

#### Scenario: Health do worker
- **WHEN** o Laravel consultar `GET /health`
- **THEN** o worker SHALL responder disponibilidade sem expor credenciais

### Requirement: Fluxo de portal para gerar guia
O worker SHALL implementar o fluxo de login, novo exame, ignorar cartão, cadastro de beneficiário, SP/SADT, formulário principal, prestador solicitante, procedimento, anexos, profissional executante e finalização.

#### Scenario: Beneficiário sem restrição
- **WHEN** a carteirinha Unimed for verificada com sucesso
- **THEN** o worker SHALL continuar para a digitação de guia SP/SADT

#### Scenario: Beneficiário com atualização cadastral
- **WHEN** o portal exibir tela de atualização cadastral
- **THEN** o worker SHALL clicar em atualizar sem alterar dados e SHALL continuar o fluxo

#### Scenario: Beneficiário com restrição administrativa
- **WHEN** o portal exibir restrição ou pendência administrativa
- **THEN** o worker SHALL retornar resultado de negócio para guia `needs_verification`, sem `numero_guia`, preservando o texto da operadora

### Requirement: Seleção de prestador solicitante com fallback
O worker SHALL selecionar o prestador solicitante procurando primeiro por CRM, depois por nome e, por fim, por prestador "nao cooperado" ativo.

#### Scenario: Prestador encontrado por CRM
- **WHEN** a busca por CRM retornar linha ativa compatível com o médico
- **THEN** o worker SHALL selecionar essa linha e registrar estratégia `crm`

#### Scenario: Prestador encontrado por nome
- **WHEN** a busca por CRM não retornar registros e a busca por nome retornar linha ativa compatível
- **THEN** o worker SHALL selecionar essa linha e registrar estratégia `nome`

#### Scenario: Fallback nao cooperado
- **WHEN** CRM e nome não retornarem prestador ativo compatível
- **THEN** o worker SHALL selecionar uma linha ativa de "MEDICO NAO COOPERADO" e registrar estratégia `nao_cooperado`

### Requirement: Procedimento, campos genéricos e anexos
O worker SHALL preencher procedimento a partir do mapeamento Especialidade x Convênio, quantidade do item e anexos obrigatórios/opcionais do payload.

#### Scenario: Procedimento comum
- **WHEN** o portal aceitar `codigo_procedimento` sem campos genéricos
- **THEN** o worker SHALL preencher `NR_QTD_1` com a quantidade do item e continuar o fluxo

#### Scenario: Procedimento com campos genéricos
- **WHEN** o portal exibir `DS_ITEM_GENERICO_1` e `VL_ITEM_GENERICO_1`
- **THEN** o worker SHALL preencher a descrição genérica e validar valor final equivalente a `0,01`

#### Scenario: Pedido médico obrigatório falha
- **WHEN** o upload de Pedido Médico falhar ou não aparecer confirmado na listagem
- **THEN** o worker SHALL interromper o item com erro de atenção operacional e sem finalizar a guia

### Requirement: Finalização idempotente e leitura de resultado
O worker SHALL clicar em "Finalizar e Gerar guia" uma única vez e SHALL interpretar o resultado terminal sem retry automático após o submit final.

#### Scenario: Guia gerada com sucesso
- **WHEN** o portal retornar número de protocolo, número de guia, situação, sessões e senha opcional
- **THEN** o worker SHALL retornar `status: succeeded` com `numero_guia`, `protocolo_operadora`, `sessoes_solicitadas`, `sessoes_autorizadas`, `senha` quando houver, `unimed_status` e `guia_status` mapeado

#### Scenario: Resultado incerto após submit
- **WHEN** ocorrer timeout ou resposta ambígua depois do clique em finalizar
- **THEN** o worker SHALL retornar `status: uncertain` com código `UNCERTAIN_AFTER_SUBMIT` e SHALL NOT reenviar a requisição ao portal

### Requirement: Testes por fixtures locais
O worker SHALL ter testes automatizados baseados em fixtures HTML locais para os estados críticos do fluxo de geração de guia.

#### Scenario: Testes sem portal real
- **WHEN** a suíte do worker for executada
- **THEN** ela SHALL cobrir os cenários principais usando fixtures locais e SHALL NOT acessar `rda.unimedsc.com.br`
