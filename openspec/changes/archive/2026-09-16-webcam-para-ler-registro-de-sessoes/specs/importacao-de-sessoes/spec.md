## MODIFIED Requirements

### Requirement: Leitura do registro por imagem ou texto

O sistema SHALL aceitar o registro de sessões como foto escolhida do dispositivo, como foto capturada pela webcam, como PDF ou como texto colado, e SHALL devolver o mesmo conjunto de dados para revisão, qualquer que seja a origem.

O sistema SHALL oferecer a captura pela webcam somente quando o navegador a disponibilizar, e SHALL manter os demais caminhos de leitura utilizáveis quando ela não estiver disponível.

O sistema SHALL apresentar a foto capturada para conferência antes de enviá-la para leitura, permitindo descartá-la e capturar outra.

O sistema SHALL encerrar o acesso à câmera ao terminar a captura, ao cancelar e ao sair da tela.

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
