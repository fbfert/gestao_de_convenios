## Why

Duas coisas atrapalhavam o uso diário das telas de listagem.

**Espaço.** Cada tela abria com três cartões de altura cheia para mostrar três dígitos — Total, Ativos, Inativos —, mais um bloco de texto descrevendo o que a tabela logo abaixo já mostrava: *"Lista — Pesquisa por nome ou carteirinha, com convênio e status visíveis na mesma tela."* Boa parte desses textos era nota de desenvolvimento vazada para a interface: "vem da API real do tenant", "passa por translateStatus()". Somados, empurravam a listagem para baixo da dobra.

**Exclusão.** Apagar um anexo pedia apenas um `window.confirm`. Quem faz isso dez vezes por dia aperta "OK" sem ler, e anexo apagado não volta: é documento do paciente e, quando a guia já foi enviada, a evidência do envio.

## What Changes

- Contadores viram uma linha compacta de pílulas, com rótulo e valor lado a lado.
- Textos explicativos de listagem e de formulário são removidos; mensagens de estado e garantias permanecem.
- Exclusão de anexo passa a exigir duas etapas: o diálogo e a palavra `EXCLUIR` digitada.

## Capabilities

### New Capabilities

- `densidade-das-telas`: contadores compactos e ausência de texto descritivo redundante.
- `confirmacao-de-exclusao`: confirmação em duas etapas para exclusão sem volta.

### Modified Capabilities

- Nenhuma.

## Non-goals

- Comprimir os cartões de Analíticos e do detalhe do lote: eles carregam uma segunda linha de detalhe que não cabe na pílula, e não são o padrão Total/Ativos/Inativos.
- Aplicar a confirmação em duas etapas a toda exclusão do sistema: por ora vale para anexo, que é o caso irreversível relatado.

## Impact

- Frontend: `Indicadores` e `ConfirmarExclusao` novos; dez telas de listagem e dezenove telas com texto descritivo.
- Sem mudança de API.
