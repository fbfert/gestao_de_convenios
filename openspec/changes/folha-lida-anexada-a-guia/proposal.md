## Why

Ao registrar as sessões a partir de uma folha lida (arquivo enviado ou captura da webcam), a folha não fica guardada na guia. A spec `importacao-de-sessoes` já manda guardar as folhas enviadas junto da confirmação ("Várias folhas de registro por guia"), e o servidor já faz isso — mas só com o arquivo do campo "PDF do registro de sessões", que a tela mostra apenas para a regional 0220.

O arquivo que acabou de ser lido é usado pela leitura e descartado pela tela. O servidor chega a gravá-lo em `registros-sessoes/`, mas sem vínculo com guia nem paciente. Resultado: fora da 0220, nenhuma guia recebe a folha, e a finalização na Unimed — que anexa as folhas da guia no portal — sai sem comprovante.

## What Changes

- A folha usada na leitura é enviada junto da confirmação das sessões e guardada na guia, na pasta do paciente, sem passo manual.
- Foto e captura da webcam são convertidas em PDF antes de guardar. O pedido é por PDF; a regional 0220 exige PDF; e é o formato que vai ao portal. Vale também para folha anexada depois, pela tela da guia.
- A confirmação passa a aceitar imagem (JPG/PNG) além de PDF no campo da folha.
- Na regional 0220, a folha lida já satisfaz a exigência; o campo avulso só aparece quando não houve leitura por arquivo (texto colado).

## Capabilities

### Modified Capabilities
- `importacao-de-sessoes`: a folha lida é anexada à guia ao confirmar; imagem vira PDF.

## Impact

**API**
- `ImportLancamentosTranscricaoRequest` — aceita `pdf,jpg,jpeg,png`
- `FolhasDeRegistroService` — converte imagem em PDF ao guardar
- `Support/ImagemParaPdf` — novo, sem dependência nova (usa `gd`, já instalado na imagem)

**Web**
- `features/lancamentos/LancamentosPage.tsx` — guarda o arquivo lido e o envia na confirmação

**Não faz parte**
- Recuperar as folhas das remessas já confirmadas: o arquivo lido ficou em `registros-sessoes/` sem vínculo com guia, e casá-lo depois exigiria adivinhação.
