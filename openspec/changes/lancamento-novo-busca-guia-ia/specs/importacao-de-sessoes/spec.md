## MODIFIED Requirements

### Requirement: Leitura do registro por imagem ou texto
O sistema SHALL aceitar o registro de sessões como foto escolhida do dispositivo, como foto capturada pela webcam, como PDF ou como texto colado, e SHALL devolver o mesmo conjunto de dados para revisão, qualquer que seja a origem. A leitura por imagem/PDF SHALL sempre devolver exatamente 10 sessões — o máximo físico de uma folha de registro —, preservando a posição física de cada linha da folha, mesmo quando em branco ou ilegível.

Este requirement substitui o comportamento anterior (mudança `importar-sessoes-por-ia`), que descartava qualquer linha sem data nem horário como ruído de leitura. Descartar reordenava as sessões seguintes para posições que não eram as delas na folha física, impedindo completar manualmente uma linha específica depois.

#### Scenario: Ler antes de escolher a guia
- **WHEN** o usuário acionar a leitura sem guia e sem executante escolhidos
- **THEN** o sistema SHALL ler o registro normalmente, porque os dados que identificam a guia estão na própria folha

#### Scenario: Ler foto ou PDF
- **WHEN** o usuário enviar uma foto ou PDF do registro
- **THEN** o sistema SHALL extrair o cabeçalho e apresentar exatamente 10 sessões para conferência, na mesma ordem das linhas impressas na folha

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
- **WHEN** uma linha lida por IA não tiver data nem horário
- **THEN** o sistema SHALL mantê-la na posição física correspondente da folha, com todos os campos em branco, em vez de descartá-la

#### Scenario: Nada reconhecido
- **WHEN** a leitura não reconhecer nenhuma sessão
- **THEN** o sistema SHALL informar o usuário e SHALL manter a tela pronta para nova tentativa

#### Scenario: Nada é gravado na leitura
- **WHEN** a leitura terminar
- **THEN** o sistema SHALL NOT criar sessão alguma antes da confirmação do usuário

#### Scenario: Nome do acompanhante sem a assinatura
- **WHEN** a folha tiver uma assinatura manuscrita do acompanhante
- **THEN** o sistema SHALL ler apenas o nome do acompanhante escrito por extenso, quando houver, e SHALL NOT tentar transcrever a assinatura

#### Scenario: Resumo de atividades em várias linhas de texto
- **WHEN** o resumo de atividades de uma sessão ocupar mais de uma linha de texto dentro do bloco delimitado pelas linhas divisórias da folha
- **THEN** o sistema SHALL juntar todo esse texto em um único `resumo_atividades` para aquela sessão, e SHALL NOT misturar texto de um bloco de sessão com o de outro
