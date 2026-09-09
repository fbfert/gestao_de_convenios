## ADDED Requirements

### Requirement: Histórico de transições de status da guia
O sistema SHALL registrar cada transição de status de uma guia com o status de origem, o status de destino, o momento em que ocorreu, o usuário responsável quando houver, a origem da transição (`manual`, `automacao`, `importacao` ou `migracao`) e um motivo opcional.

#### Scenario: Transição grava linha de histórico
- **WHEN** o status de uma guia mudar
- **THEN** o sistema SHALL gravar uma linha de histórico com o status anterior e o novo

#### Scenario: Criação registra a primeira transição
- **WHEN** uma guia for criada com um status inicial
- **THEN** o sistema SHALL gravar uma linha de histórico com o status de origem vazio

#### Scenario: Transição de robô não tem usuário
- **WHEN** a transição vier da automação ou de uma importação
- **THEN** o sistema SHALL gravar a linha sem usuário responsável
- **AND** o sistema SHALL gravar a origem correspondente

#### Scenario: Histórico é acumulativo
- **WHEN** uma guia for negada duas vezes
- **THEN** o sistema SHALL manter as duas linhas de histórico

### Requirement: Carimbos de negação e aprovação
O sistema SHALL manter em `guias` a data da última negação e a da última aprovação, escritas pela mesma operação que grava o histórico.

#### Scenario: Negar carimba a data
- **WHEN** uma guia passar para o status negado
- **THEN** o sistema SHALL preencher a data de negação da guia

#### Scenario: Aprovar carimba a data
- **WHEN** uma guia passar para o status aprovado
- **THEN** o sistema SHALL preencher a data de aprovação da guia

#### Scenario: Segunda negação atualiza o carimbo
- **WHEN** uma guia for negada de novo
- **THEN** o sistema SHALL atualizar a data de negação para a mais recente

#### Scenario: Carimbo e histórico não divergem
- **WHEN** a gravação do histórico falhar
- **THEN** o sistema SHALL NOT alterar o status nem os carimbos da guia

### Requirement: Ponto único de escrita de status
O sistema SHALL concentrar toda transição de status de guia em um único método de serviço, e SHALL NOT permitir que o status seja alterado por qualquer outro caminho.

#### Scenario: Escrita de status fora do método é recusada
- **WHEN** algum código alterar o status de uma guia e salvá-la sem passar pelo método de transição
- **THEN** o sistema SHALL lançar erro em vez de gravar

#### Scenario: Salvar outros campos continua livre
- **WHEN** algum código alterar campos da guia que não sejam o status
- **THEN** o sistema SHALL gravar normalmente

#### Scenario: Automação e importação usam o mesmo método
- **WHEN** a automação ou a importação definirem o status de uma guia
- **THEN** o sistema SHALL registrar a transição pelo mesmo método, com a origem correspondente

### Requirement: Reconstrução do histórico existente
O sistema SHALL oferecer um comando que reconstrói o histórico das guias já existentes a partir da trilha de auditoria, marcando as linhas como originadas de migração.

#### Scenario: Simulação não grava
- **WHEN** o comando for executado em modo de simulação
- **THEN** o sistema SHALL relatar o que faria sem gravar nada

#### Scenario: Linhas reconstruídas ficam identificadas
- **WHEN** o comando gravar histórico a partir da trilha
- **THEN** o sistema SHALL usar a origem de migração nessas linhas

#### Scenario: Reconstrução incompleta não interrompe
- **WHEN** não houver trilha suficiente para alguma guia
- **THEN** o sistema SHALL relatar quantas guias ficaram sem histórico e concluir sem erro
