## ADDED Requirements

### Requirement: Homologacao assistida contra o portal real
O sistema SHALL ser homologado contra o portal real da Unimed RDA somente em sessao assistida, com responsavel presente e credenciais fornecidas fora do chat, dos prompts, dos logs e dos arquivos versionados.

#### Scenario: Sessao assistida autorizada
- **WHEN** a homologacao real for iniciada
- **THEN** o responsavel SHALL estar presente e a credencial SHALL estar configurada diretamente no ambiente local ou na interface da aplicacao, sem senha colada no chat

#### Scenario: Ausencia de credencial segura
- **WHEN** a credencial de teste/homologacao nao estiver disponivel de forma segura
- **THEN** a execucao contra o portal real SHALL ser interrompida e registrada como bloqueada

### Requirement: Evidencia sanitizada por caso de homologacao
A homologacao SHALL registrar para cada caso o resultado esperado, o resultado obtido, a evidencia conferida e a decisao do caso, sem expor segredos ou dados sensiveis.

#### Scenario: Caso executado
- **WHEN** um caso do roteiro for executado contra o portal real
- **THEN** o relatorio SHALL registrar esperado, obtido, evidencia sanitizada, status do caso e pendencias, se existirem

#### Scenario: Dados sensiveis em evidencia
- **WHEN** a evidencia contiver senha, carteirinha completa, documento de paciente, credencial ou dado clinico sensivel
- **THEN** o relatorio SHALL omitir, mascarar ou resumir essa evidencia

### Requirement: Roteiro minimo de homologacao Unimed v2
A homologacao SHALL cobrir os fluxos criticos de gerar guia, consultar status, capturar senha/validade, tratamento de restricoes, fallback de medico, upload de documentos, resultado incerto e pausa por mudanca estrutural.

#### Scenario: Casos criticos avaliados
- **WHEN** a homologacao for concluida
- **THEN** o relatorio SHALL indicar o resultado dos 15 casos definidos no roteiro da Etapa 5 ou justificar explicitamente qualquer caso nao executado

#### Scenario: Comportamento inesperado do portal
- **WHEN** o portal real apresentar estrutura inesperada ou comportamento nao coberto
- **THEN** o teste daquele caso SHALL parar, registrar a ocorrencia e evitar tentativas repetidas que possam duplicar envio ou alterar estado externo indevidamente

### Requirement: Decisao GO/NO-GO e rollback
A homologacao SHALL produzir decisao GO/NO-GO antes de habilitar uso produtivo da automacao Unimed v2 e SHALL incluir plano de rollback.

#### Scenario: Casos criticos aprovados
- **WHEN** os casos criticos forem aprovados ou tiverem pendencias aceitas e documentadas
- **THEN** o relatorio SHALL registrar GO, condicoes de uso e plano de rollback

#### Scenario: Pendencia critica sem aceite
- **WHEN** houver pendencia critica sem aceite do responsavel
- **THEN** o relatorio SHALL registrar NO-GO e manter o uso produtivo bloqueado
