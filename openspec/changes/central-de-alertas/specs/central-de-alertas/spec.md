## ADDED Requirements

### Requirement: Registro de alertas abertos
O sistema SHALL manter um registro de alertas por tenant, com chave, nível (verde, amarelo ou vermelho), título, descrição, entidade relacionada opcional, dados de apoio, momento de abertura, momento de resolução, quem reconheceu e até quando está silenciado.

#### Scenario: Abrir alerta quando a condição passa a valer
- **WHEN** o avaliador encontrar uma condição que satisfaz uma regra ativa
- **THEN** o sistema SHALL abrir um alerta com a chave dessa regra

#### Scenario: Alerta pertence ao tenant
- **WHEN** um usuário listar os alertas
- **THEN** o sistema SHALL devolver apenas os alertas do tenant do usuário

### Requirement: Deduplicação de alertas abertos
O sistema SHALL manter no máximo um alerta aberto por combinação de tenant, chave e entidade, e SHALL permitir vários alertas já resolvidos para essa mesma combinação.

#### Scenario: Avaliar duas vezes não duplica
- **WHEN** o avaliador rodar duas vezes seguidas com a mesma condição valendo
- **THEN** o sistema SHALL manter um único alerta aberto

#### Scenario: Reabrir depois de resolvido é permitido
- **WHEN** a condição voltar a valer depois de o alerta ter sido resolvido
- **THEN** o sistema SHALL abrir um alerta novo, preservando o resolvido

### Requirement: Fechamento automático
O sistema SHALL resolver automaticamente os alertas abertos cuja condição deixou de valer.

#### Scenario: Condição some e o alerta se resolve
- **WHEN** o avaliador rodar e a condição que originou um alerta aberto não valer mais
- **THEN** o sistema SHALL marcar esse alerta como resolvido

#### Scenario: Regra desativada não gera nem mantém
- **WHEN** uma regra estiver desativada
- **THEN** o sistema SHALL NOT abrir alertas dessa chave

### Requirement: Reconhecer e silenciar
O sistema SHALL permitir que um usuário reconheça um alerta e que o silencie até uma data.

#### Scenario: Reconhecer registra quem e quando
- **WHEN** um usuário reconhecer um alerta
- **THEN** o sistema SHALL registrar o usuário e o momento

#### Scenario: Alerta silenciado não volta antes do prazo
- **WHEN** um alerta estiver silenciado até uma data futura
- **THEN** o sistema SHALL NOT exibi-lo entre os alertas pendentes

### Requirement: Limiares configuráveis por tenant
O sistema SHALL manter as regras de alerta em tabela, com estado ativo, nível base, limiares e marcação de crítica, e SHALL NOT trazer limiar embutido no código.

#### Scenario: Alterar limiar muda o resultado sem alterar código
- **WHEN** o limiar de uma regra for alterado
- **THEN** o sistema SHALL passar a usar o novo limiar na avaliação seguinte

#### Scenario: Tenant novo nasce com as regras padrão
- **WHEN** um tenant for populado com os dados iniciais
- **THEN** o sistema SHALL criar as regras padrão para esse tenant

### Requirement: Regras da primeira entrega
O sistema SHALL avaliar as regras de senha vencendo, guia negada, falhas em série da automação e componente de saúde fora.

#### Scenario: Senha vencendo dentro da janela configurada
- **WHEN** uma guia tiver validade de senha dentro da janela configurada no tenant
- **THEN** o sistema SHALL abrir um alerta de senha vencendo

#### Scenario: Guia negada ainda não tratada
- **WHEN** uma guia estiver negada com o alerta de negação ainda visível
- **THEN** o sistema SHALL abrir um alerta de guia negada

#### Scenario: Falhas consecutivas da automação
- **WHEN** a mesma operação da automação falhar seguidamente acima do limiar dentro da janela configurada
- **THEN** o sistema SHALL abrir um alerta de falhas em série

#### Scenario: Componente de saúde fora
- **WHEN** um componente de saúde estiver sem heartbeat além do limite
- **THEN** o sistema SHALL abrir um alerta de componente fora

### Requirement: Ações do alerta de guia negada
O sistema SHALL oferecer, no alerta de guia negada, as mesmas ações que o banner de guias negadas oferecia: ocultar o alerta e abrir uma nova solicitação a partir da guia.

#### Scenario: Ocultar resolve o alerta
- **WHEN** o usuário ocultar o alerta de uma guia negada
- **THEN** o sistema SHALL deixar de exibi-lo

#### Scenario: Nova solicitação a partir da guia
- **WHEN** o usuário optar por abrir nova solicitação a partir do alerta
- **THEN** o sistema SHALL levá-lo ao cadastro de solicitação com os dados da guia

### Requirement: Central de alertas e configuração
O sistema SHALL oferecer uma tela de alertas com filtro por nível, chave e situação, e uma tela de configuração das regras, ambas protegidas por permissão própria.

#### Scenario: Filtrar por nível
- **WHEN** o usuário filtrar a listagem por nível
- **THEN** o sistema SHALL devolver apenas os alertas daquele nível

#### Scenario: Sem permissão não acessa
- **WHEN** um usuário sem a permissão de ver alertas consultar a listagem
- **THEN** o sistema SHALL recusar o acesso

#### Scenario: Configurar exige permissão de gestão
- **WHEN** um usuário sem a permissão de gerir alertas tentar alterar uma regra
- **THEN** o sistema SHALL recusar a alteração

### Requirement: Card de alertas no dashboard
O sistema SHALL exibir no dashboard um card com os cinco alertas abertos mais recentes de nível amarelo ou vermelho, com atalho para a listagem completa.

#### Scenario: Card mostra apenas amarelo e vermelho
- **WHEN** houver alertas abertos de nível verde
- **THEN** o sistema SHALL NOT exibi-los no card do dashboard

#### Scenario: Sem alertas o card informa que não há pendência
- **WHEN** não houver alerta aberto de nível amarelo ou vermelho
- **THEN** o sistema SHALL exibir o card informando que não há alerta pendente
