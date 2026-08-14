## ADDED Requirements

### Requirement: Contadores em linha compacta
O sistema SHALL exibir os contadores de uma listagem em uma única linha, com rótulo e valor lado a lado, e SHALL NOT usar cartões de altura cheia para esse fim.

#### Scenario: Abrir uma listagem com contadores
- **WHEN** o usuário abrir uma listagem que exibe totais
- **THEN** o sistema SHALL apresentá-los em linha compacta, deixando a listagem visível sem rolagem adicional

#### Scenario: Tela estreita
- **WHEN** a largura disponível não comportar todos os contadores
- **THEN** o sistema SHALL quebrar a linha, mantendo cada contador legível

### Requirement: Sem texto descritivo redundante
O sistema SHALL NOT exibir texto que apenas descreva o que a tela já mostra, e SHALL preservar mensagens de estado e avisos que carreguem informação não deduzível.

#### Scenario: Listagem
- **WHEN** o usuário abrir uma listagem
- **THEN** o sistema SHALL NOT exibir parágrafo descrevendo o conteúdo da própria tabela

#### Scenario: Mensagem de estado
- **WHEN** a tela estiver carregando, vazia ou em erro
- **THEN** o sistema SHALL exibir a mensagem correspondente

#### Scenario: Garantia sobre o dado
- **WHEN** houver informação que o usuário não consegue deduzir da tela, como o que a auditoria deixa de registrar
- **THEN** o sistema SHALL mantê-la acessível, ainda que fora do fluxo principal de leitura
