## 1. Backend (R8)

- [x] 1.1 `SolicitacaoResource`: no bloco de itens, expor `guia` com `id`, `numero_guia` e `status`, mantendo `guia_id` enquanto houver consumidor.
- [x] 1.2 Constante compartilhada para o prefixo `GUIA-SOLICITACAO-`, consumida pelo `SolicitacaoService` e exposta ao front.
- [x] 1.3 Conferir que `itens.guia` já está carregado nos sete pontos (um no serviço, seis no controller) — sem eager load novo.
- [x] 1.4 Teste de contagem de consultas da listagem, com `DB::listen`.

## 2. Modal com abas (R1)

- [x] 2.1 `SolicitacaoGuiaModal` monta abas a partir de `solicitacao.itens`, com `@headlessui/react`.
- [x] 2.2 Uma aba por item, inclusive sem guia, com o estado vazio "Aguardando geração da guia".
- [x] 2.3 Rótulo com especialidade e número da guia quando houver.
- [x] 2.4 Conteúdo com guia reaproveita `GuiaDetalheResumo`, sem alterá-lo.
- [x] 2.5 Número da guia como link para `/guias/{id}`.
- [x] 2.6 Solicitação sem itens mantém a mensagem atual.
- [x] 2.7 Parar de usar `solicitacao.guia` neste arquivo, sem remover a relação no backend.

## 3. Listagem (R2, R3, R5, R6, R7)

- [x] 3.1 Número da guia tratando os três estados.
- [x] 3.2 Badge com o status traduzido, para qualquer convênio, preservando `historico_*`.
- [x] 3.3 `solicitado_em` como linha secundária sob o paciente.
- [x] 3.4 "Médico solicitante" para "Médico" no cabeçalho, no `data-rotulo` e no filtro.
- [x] 3.5 Redistribuir as larguras da tabela deixando espaço para a coluna Info.

## 4. Coluna Info (R4)

- [x] 4.1 Quatro indicadores com `Tooltip`: datas, observações, anexos e CID.
- [x] 4.2 Indicador sem conteúdo fica visível, apagado, `aria-hidden` e não focável.
- [x] 4.3 Ícone de CID neutro, com token — nunca cruz vermelha.
- [x] 4.4 `data-rotulo="Info"` para o modo cartão.
- [x] 4.5 Verificar em 390px que o painel não causa rolagem horizontal.

## 5. Testes

- [x] 5.1 Playwright: 2 itens com guia resultam em 2 abas.
- [x] 5.2 Playwright: 3 itens com 1 guia resultam em 3 abas, duas vazias.
- [x] 5.3 Playwright: o número da guia navega para o detalhe.
- [x] 5.4 Convênio manual não exibe o valor de preenchimento como número.
- [x] 5.5 Guia sem número exibe pendente, nunca o identificador interno.

## 6. Validação

- [x] 6.1 `openspec validate solicitacao-guias-por-item-e-info --type change --strict`.
- [x] 6.2 `php artisan test`, `npm run lint` e `npm run test:e2e`.
