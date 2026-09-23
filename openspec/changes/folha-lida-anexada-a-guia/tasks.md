## 1. API

- [x] 1.1 `ImagemParaPdf`: JPG/PNG vira PDF de uma página, respeitando a orientação EXIF
- [x] 1.2 `FolhasDeRegistroService::guardar` converte imagem antes de gravar
- [x] 1.3 `ImportLancamentosTranscricaoRequest` aceita `pdf,jpg,jpeg,png`
- [x] 1.4 Testes: confirmação com imagem guarda PDF; confirmação recusada não guarda

## 2. Web

- [x] 2.1 Guardar o arquivo lido e enviá-lo na confirmação, junto do campo avulso
- [x] 2.2 Mostrar que a folha lida será anexada; campo avulso da 0220 só sem folha lida
- [x] 2.3 E2E: ler pela webcam, confirmar e ver a folha na guia
