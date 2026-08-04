## ADDED Requirements

### Requirement: Consulta real de status Unimed
O sistema SHALL executar a operação `consult_status_batch` no worker local por Playwright, com login único por lote, navegação pelo fluxo de beneficiário e consulta da guia pelo número real.

#### Scenario: Guia autorizada consultada
- **WHEN** o worker consultar uma guia cujo portal retorna situação `Autorizado` ou `Em execução`
- **THEN** o resultado SHALL mapear a guia para status interno `approved` e preservar o texto original em `unimed_status`

#### Scenario: Guia em análise consultada
- **WHEN** o worker consultar uma guia cujo portal retorna situação `Em estudo` ou `Em Análise`
- **THEN** o resultado SHALL mapear a guia para status interno `under_review` e preservar o texto original em `unimed_status`

#### Scenario: Guia negada ou cancelada consultada
- **WHEN** o worker consultar uma guia cujo portal retorna situação `Negado` ou `Cancelado`
- **THEN** o resultado SHALL mapear respectivamente para `denied` ou `canceled`

#### Scenario: Consulta conclusiva atualiza horário
- **WHEN** a consulta de status retornar resultado conclusivo para uma guia
- **THEN** o backend SHALL atualizar `unimed_last_checked_at`

#### Scenario: Tentativa falha não atualiza horário
- **WHEN** a consulta de status falhar por guia não encontrada, restrição individual ou erro não conclusivo
- **THEN** o backend SHALL NOT atualizar `unimed_last_checked_at` como se fosse consulta bem-sucedida

### Requirement: Captura separada de senha e validade
O sistema SHALL executar a operação `capture_authorization_data_batch` separada da consulta de status para guias aprovadas com número e autorização incompleta.

#### Scenario: Capturar senha e validade
- **WHEN** o worker localizar uma guia aprovada em "Exames em aberto" e a tela de execução trouxer `NR_SENHA` e `DT_VALIDADE_SENHA`
- **THEN** o backend SHALL persistir a senha e a validade normalizada sem alterar indevidamente o status da guia

#### Scenario: Guia não encontrada em exames abertos
- **WHEN** o worker percorrer todas as páginas de "Exames em aberto" sem encontrar a guia
- **THEN** o resultado SHALL registrar `NOT_FOUND_IN_OPEN_EXAMS` e o backend SHALL manter a guia elegível para tentativa futura

#### Scenario: Valores vazios não sobrescrevem dados
- **WHEN** a captura retornar senha ou validade vazia por leitura incompleta
- **THEN** o backend SHALL NOT sobrescrever senha ou validade existentes com valores vazios

### Requirement: Paginação por sessão real
O worker SHALL percorrer a listagem de "Exames em aberto" usando links reais presentes na sessão, sem dynaHash hardcoded e sem assumir número fixo de páginas.

#### Scenario: Guia em página posterior
- **WHEN** a guia elegível aparecer apenas em uma página posterior da listagem
- **THEN** o worker SHALL navegar pela paginação disponível e capturar os dados corretos da guia exata

#### Scenario: Fim da paginação
- **WHEN** não houver próxima página nem link pendente a percorrer
- **THEN** o worker SHALL encerrar a varredura sem loops infinitos

### Requirement: Elegibilidade e lote tenant-safe
O backend SHALL enfileirar e executar consulta de status e captura de senha/validade com elegibilidade própria, lock por tenant e continuação após erro individual.

#### Scenario: Elegibilidade de consulta de status
- **WHEN** uma guia Unimed tiver `numero_guia` preenchido, status não terminal e consulta devida há 24h
- **THEN** o scheduler SHALL enfileirar `consult_status_batch` para o tenant correspondente

#### Scenario: Elegibilidade de captura de autorização
- **WHEN** uma guia Unimed estiver `approved`, tiver `numero_guia` e senha ou validade ausente
- **THEN** o scheduler SHALL enfileirar `capture_authorization_data_batch`

#### Scenario: Lock por tenant
- **WHEN** já existir batch Unimed em execução para o tenant e mesma operação
- **THEN** o sistema SHALL evitar execução paralela conflitante

#### Scenario: Erro individual continua lote
- **WHEN** uma guia do lote falhar por motivo individual
- **THEN** as demais guias do lote SHALL continuar sendo processadas

#### Scenario: Erro estrutural para lote
- **WHEN** ocorrer login inválido, portal indisponível ou mudança estrutural irrecuperável
- **THEN** o lote SHALL parar e registrar erro estrutural sem tentar processar os demais itens

### Requirement: Testes por fixtures locais para status e autorização
O worker SHALL ter testes automatizados com fixtures HTML locais para consulta de status, paginação de exames em aberto e captura de senha/validade, sem acessar o portal real.

#### Scenario: Testes sem portal real
- **WHEN** a suíte do worker for executada
- **THEN** ela SHALL cobrir status, paginação e captura usando fixtures locais e SHALL NOT acessar `rda.unimedsc.com.br`
