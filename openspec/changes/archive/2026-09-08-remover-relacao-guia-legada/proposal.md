# Remover a relação legada `Solicitacao::guia()`

## Why

`Solicitacao::guia()` é um `hasOne` **sem ordenação**: devolve uma guia qualquer da solicitação,
variando com a ordem física das linhas. Desde a multi-especialidade cada `SolicitacaoItem` tem a sua
guia, então a relação não tem mais um significado definido — e ela ainda decide comportamento em
dois lugares: o que a API expõe e se um anexo pode ser removido.

O custo é medido, não estimado. Para exibir uma guia arbitrária, a listagem de Solicitações carrega
oito relações por página:

```
guia.paciente, guia.convenio, guia.profissional, guia.especialidade,
guia.solicitacaoItem.especialidade, guia.solicitacaoItem.profissional,
guia.antecipacoes, guia.conciliacoes
```

Antecipações e conciliações de uma guia arbitrária são carregadas em toda página de Solicitações
para não serem exibidas em lugar nenhum.

O change `solicitacao-guias-por-item-e-info` já migrou a interface para `itens[].guia`. Sobrou um
consumidor no front (`SolicitacaoAnexos`) e os usos de backend.

## What Changes

- `Solicitacao::guia()` (`hasOne`) vira `Solicitacao::guias()` (`hasMany`). A relação não some: as
  checagens de "esta solicitação já tem guia?" precisam dela, e um `hasMany` responde isso sem
  fingir que existe uma guia principal.
- `SolicitacaoResource` deixa de expor a chave `guia`. O payload passa a ser inteiramente por item.
- Caem os oito eager loads da listagem e o `'guia'` das seis outras cargas.
- `SolicitacaoAnexos` passa a decidir o travamento só pelos itens.
- `Solicitacao.guia` sai do tipo do front.

**BREAKING**: a chave `guia` desaparece da resposta de `/api/solicitacoes` (index, show, store,
update, aprovar, negar, status). Quem precisa da guia lê `itens[].guia`.

## Impact

- `api/app/Models/Solicitacao.php`, `SolicitacaoDocumento.php`
- `api/app/Http/Resources/SolicitacaoResource.php`
- `api/app/Http/Controllers/SolicitacaoController.php`, `SolicitacaoDocumentoController.php`
- `api/app/Services/SolicitacaoService.php`
- `web/src/features/solicitacoes/SolicitacaoAnexos.tsx`, `types.ts`
- `api/tests/Feature/SolicitacoesApiTest.php`
