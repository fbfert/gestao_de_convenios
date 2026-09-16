## ADDED Requirements

### Requirement: Importação em tela própria
O sistema SHALL abrir a importação do registro de sessões em tela dedicada, alcançada a partir da listagem de sessões, e SHALL NOT manter o formulário de importação embutido na listagem.

#### Scenario: Abrir a importação
- **WHEN** o usuário acionar a importação a partir da listagem de sessões
- **THEN** o sistema SHALL abrir a tela dedicada, sem manter a listagem visível no mesmo layout

### Requirement: Leitura do registro por imagem ou texto
O sistema SHALL aceitar o registro de sessões como foto, PDF ou texto colado, e SHALL devolver o mesmo conjunto de dados para revisão, qualquer que seja a origem.

#### Scenario: Ler foto ou PDF
- **WHEN** o usuário enviar uma foto ou PDF do registro
- **THEN** o sistema SHALL extrair o cabeçalho e as sessões e apresentá-los para conferência

#### Scenario: Colar a transcrição
- **WHEN** o usuário colar a transcrição em texto
- **THEN** o sistema SHALL extrair o cabeçalho e as sessões no mesmo formato da leitura por imagem

#### Scenario: Linha ilegível
- **WHEN** uma linha lida não tiver data nem horário
- **THEN** o sistema SHALL descartá-la, por ser ruído de leitura e não uma sessão

#### Scenario: Nada reconhecido
- **WHEN** a leitura não reconhecer nenhuma sessão
- **THEN** o sistema SHALL informar o usuário e SHALL manter a tela pronta para nova tentativa

#### Scenario: Nada é gravado na leitura
- **WHEN** a leitura terminar
- **THEN** o sistema SHALL NOT criar sessão alguma antes da confirmação do usuário

### Requirement: Escolha da antecipação e do executante
O sistema SHALL identificar a antecipação pelo paciente, pela especialidade e pelo saldo do ciclo, e SHALL oferecer como executante apenas quem atende a especialidade daquela antecipação.

#### Scenario: Escolher a antecipação
- **WHEN** o usuário abrir a lista de antecipações da importação
- **THEN** o sistema SHALL exibir paciente, especialidade e quanto do ciclo já foi utilizado

#### Scenario: Executante da especialidade
- **WHEN** a antecipação estiver escolhida
- **THEN** o sistema SHALL listar apenas profissionais que atendem a especialidade dela

#### Scenario: Executante único
- **WHEN** houver um único profissional para a especialidade
- **THEN** o sistema SHALL selecioná-lo automaticamente

### Requirement: Retorno visível durante a leitura
O sistema SHALL indicar de forma destacada que a leitura está em andamento e SHALL impedir o disparo de uma segunda leitura enquanto a primeira não terminar.

#### Scenario: Leitura demorada
- **WHEN** a leitura estiver em andamento
- **THEN** o sistema SHALL exibir aviso de progresso e SHALL manter os botões de leitura desabilitados
