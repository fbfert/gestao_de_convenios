## ADDED Requirements

### Requirement: Consulta de status Unimed
O sistema SHALL permitir consultar status de Guias Unimed por ação manual e por rotina automática baseada em elegibilidade `due`.

#### Scenario: Consulta manual
- **WHEN** o operador solicitar atualização de status de uma Guia Unimed
- **THEN** o sistema SHALL enfileirar execução de consulta se não houver execução incompatível em andamento

#### Scenario: Consulta automática due
- **WHEN** uma Guia Unimed estiver elegível por timestamp de próxima consulta
- **THEN** o scheduler SHALL enfileirar job leve sem abrir navegador diretamente

### Requirement: Captura de senha e validade
O sistema SHALL permitir capturar senha de autorização e validade para Guias Unimed aprovadas que ainda não possuam esses dados.

#### Scenario: Capturar dados pendentes
- **WHEN** uma Guia Unimed aprovada estiver sem senha ou validade
- **THEN** o sistema SHALL consultar a Unimed e atualizar os campos locais quando encontrados

#### Scenario: Dados ainda indisponíveis
- **WHEN** a senha ou validade ainda não estiver disponível no portal
- **THEN** o sistema SHALL registrar evento e reagendar próxima elegibilidade sem marcar como erro estrutural

### Requirement: Relatórios e atenção operacional
O sistema SHALL disponibilizar relatório de execuções Unimed, filtros, timeline de eventos e avisos para automações que precisem de atenção.

#### Scenario: Execução precisa de atenção
- **WHEN** uma execução ficar em `failed`, `uncertain` ou `needs_attention`
- **THEN** o sistema SHALL exibir aviso operacional com link ao detalhe da execução

### Requirement: Reprocessamento seguro
O sistema SHALL permitir reprocessamento manual somente quando as regras de idempotência e concorrência permitirem.

#### Scenario: Bloquear reprocessamento incerto
- **WHEN** uma execução de gerar Guia estiver `uncertain`
- **THEN** o sistema SHALL exigir confirmação idempotente antes de liberar novo envio

#### Scenario: Nova tentativa vinculada
- **WHEN** o operador solicitar reprocessamento permitido
- **THEN** o sistema SHALL criar nova execução vinculada à anterior sem apagar histórico
