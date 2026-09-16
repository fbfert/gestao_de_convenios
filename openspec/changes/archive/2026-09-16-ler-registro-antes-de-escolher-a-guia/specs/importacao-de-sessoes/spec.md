## MODIFIED Requirements

### Requirement: Leitura do registro por imagem ou texto

O sistema SHALL aceitar o registro de sessões como foto escolhida do dispositivo, como foto capturada pela webcam, como PDF ou como texto colado, e SHALL devolver o mesmo conjunto de dados para revisão, qualquer que seja a origem.

O sistema SHALL permitir a leitura **antes** de a guia e o executante estarem escolhidos, e SHALL NOT exigir a guia para ler.

O sistema SHALL oferecer a captura pela webcam somente quando o navegador a disponibilizar, e SHALL manter os demais caminhos de leitura utilizáveis quando ela não estiver disponível.

O sistema SHALL apresentar a foto capturada para conferência antes de enviá-la para leitura, permitindo descartá-la e capturar outra.

O sistema SHALL encerrar o acesso à câmera ao terminar a captura, ao cancelar e ao sair da tela.

#### Scenario: Ler antes de escolher a guia
- **WHEN** o usuário acionar a leitura sem guia e sem executante escolhidos
- **THEN** o sistema SHALL ler o registro normalmente, porque os dados que identificam a guia estão na própria folha

#### Scenario: Ler foto ou PDF
- **WHEN** o usuário enviar uma foto ou PDF do registro
- **THEN** o sistema SHALL extrair o cabeçalho e as sessões e apresentá-los para conferência

#### Scenario: Capturar pela webcam
- **WHEN** o usuário capturar o registro pela webcam e confirmar a foto
- **THEN** o sistema SHALL lê-la pelo mesmo caminho da foto escolhida do dispositivo, apresentando o cabeçalho e as sessões para conferência

#### Scenario: Descartar a foto capturada
- **WHEN** o usuário descartar a foto capturada
- **THEN** o sistema SHALL NOT enviá-la para leitura e SHALL voltar a mostrar a imagem ao vivo da câmera

#### Scenario: Câmera indisponível no navegador
- **WHEN** o navegador não disponibilizar acesso à câmera
- **THEN** o sistema SHALL NOT oferecer a captura pela webcam, e SHALL manter a leitura por arquivo e por texto

#### Scenario: Permissão de câmera negada
- **WHEN** o acesso à câmera for recusado ou falhar
- **THEN** o sistema SHALL informar o motivo e SHALL indicar os outros caminhos de leitura, sem impedir o uso da tela

#### Scenario: Câmera liberada ao sair
- **WHEN** o usuário confirmar a foto, cancelar a captura ou sair da tela
- **THEN** o sistema SHALL encerrar o acesso à câmera

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

O sistema SHALL usar o número da guia lido no registro para escolher a guia: resolvendo para uma única guia disponível para lançamento, SHALL selecioná-la e SHALL indicar que a escolha veio da leitura; não resolvendo, SHALL abrir a busca de guia já preenchida com o número lido.

O sistema SHALL apresentar o nome do executante lido no registro como informação de conferência, e SHALL NOT preenchê-lo automaticamente a partir da leitura.

O sistema SHALL exigir a guia e o executante para confirmar as sessões, independentemente de a leitura ter ocorrido antes.

#### Scenario: Escolher a antecipação
- **WHEN** o usuário abrir a lista de antecipações da importação
- **THEN** o sistema SHALL exibir paciente, especialidade e quanto do ciclo já foi utilizado

#### Scenario: Executante da especialidade
- **WHEN** a antecipação estiver escolhida
- **THEN** o sistema SHALL listar apenas profissionais que atendem a especialidade dela

#### Scenario: Executante único
- **WHEN** houver um único profissional para a especialidade
- **THEN** o sistema SHALL selecioná-lo automaticamente

#### Scenario: Número lido identifica uma guia
- **WHEN** o número de guia lido corresponder a exatamente uma guia disponível para lançamento
- **THEN** o sistema SHALL selecionar essa guia e SHALL indicar que a escolha veio da leitura

#### Scenario: Número lido não identifica uma guia
- **WHEN** o número de guia lido não corresponder a nenhuma guia disponível, ou corresponder a mais de uma
- **THEN** o sistema SHALL abrir a busca de guia com o número lido já preenchido, e SHALL NOT escolher guia alguma

#### Scenario: Registro sem número de guia legível
- **WHEN** a leitura não reconhecer o número da guia
- **THEN** o sistema SHALL manter a escolha da guia com o usuário, sem abrir a busca nem escolher por outro dado

#### Scenario: Executante lido é só informação
- **WHEN** a leitura reconhecer o nome do executante
- **THEN** o sistema SHALL exibi-lo junto ao campo de executante e SHALL NOT selecionar profissional algum a partir dele

#### Scenario: Confirmar continua exigindo guia e executante
- **WHEN** o usuário tentar confirmar as sessões sem guia ou sem executante escolhidos
- **THEN** o sistema SHALL recusar a confirmação, ainda que a leitura já tenha sido feita
