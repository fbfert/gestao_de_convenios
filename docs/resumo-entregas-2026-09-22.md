# Entregas de 22/09/2026 — três bugs de captura de dados na automação Unimed

Disparado pelo usuário conferindo a Guia 50144652656 (autorizada, com senha e validade, mas sem
número de sessões). A partir daí, três bugs distintos na automação Unimed foram achados e corrigidos
no mesmo tema — dado que o portal mostra na tela e o worker lê, mas descarta ou nunca chega a
procurar. Nenhum deles é do tipo "timeout curto demais num clique" que domina o histórico anterior
(ver `docs/automacao-unimed/`); são gaps de cobertura em código que parecia completo.

## 1. Sessões descartadas na captura de senha/validade — commit `df4a47e`

A tela de execução do SP/SADT (a mesma que `consultarStatusGuia` já lê pra pegar sessões) também é
usada por `capturarAutorizacaoGuia`, só que essa segunda operação só devolvia `senha`/`validade_senha`
no resultado — `lerDadosExecucaoGuia` já lia `QT_SOLIC_1`/`QT_AUTORIZADA_1` da tela, e o valor ia pro
lixo antes de chegar no backend. `CapturarSenhaValidadeUnimedService::aplicarResultado` também só
persistia os dois campos de senha, mesmo que o worker mandasse sessões.

Consequência prática: uma guia que virasse `approved` e tivesse a senha capturada só por esse segundo
fluxo ficava com `sessoes_solicitadas`/`sessoes_autorizadas` em 0 **pra sempre** —
`ConsultarStatusUnimedService::avaliar()` exclui guias `approved` da reconsulta de status, e
`CapturarSenhaValidadeUnimedService::avaliar()` exclui guias que já têm senha+validade. Sem as duas
travas relaxarem ao mesmo tempo, nenhuma automação nova ia revisitar o dado.

Corrigido nos dois lados (worker repassa, backend persiste). Testado (9/9 worker, 31/31 backend) e
**verificado ao vivo**: guia 50144652656 reprocessada manualmente voltou com 9 de 10 sessões
autorizadas.

**Backfill.** Sete guias já estavam travadas nesse estado desde antes do fix — reprocessadas na mão
(via `AutomacaoService::enfileirar()` direto, contornando `avaliar()`, encadeando na execução anterior
como `parent` pra gerar idempotency key nova):

| Guia | Paciente |
|---|---|
| 50144090373, 50144090977, 50144091073, 50144421026, 50144421119 | Miguel Ribeiro Machado |
| 50144481424, 50144481580 | Elloa Sipriano de Liz |

## 2. Botão manual "Buscar sessões" — commit `f0baa40`

Pra guias que ainda ficarem travadas nesse mesmo estado no futuro (aprovada + senha/validade
capturadas + sessões zeradas), sem depender de reprocessamento manual via tinker.
`CapturarSenhaValidadeUnimedService::enviarRecuperacaoSessoes()` é o espelho de `enviar()` com
elegibilidade invertida — exige senha/validade já capturadas em vez de ausentes — encadeando na última
execução como `parent` pelo mesmo motivo do backfill acima. Rota
`POST /guias/{guia}/recuperar-sessoes-unimed`, botão nas mesmas colunas onde já existe "Buscar
Senha"/"Buscar Validade" (`GuiasPage.tsx`) e na tela de detalhe (`GuiaDetalhePage.tsx`).

## 3. Postback sem espera em `selecionarProfissionalExecutante` — commit `12dd22b`

Achado investigando a execução #933 (item 2612, Miguel Velasco Masieiro): Finalizar foi recusado pelo
portal com "O valor do campo Liminar judicial é obrigatório", apesar de existir reforço defensivo
específico pra esse campo (achado em 26/08, ver `gerarGuia.js:349-355`). `selecionarProfissionalExecutante`
era o único ponto do fluxo de `gerar_guia` que troca um `<select>` da página sem esperar nenhum
postback assentar depois — todos os outros pontos que disparam esse tipo de postback assíncrono
(contratado, prestador, procedimento) já têm espera explícita, com o mesmo bug documentado em
comentário ali perto. Corrigido com um `waitProcessing(page)` extra, seguindo o padrão já usado no
resto do arquivo.

**Sem confirmação ao vivo de causa raiz.** `confirmar_guia_incerta` (execução #987) achou que a guia
do item 2612 tinha sido criada de verdade no clique original (número 50145083066, Autorizada, senha e
sessões 10/10) — o worker só leu a página errada depois do Finalizar e reportou "incerto" por engano.
Isso significa que a hipótese de causa (postback do executante apagando o Liminar Judicial) **não foi
testada numa geração nova do zero**. O fix ficou no ar por ser de baixo risco e seguir um padrão já
testado, mas se o mesmo erro voltar a aparecer, a causa real é outra — provavelmente do lado da
leitura da confirmação, não do preenchimento.

## 4. `NOT_FOUND_IN_OPEN_EXAMS` também via fallback de cadastro — commit `79447e4`

Achado na guia 50144774682 (Miguel Odair Vieira da Silva): `capturarAutorizacaoGuia` só sabia procurar
a guia pela busca direta em "Exames em aberto" (`abrirGuiaPorFiltro`). Guias que saem dessa lista mas
continuam acháveis via cadastro do beneficiário → Localizar Guia (mesmo caminho que
`consultarStatusGuia` já usa pra status, desde o incidente de 31/08 com guias Negadas) sempre falhavam
com `NOT_FOUND_IN_OPEN_EXAMS`, mesmo com senha/validade/sessões disponíveis — só que numa tela
diferente (`nova.do`), que é **só leitura**: sem os `<input name="NR_SENHA">` de sempre, o dado
aparece como texto solto ao lado do rótulo (`Senha de autorização:`, `#CampoValidadeSenha`, `Qt.
Solic.`/`Qt. Autoriz.` na tabela `#tlinhas`).

**Metodologia:** antes de tocar em código, um script de exploração só-leitura
(`worker-unimed/scripts/explorar-localizar-guia.js`) confirmou ao vivo a estrutura real dessa tela
pra guia 50144774682 — sem isso, a estrutura (rótulo + `<td>` vizinho, não formulário) só seria
descoberta por tentativa e erro em produção. Depois do fix, outro script
(`worker-unimed/scripts/testar-fallback-captura.js`) validou o resultado contra o portal de produção
antes do deploy: senha `2603332006`, validade `14/11/2026`, sessões 10/10 — batendo exatamente com o
que a tela mostra. Só depois disso o deploy rodou, e a guia foi confirmada de ponta a ponta pelo
Laravel.

**19 guias** continuam com esse padrão (falha permanente, não transitória — distinto das falhas que se
resolviam sozinhas numa tentativa seguinte). Ficam elegíveis pro reprocessamento automático normal a
partir deste deploy, sem precisar de reenvio manual.

## Efeito colateral: disjuntor pausado por um redeploy no meio de uma execução

O segundo redeploy do dia (para o fix #3) recriou o `gescon-worker` enquanto uma execução `gerar_guia`
estava em andamento (item 2601) — a conexão caiu, `WORKER_INTERNAL_FATAL`, e o disjuntor
(`UnimedCircuitBreakerService`) pausou a credencial Unimed do tenant inteiro como medida de segurança
(mesmo mecanismo do runbook de pausa dominical, ver `docs/automacao-unimed/08-runbook-operacional.md`).
Percebido e revertido na hora (reativação manual, mesmo efeito do endpoint
`POST /configuracoes/unimed/reativar`); item 2601 voltou pra `pending` sem guia duplicada. Nos
redeploys seguintes, passou a esperar zero execuções ativas (`AutomacaoExecucao::whereIn('status',
['queued','running'])`) antes de recriar o `gescon-worker`.

## Backup manual fora do agendamento

Antes de mexer nas 7 guias do item 1, um backup avulso (banco + storage + segredos) foi gerado em
`/backup/gescon/2026-09-22_manual-1324/`, numa pasta própria com timestamp — sem sobrescrever o
backup automático das 00:52 nem nenhum outro dia. Upload pro Drive precisou ser feito manualmente pelo
usuário (o classificador de segurança do ambiente bloqueia `rclone copy` quando disparado pelo
agente).

## Validação

| Suíte | Resultado |
|---|---|
| `node --test` (worker, suite completa) | 65/65 |
| `php vendor/bin/phpunit` (arquivos Unimed relacionados) | 45/45 |
| Live, portal de produção | 3 guias confirmadas ponta a ponta (50144652656, 50144774682, e a recuperação manual de 2612 via `confirmar_guia_incerta`) |

## Pendências

- **19 guias** em `NOT_FOUND_IN_OPEN_EXAMS` aguardando o próximo ciclo automático (lista completa
  consultável via o filtro usado nesta sessão: execuções `capture_authorization_data_batch` com esse
  erro, cujo `guia_id` ainda tem `senha = '-'` e `validade_senha` nula).
- **Causa raiz do "Liminar judicial" (item 3) não confirmada ao vivo.** Se reaparecer, investigar a
  leitura da confirmação pós-Finalizar, não o preenchimento.
- **Upload do backup manual pro Drive** depende de ação do usuário enquanto o bloqueio do classificador
  de segurança não for ajustado (permissão Bash pra `rclone copy`, se desejado).
