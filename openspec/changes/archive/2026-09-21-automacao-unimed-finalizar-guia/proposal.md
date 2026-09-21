## Why

Finalizar uma guia na Unimed é hoje trabalho manual de portal: achar a guia, conferir regime e tipo de atendimento, digitar até dez datas de sessão uma a uma, anexar a folha assinada e clicar em Gravar e Finalizar. É repetitivo, é o último passo de todo ciclo de guia e é exatamente o tipo de tarefa que o worker Unimed já faz para gerar guia e consultar senha. O commit `0a0de73` (21/09/2026) fechou a lacuna anterior — Finalizar agora exige sessão registrada — e deixou escrito que a automação da finalização ficava "para depois". Este é o depois.

Automatizar esse passo só é seguro se as sessões que vão para o portal já estiverem corretas. O portal exige pelo menos 50 minutos entre uma sessão e a seguinte, e hoje nada no Gescon impede gravar duas sessões no mesmo horário — nem dentro da mesma guia, nem entre guias diferentes do mesmo paciente. Sem essa trava, a automação levaria o erro até a operadora, onde ele custa muito mais caro para desfazer.

## What Changes

**Automação de finalização na Unimed**

- Nova operação `finalizar_guia` no worker Unimed: busca a guia pelo número, abre a execução, fixa regime `01 - Ambulatorial` e tipo `03 - Outras Terapias`, preenche `dt_serie_1..N` com as sessões registradas no Gescon em ordem cronológica, anexa as folhas de registro e conclui com Gravar e Finalizar.
- Botão "Finalizar na Unimed" no grupo por guia da tela de Sessões, no lugar do Finalizar manual quando o convênio for `unimed_rda`. Convênio manual mantém o Finalizar de hoje.
- **BREAKING** (comportamento): guia de convênio `unimed_rda` só passa a `finalized` quando a operação concluir na operadora. O Finalizar manual deixa de estar disponível para esses convênios.
- Modo simulação por configuração global: o worker preenche tudo, anexa e para antes de Gravar e Finalizar, guardando evidência. Serve para a primeira homologação em produção, já que não existe ambiente de teste da Unimed.
- Divergências de quantidade não bloqueiam sozinhas — param e devolvem a decisão ao operador: menos sessões que o autorizado pede confirmação de finalizar com menos; mais sessões que o autorizado pede escolha entre enviar o limite autorizado ou revisar.

**Regras de agenda no registro de sessões**

- Intervalo mínimo de 50 minutos entre os *inícios* de duas sessões do mesmo paciente.
- Limite diário por especialidade: 8 sessões quando a especialidade for ABA (identificada pelo nome), 1 quando não for.
- Verificação contra as sessões `completed` já gravadas em **outras guias** do mesmo paciente, não só contra as do lote sendo confirmado.
- Violação é bloqueio: a confirmação só passa depois de corrigida. Sem escape por justificativa — diferente da divergência de paciente, aqui não há caso legítimo a preservar.
- Choque de agenda do **profissional** (mesmo executante em dois pacientes no mesmo horário) é aviso, não bloqueio.
- Novo card de alerta no grupo Guias do dashboard para guias com sessões em conflito.
- Vale para sessões novas. Não há varredura retroativa do que já está gravado.

**Folhas de registro múltiplas por guia**

- Uma guia passa a aceitar N folhas de registro de sessões, não uma só: guia de dez sessões costuma vir em duas folhas impressas, preenchidas em partes.
- As folhas entram no mesmo import (vários arquivos de uma vez) ou depois, anexadas à guia.
- A finalização envia todas as folhas da guia, uma por vez no popup de anexos.
- Guia da regional 0220 sem nenhuma folha avisa e permite finalizar mesmo assim, com confirmação explícita.

**Não faz parte desta change**

- Finalização automática em qualquer convênio que não seja `unimed_rda`.
- Disparo automático da finalização (por cota atingida, por agendamento ou em lote) — só o botão, um a um.
- Varredura ou correção das sessões já gravadas antes desta change.
- Regime e tipo de atendimento configuráveis por convênio ou especialidade: ficam fixos para a Unimed.

## Capabilities

### New Capabilities

- `automacao-unimed-finalizar-guia`: operação de finalização de guia no portal da Unimed — disparo pelo operador, preenchimento da execução, envio das folhas, modo simulação, decisões de divergência de quantidade e efeito no status da guia.
- `sessoes-regras-de-agenda`: intervalo mínimo entre sessões, limite diário por especialidade, verificação cruzada entre guias do mesmo paciente, aviso de choque do profissional e alerta no dashboard.

### Modified Capabilities

- `importacao-de-sessoes`: a confirmação passa a recusar sessões que violem as regras de agenda, e a folha de registro deixa de ser um arquivo único por import para ser N folhas por guia, anexáveis também depois.
- `guia-detail`: as ações de status deixam de tratar Finalizar como ação uniforme — para convênio `unimed_rda` a finalização passa pela operadora.

## Impact

**Código**

- `worker-unimed/src/operations/finalizarGuia.js` (novo), registrado em `worker-unimed/src/server.js` ao lado de `gerar_guia`, `consultar_status` e `confirmar_guia_incerta`.
- `api/app/Services/Automation/FinalizarGuiaUnimedService.php` (novo), no padrão de `GerarGuiaUnimedService`; `UnimedWorkerClient`, `FakeUnimedWorkerClient` e `AutomationErrorCatalog` ganham a operação.
- `api/app/Services/GuiaService.php`: `finalizar()` passa a distinguir convênio com automação de convênio manual.
- `api/app/Services/LancamentoService.php` e `LancamentoTranscricaoService.php`: validação das regras de agenda antes de gravar.
- `api/app/Http/Controllers/LancamentoController.php`: aceita N arquivos em `pdf_registro_sessoes`; `guardarRegistroDeSessoes` passa a gravar vários.
- `api/app/Services/DashboardGuiasCardService.php`: card de sessões em conflito.
- `web/src/features/lancamentos/LancamentosPage.tsx` e `web/src/features/guias/FinalizarGuiaButton.tsx`: botão condicionado ao convênio, acompanhamento da execução e diálogos de decisão.

**Dados**

- `automacao_execucoes` ganha a operação `finalizar_guia` — sem coluna nova, `payload`/`resultado` já são JSON.
- `paciente_arquivos` já guarda `metadata.guia_id`; nada muda no schema para suportar N folhas.
- Configuração global nova para o modo simulação da finalização.

**Conflito registrado entre spec e código**

`openspec/specs/guia-detail/spec.md` (requisito "Ações de status em telas de guia") ainda diz que finalizar e negar ficam disponíveis na lista e no detalhe de guias. Desde o commit `0a0de73` o Finalizar saiu dessas telas e foi para a tela de Sessões, e um Aprovar manual tomou o lugar dele ao lado do Negar. A spec está desatualizada em relação ao código **antes** desta change; o delta de `guia-detail` corrige as duas coisas de uma vez.
