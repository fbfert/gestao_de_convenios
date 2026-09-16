> Implementado em 16/09/2026. `npx tsc -b`, `npm run lint` e `npm run build`
> sem erro no `web/`; suíte e2e verde com 24 testes (21 de antes + 3 novos).

## 1. Componente compartilhado

- [x] 1.1 `components/ui/CapturaWebcam.tsx`, extraído de `LerCarteirinha`: abre o stream, mostra o vídeo, captura o quadro em JPEG e devolve um `File`. Não envia nada sozinho — quem chama decide, e por isso a foto entra no mesmo caminho de quem escolheu um arquivo.
- [x] 1.2 Trilhas paradas ao capturar, ao cancelar e ao desmontar. O desmonte cobre também o caso de a tela sair durante o `await` do `getUserMedia`: sem a guarda `cancelado`, o stream chegaria órfão, com a luz acesa e sem ninguém para pará-lo.
- [x] 1.3 `larguraMaxima` como propriedade — 1600 na carteirinha, 2400 no registro.
- [x] 1.4 `conferirAntesDeEnviar` congela o quadro com "Usar esta foto" / "Tirar outra". Desligado por padrão, para o envio direto da carteirinha não mudar.
- [x] 1.5 `webcamDisponivel()` exportado do mesmo módulo.

**Dois defeitos que o e2e expôs, e que não estavam no plano:**

- [x] 1.6 A captura habilitava assim que o stream chegava, mas `videoWidth` só existe depois do `loadedmetadata` — clicar antes caía num `return` silencioso: botão aceso que não fazia nada. `pronta` passou a subir no `onLoadedMetadata` do `<video>`, não no `.then()` do `getUserMedia`.
- [x] 1.7 O `<video>` DESMONTA quando a prévia congelada entra no lugar dele, e volta em "Tirar outra" como um elemento novo, de `srcObject` vazio. Atribuir o stream uma vez só, no `.then()`, acertava a primeira montagem e deixava a segunda captura num quadro preto — sem erro nenhum. Virou ref de callback (`ligarVideo`), que religa o stream toda vez que o elemento aparece. Conferido que o e2e pega a regressão: trocando `ref={ligarVideo}` por `ref={videoRef}`, a asserção de 4.3 falha.

## 2. Carteirinha passa a usar o componente

- [x] 2.1 `LerCarteirinha` consome `CapturaWebcam`. Comportamento preservado: mesmos `data-testid` (`paciente-webcam-preview`, `paciente-webcam-capturar`), mesma largura de 1600, mesmo rótulo "Tirar foto e ler" e mesmo envio direto, sem conferência.

## 3. Webcam no registro de sessões

- [x] 3.1 Botão "Usar webcam" em `LancamentosPage`, ao lado de "Ler foto ou PDF do registro", só quando `webcamDisponivel()`.
- [x] 3.2 Habilitado pela mesma condição do outro botão (`prontoParaLer`) — provado por `a webcam so libera com guia e executante escolhidos`.
- [x] 3.3 A foto confirmada entra em `ler(arquivo)`, o mesmo caminho do arquivo escolhido. Provado por `a foto confirmada entra na mesma leitura do arquivo escolhido`, que confere também que o anexo vai com nome `.jpg` e `image/jpeg` — o que `LerRegistroSessoesRequest` valida (`mimes:pdf,jpg,jpeg,png`).
- [x] 3.4 Erro de permissão cai no `formError` da tela, apontando a escolha de arquivo.

## 4. Verificação

- [x] 4.1 `npx tsc -b`, `npm run lint` e `npm run build` no `web/`.
- [x] 4.2 Suíte e2e verde: 24 testes.
- [x] 4.3 `web/tests/e2e/lancamento-webcam.spec.ts`, com a câmera falsa do Chromium (`--use-fake-device-for-media-stream` para o vídeo sintético, `--use-fake-ui-for-media-stream` para conceder a permissão sem diálogo). Três cenários: o botão só libera com guia e executante; capturar congela para conferência e **não** chama a IA; "Tirar outra" volta ao vivo e capturável; a foto confirmada dispara `POST /guias/{id}/lancamentos/ler-registro` uma única vez, com o JPEG anexado.

## 5. Em aberto

- [x] 5.1 Conferir no navegador uma captura real, com webcam de verdade e a chave OpenAI de produção. O vídeo falso do Chromium é um padrão colorido, não uma folha de sessões: o e2e prova o caminho, não a qualidade da extração a partir de foto de webcam (foco, reflexo, distância, letra manuscrita). Mesma limitação da tarefa 3.4 da change `importar-sessoes-por-ia`.

  **Conferido em 16/09/2026**, em produção. A leitura a partir de foto real foi aprovada pelo responsável do produto. Os parâmetros ficaram como estão — 2400px de largura e JPEG 0.92, em `components/ui/CapturaWebcam.tsx` — e são o primeiro lugar a mexer se a qualidade cair com outra câmera ou outra iluminação.
