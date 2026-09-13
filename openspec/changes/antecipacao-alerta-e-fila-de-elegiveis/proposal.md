## Why

A antecipação foi reformulada em produção (commits `7fd6020`, `6985efe`, `c4d2b1d`, `81acc08`) e **não tem spec nenhuma**. Deixou de ser um "balde de cota" — uma tabela `antecipacoes` com `qtd_autorizada`/`qtd_utilizada`/`ciclo` que os lançamentos consumiam — e virou duas coisas separadas: um **alerta configurável** que avisa quando uma guia chegou na data de renovar, e uma **fila de elegíveis** onde alguém decide gerar ou dispensar. A contagem de sessões virou conta ao vivo contra a guia, sem tabela intermediária.

Esta change é retroativa: documenta o que já está no ar, para que o comportamento pare de existir só no código.

**Conflito registrado, conforme `AGENTS.md`.** A única descrição escrita da antecipação está na change arquivada `2026-07-18-fluxo-operacional-convenio`, e ela descreve o modelo antigo:

> "a continuidade SHALL ocorrer por antecipações que definem a quantidade de sessões liberadas por período"

Isso contradiz o código atual, onde nenhuma antecipação define quantidade de sessão alguma. Agravante: essa change foi arquivada mas **nunca foi promovida** a `openspec/specs/` — das 6 arquivadas, é a única sem spec correspondente. Ou seja, o texto que contradiz o código não é sequer uma spec aprovada hoje; é um documento morto. Esta change assume a descrição da antecipação e torna a passagem da arquivada obsoleta.

## What Changes

- **BREAKING** (já aplicado em produção): a tabela `antecipacoes` do modelo antigo, com `qtd_autorizada`/`qtd_utilizada`/`ciclo_inicio`/`ciclo_fim`/`status open|closed`, foi derrubada junto com `antecipacao_import_lotes` e `antecipacao_import_linhas`. `lancamentos.antecipacao_id` virou `lancamentos.guia_id`.
- **BREAKING**: a importação de antecipações por planilha deixou de existir (`AntecipacaoImportService`, `ImportarAntecipacoesPage`).
- Data-alvo da antecipação passa a ser **calculada** a partir de dias + campo de referência, com precedência: override da guia → override do convênio → padrão global do tenant. Regra de convênio como dado configurável, nunca hardcoded.
- Nova regra de alerta `antecipacao.devida` na Central de Alertas, com nível base amarelo e escalada para vermelho após N dias de atraso. **Nunca gera nada sozinha** — só avisa.
- Nova fila de elegíveis agrupada por solicitação, e histórico do que foi gerado ou dispensado.
- Gerar a antecipação cria itens novos **encadeados por renovação na mesma solicitação** (`renovacao_de_item_id`), não uma solicitação nova.
- Cota de sessões deixa de existir como dado: a disponibilidade passa a ser `COALESCE(sessoes_autorizadas, sessoes_solicitadas, 0)` menos os lançamentos já feitos, calculada na hora.

## Capabilities

### New Capabilities
- `antecipacao`: quando uma guia fica elegível para antecipação, como o alerta avisa, e como o operador gera ou dispensa a renovação a partir da fila.

### Modified Capabilities
<!-- Nenhuma. A descrição conflitante vive em `openspec/changes/archive/2026-07-18-fluxo-operacional-convenio/`, que nunca foi promovida a `openspec/specs/` — não há spec aprovada a modificar. O conflito está registrado na seção Why. -->

## Impact

**API**
- `app/Models/Antecipacao.php`, `app/Models/Guia.php` (`elegiveisParaAntecipacao`, `countElegiveisParaAntecipacao`, `antecipacaoDataAlvo`)
- `app/Services/AntecipacaoService.php`, `app/Services/Alertas/Regras/AntecipacaoDevida.php`
- `app/Http/Controllers/AntecipacaoController.php`, `GuiaController::ocultarAlertaAntecipacao`
- Rotas `GET /antecipacoes/elegiveis`, `GET|POST /antecipacoes`, `POST /antecipacoes/ignorar`, `PATCH|DELETE /antecipacoes/{antecipacao}`, `PATCH /guias/{guia}/ocultar-alerta-antecipacao`
- Permissões `antecipacoes.view` e `antecipacoes.manage`
- Configuração: `antecipacao_dias` e `antecipacao_referencia` em `configuracoes_globais` e o override em `convenios`; `antecipacao_data_alvo`, `antecipacao_proxima_em` e `alerta_antecipacao_ocultado_em` em `guias`

**Web**
- `features/antecipacoes/` (`AntecipacoesPage`, `SelecionarItensAntecipacaoModal`, `SelecionarSolicitacaoModal`)
- Card de Guias no dashboard e Saúde do sistema (contagem de elegíveis)

**Removido**
- `AntecipacaoImportService`, `AntecipacaoImportLote`, `AntecipacaoEditarPage`, `ImportarAntecipacoesPage`, `useAntecipacoesImport`

**Não faz parte desta change**
- Rever a change arquivada `2026-07-18-fluxo-operacional-convenio` ou promovê-la a spec.
- Os outros pontos do fluxo operacional que aquela change descrevia (agendamento, conciliação, repasse).
