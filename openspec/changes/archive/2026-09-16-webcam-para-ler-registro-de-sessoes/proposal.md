## Why

Em `/lancamentos/novo`, "Ler foto ou PDF do registro" abre um seletor de arquivo. O `<input>` tem `capture="environment"`, que no celular abre a câmera do sistema — mas **no computador esse atributo é ignorado**, e é no computador que a recepção trabalha. Hoje, para lançar as sessões de uma folha que está na mesa, o caminho é: fotografar com o celular, mandar para si mesmo, baixar no computador, e então escolher o arquivo. A webcam que já está em cima da mesa não é usada.

A tela de Pacientes já resolveu isso: `LerCarteirinha` tem um botão "Usar webcam" que captura o quadro na própria página e manda para a IA. O mecanismo está provado — falta no registro de sessões.

Duas diferenças que o registro impõe, e que fazem dele mais que um copiar-e-colar da carteirinha:

- **Conferir antes de enviar.** A carteirinha envia assim que captura. O registro é uma folha A4 com até 10 linhas de letra manuscrita: uma foto tremida, cortada ou com reflexo custa uma chamada de IA e de 5 a 30 segundos de espera até o operador descobrir que não deu. A foto passa a congelar na tela, com "Usar esta foto" e "Tirar outra".
- **Resolução maior.** 1600px de largura basta para um cartão; para uma folha inteira com data, dois horários e assinatura por linha, o texto chega ao limite da legibilidade. O registro captura em 2400px — ainda muito abaixo do teto de 20MB do `LerRegistroSessoesRequest`.

## What Changes

- **Novo botão "Usar webcam"** em `/lancamentos/novo`, ao lado de "Ler foto ou PDF do registro", aparecendo só quando o navegador expõe `mediaDevices.getUserMedia`. A foto capturada entra **no mesmo caminho** do arquivo escolhido (`POST /guias/{id}/lancamentos/ler-registro`), então a conferência das 10 linhas e a confirmação seguem exatamente iguais — muda só de onde a imagem veio.
- **Conferência da foto antes de enviar**, com "Usar esta foto" e "Tirar outra".
- **Componente compartilhado `CapturaWebcam`**, extraído de `LerCarteirinha`. Hoje o `getUserMedia`, o desenho no canvas e o `getTracks().forEach(stop)` — que é o que apaga a luz da webcam — existiriam em duas cópias. É o tipo de código que recebe uma correção num lugar e fica desatualizado no outro; a luz da câmera que não apaga é justamente o defeito que assusta o usuário. `LerCarteirinha` passa a usar o componente, com o comportamento atual preservado (envio direto, 1600px).

## Capabilities

### Modified Capabilities
- `importacao-de-sessoes`: a leitura do registro passa a aceitar também a captura pela webcam, com conferência da foto antes do envio.

> **Ordem de arquivamento.** A capability `importacao-de-sessoes` ainda não está em `openspec/specs/` — ela nasce na change `importar-sessoes-por-ia`, que segue aberta pela tarefa 3.4 (conferir uma leitura real no navegador, com a chave OpenAI de produção — depende de ambiente e de um humano, não de código). Por isso `openspec validate --strict` passa mas avisa que um archive desta change seria recusado enquanto a spec base não existir. Esta change precisa ser arquivada **depois** daquela.

## Impact

**Web**
- `components/ui/CapturaWebcam.tsx` (novo, extraído de `LerCarteirinha`)
- `features/lancamentos/LancamentosPage.tsx`
- `features/pacientes/LerCarteirinha.tsx` (passa a consumir o componente)

**API**
- Nenhuma mudança. A foto da webcam é um `File` JPEG comum, dentro de `mimes:pdf,jpg,jpeg,png` e do teto de 20MB que `LerRegistroSessoesRequest` já valida.

**Não faz parte desta change**
- Aceitar mais de uma foto por registro (verso da folha): a API recebe um arquivo e a tela confere até 10 sessões, então seria mudança de backend, do serviço de IA e da conferência.
- Recorte, correção de perspectiva ou realce da imagem antes do envio.
- Gravar a foto da webcam em outro lugar que não o mesmo destino do arquivo escolhido hoje.

## Nota de ambiente

`getUserMedia` só existe em contexto seguro: HTTPS, ou `localhost`/`127.0.0.1`. Em produção (`https://gescon.gestaonossa.com.br`) e no `vite dev` local funciona. O que **não** funciona é abrir o ambiente de desenvolvimento pelo IP da rede em `http://` — ali o botão simplesmente não aparece, porque `mediaDevices` fica indefinido. É limitação do navegador, não da implementação.
