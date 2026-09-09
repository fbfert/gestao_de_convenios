# Tarefas

## 1. Modelo

- [x] 1.1 Trocar `Solicitacao::guia()` (`hasOne`) por `Solicitacao::guias()` (`hasMany`), com o
      comentário explicando por que a relação continua existindo.
- [x] 1.2 `SolicitacaoDocumento::estaTravado()` decide pelo conjunto (`guias()->exists()`), cobrindo
      guia de item e guia antiga sem item numa consulta só.

## 2. API

- [x] 2.1 Remover a chave `guia` de `SolicitacaoResource`.
- [x] 2.2 Remover os oito eager loads `guia.*` de `SolicitacaoService::listar()`.
- [x] 2.3 Remover `'guia'` e `'guia.*'` das seis cargas de `SolicitacaoController`.
- [x] 2.4 Remover `'guia'` das relações de `SolicitacaoDocumentoController` e trocar a checagem de
      bloqueio de remoção por `guias()->exists()`.
- [x] 2.5 `SolicitacaoService::sincronizarGuiaDaSolicitacao()` deixa de eleger uma guia arbitrária:
      usa a guia do item que ela mesma vincula, com recuo para a guia antiga sem item.

## 3. Front

- [x] 3.1 `SolicitacaoAnexos` decide o travamento só pelos itens.
- [x] 3.2 Remover `Solicitacao.guia` (e o import que ficou órfão) de `types.ts`.

## 4. Testes

- [x] 4.1 `SolicitacoesApiTest` passa a vincular a guia ao item e a conferir por `itens.0.guia`.
- [x] 4.2 Cobrir que uma guia sem item vinculado ainda trava a remoção do anexo do pedido.
- [x] 4.3 `php artisan test`, `npm run lint`, `npm run build` e `npm run test:e2e` verdes.
