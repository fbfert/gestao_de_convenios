## 1. API

- [x] 1.1 `ImagemParaPdf`: JPG/PNG vira PDF de uma página, respeitando a orientação EXIF
- [x] 1.2 `FolhasDeRegistroService::guardar` converte imagem antes de gravar
- [x] 1.3 `ImportLancamentosTranscricaoRequest` aceita `pdf,jpg,jpeg,png`
- [x] 1.4 Testes: confirmação com imagem guarda PDF; confirmação recusada não guarda

## 2. Web

- [x] 2.1 Guardar o arquivo lido e enviá-lo na confirmação, junto do campo avulso
- [x] 2.2 Mostrar que a folha lida será anexada; campo avulso da 0220 só sem folha lida
- [x] 2.3 E2E: ler pela webcam, confirmar e ver a folha na guia

## 3. Pasta do paciente

- [x] 3.1 Remoção pela pasta recusa folha de guia finalizada — mesma regra da remoção pela guia
- [x] 3.2 Pasta mostra o número da guia em cada folha e esconde "Remover" quando travada
- [x] 3.3 Testes da API para os dois casos de remoção e para o número da guia na listagem

## 4. Anexar à mão no registro

- [x] 4.1 Campo "Anexar folhas" sempre visível, com vários arquivos e botão de retirar
- [x] 4.2 Regional 0220 aceita a folha lida ou uma anexada à mão; limite de 10 no total
- [x] 4.3 E2E: folha lida + folha anexada à mão chegam as duas na guia
