## ADDED Requirements

### Requirement: Destinatários do tenant
O sistema SHALL manter destinatários de alerta por tenant, cada um com e-mail, nome, níveis desejados, chaves desejadas, canal, horário do digest, estado ativo e marcação de verificado.

#### Scenario: Destinatário recebe apenas as chaves que escolheu
- **WHEN** um destinatário estiver inscrito somente em uma chave
- **THEN** o sistema SHALL NOT enviar a ele alertas de outras chaves

#### Scenario: Destinatário recebe apenas os níveis que escolheu
- **WHEN** um destinatário estiver inscrito somente no nível crítico
- **THEN** o sistema SHALL NOT enviar a ele alertas de nível de atenção

#### Scenario: Destinatário inativo não recebe
- **WHEN** um destinatário estiver inativo
- **THEN** o sistema SHALL NOT enviar nada a ele

### Requirement: Destinatários globais do suporte
O sistema SHALL manter destinatários globais fora do escopo de tenant, e SHALL enviar a eles um único digest por dia agregando todos os tenants.

#### Scenario: Digest global agrega todos os tenants
- **WHEN** houver alertas abertos em mais de um tenant
- **THEN** o sistema SHALL enviar ao destinatário global um único e-mail com todos eles

#### Scenario: Destinatário global não depende do escopo de tenant
- **WHEN** os destinatários globais forem consultados sem tenant resolvido
- **THEN** o sistema SHALL devolvê-los normalmente

### Requirement: Digest diário
O sistema SHALL enviar, no horário configurado por destinatário, um resumo dos alertas abertos agrupados por nível, e SHALL NOT enviar nada quando não houver alerta aberto.

#### Scenario: Digest não sai quando não há alerta
- **WHEN** chegar o horário do digest e não houver alerta aberto elegível
- **THEN** o sistema SHALL NOT enviar e-mail

#### Scenario: Digest sai apenas no horário do destinatário
- **WHEN** a hora corrente não for a configurada para um destinatário
- **THEN** o sistema SHALL NOT enviar o digest a ele naquela execução

### Requirement: Envio imediato de alerta crítico
O sistema SHALL enviar aviso imediato apenas para alertas de nível crítico cuja regra esteja marcada como crítica, respeitando uma janela de silêncio por alerta.

#### Scenario: Alerta de atenção não dispara envio imediato
- **WHEN** um alerta de nível de atenção for aberto
- **THEN** o sistema SHALL NOT enviar aviso imediato

#### Scenario: Regra não crítica não dispara envio imediato
- **WHEN** um alerta crítico pertencer a uma regra não marcada como crítica
- **THEN** o sistema SHALL NOT enviar aviso imediato

#### Scenario: Não reenvia dentro da janela de silêncio
- **WHEN** o mesmo alerta já tiver sido avisado dentro da janela configurada
- **THEN** o sistema SHALL NOT avisar de novo

#### Scenario: Falhas em série viram um aviso
- **WHEN** a mesma condição ocorrer várias vezes seguidas
- **THEN** o sistema SHALL enviar um único aviso, e não um por ocorrência

### Requirement: Corpo a partir dos modelos existentes
O sistema SHALL montar o corpo das notificações a partir dos modelos de e-mail já cadastrados, e SHALL usar um texto padrão quando não houver modelo para a chave.

#### Scenario: Modelo cadastrado é usado
- **WHEN** existir um modelo de e-mail para a chave da notificação
- **THEN** o sistema SHALL usar o assunto e o corpo desse modelo

#### Scenario: Ausência de modelo não impede o envio
- **WHEN** não existir modelo para a chave
- **THEN** o sistema SHALL enviar mesmo assim, com o texto padrão

### Requirement: Separação dos servidores de envio
O sistema SHALL enviar os e-mails do tenant pelo servidor SMTP do próprio tenant, e os e-mails dos destinatários globais pelo servidor da aplicação.

#### Scenario: E-mail do suporte não depende do SMTP do tenant
- **WHEN** o SMTP do tenant estiver com falha
- **THEN** o sistema SHALL ainda assim conseguir enviar ao destinatário global

### Requirement: Higiene de destinatário
O sistema SHALL desativar um destinatário após falhas de envio consecutivas acima do limite e SHALL abrir um alerta informando a desativação.

#### Scenario: Desativação após falhas seguidas
- **WHEN** os envios a um destinatário falharem consecutivamente acima do limite
- **THEN** o sistema SHALL desativá-lo

#### Scenario: Desativação gera alerta
- **WHEN** um destinatário for desativado por falhas
- **THEN** o sistema SHALL abrir um alerta de destinatário falhando

#### Scenario: Envio bem-sucedido marca como verificado
- **WHEN** um envio a um destinatário ainda não verificado tiver sucesso
- **THEN** o sistema SHALL marcá-lo como verificado
