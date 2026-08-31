# Entregas de 31/08/2026 — consulta de status Unimed, Finalizar de guia aprovada, alerta de negadas

## 1. Automação Unimed — consulta de status acha guias que saíram de "Exames em aberto"

Seis guias reais (Laura de Faveri: `50144091073`, `50144090977`, `50144090373`; Miguel Ribeiro
Machado: `50144046479`, `50144046035`, `50144044209`) apareciam como "Em análise" no gescon, mas a
consulta de status devolvia `GUIA_NOT_FOUND` ao vivo no portal. Causa: `abrirGuiaPorFiltro`
("Exames em aberto"/"Exames finalizados") nunca lista uma guia depois que ela sai do estado
pendente — Negado/Cancelado/Autorizado somem das duas telas. Isso também explica a anomalia da
guia `50144044209` registrada em 26/08 como "não localizável por motivo desconhecido" — não era
anomalia, era essa mesma lacuna de escopo de busca.

**Correção:** usuário encontrou manualmente o caminho alternativo no portal (Novo Exame →
"clique aqui" → "Cadastre o beneficiário..." → carteirinha → Verificar → Atualizar → tela
"Localizar Guia", campo `s_NR_GUIA`/`Button_Filtro` — nomes diferentes do
`s_nr_guia`/`Button_FIltro` de "Exames em aberto"). Essa tela mostra o histórico completo do
beneficiário, incluindo guias em estado terminal. Implementado como fallback em
`consultarStatusGuia` (`worker-unimed/src/operations/statusSenha.js`), reaproveitando
`abrirBeneficiario`/`preencherCarteirinha`/`atualizarCadastroSeNecessario` (as mesmas peças que
`gerarGuia` já usa), só que seguindo para "Localizar Guia" em vez de "Digitação de guia SP/SADT".
Situação lida pelo texto da linha (o portal mostra ícone **e** texto juntos, ex.:
`ico16estudo.gif` + `<span>Em estudo</span>`), com o nome do arquivo do ícone como fallback pros
casos raros sem texto.

**Bug relacionado corrigido:** `atualizarCadastroSeNecessario()` só esperava 500ms pelo botão
"Atualizar" — insuficiente pelo menos 1 vez a cada 3 tentativas seguidas ao vivo, travando na tela
"Cadastrar beneficiário externo sem cartão". Aumentado pra 3000ms.

**Verificado ao vivo** contra `rda.unimedsc.com.br`, uma sessão isolada por guia (mesmo padrão do
dispatch real em produção — um browser/login por `AutomacaoExecucao`) e depois via
`ConsultarStatusUnimedService::enviar()` → fila → worker real: as 6 guias resolveram (2x Negado,
1x Autorizado, 3x Em estudo/ainda pendente) e ficaram gravadas certas na tabela `guias`.

Commit: `5223582`. Ver `worker-unimed/src/operations/statusSenha.js`,
`worker-unimed/src/portal.js`.

## 2. Finalizar aceita guia já aprovada pela automação (abre ciclo de Antecipação)

Pergunta do usuário ("depois de Aprovada a guia, o que preciso fazer?") revelou uma lacuna real:
`GuiaService::finalizar()` só funcionava com `status = 'under_review'`. Guias aprovadas pela
automação Unimed (`status = 'approved'`) nunca passavam por ali — e sem passar por Finalizar,
`AntecipacaoService::abrirCiclo()` nunca rodava, então nenhum `Lancamento` de sessão tinha ciclo de
cota pra consumir.

**Correção:** `finalizar()` agora aceita `under_review` OU `approved`, usando
`senha`/`validade_senha` já existentes na guia como default quando não vêm no payload (guias
`approved` já têm os dois, capturados sozinhos por `CapturarSenhaValidadeUnimedService`).
`GuiaStatusActions.tsx` mostra o botão Finalizar nos dois casos e pré-preenche o formulário; o
botão Negar continua só em `under_review` (não faz sentido negar uma guia que a operadora já
aprovou).

Commit: `1f6cc2d`. Testes: `GuiaServiceTest`, `GuiasApiTest` (novos casos pro fluxo `approved`).

## 3. Alerta de guias negadas em /guias e no Dashboard

Guia negada ficava sem nenhum sinal ativo — só aparecia numa consulta manual. Novo componente
`GuiaAlertaNegacoes.tsx`, montado tanto em `/guias` quanto no Dashboard (gated por
`dashboard.guias`), cada um buscando direto `GET /guias?alerta_negacao_pendente=1` — um único
componente, uma única fonte de dado.

Clicar em "Ocultar" numa linha abre um modal com três botões, como pedido: **Nova Solicitação**
(oculta e navega pra `/solicitacoes/nova?paciente_id=...&convenio_id=...&especialidade_id=...&profissional_id=...`
— novo mecanismo de pré-preenchimento, a rota sempre abria em branco antes), **Já solicitei** e
**Pode ocultar** (só ocultam). Ocultar é permanente e vale pra qualquer usuário do tenant (novo
campo `alerta_negacao_ocultado_em`, timestamp) — não existe "ocultar só pra mim" em nenhum outro
lugar do gescon, então não foi criado um padrão novo aqui. Sem heurística de auto-ocultar: como não
existe vínculo no banco entre uma guia negada e a solicitação que a substitui, isso ficou fora de
escopo por decisão consciente (evita falso positivo/negativo).

Backend: `GuiaService::listar()` ganhou o filtro `alerta_negacao_pendente`; novo endpoint
`PATCH /guias/{guia}/ocultar-alerta-negacao`.

Commit: `4942c5d`. Testes: `GuiaServiceTest`, `GuiasApiTest`.

## 4. Rótulo "Aprovado" duplicado — approved vira "Autorizado"

Consequência direta do item 2: com `approved` e `finalized` agora sendo dois pontos reais e
distintos da mesma jornada (autorizado pela operadora → ciclo de Antecipação aberto), o fato de os
dois aparecerem como o mesmo texto "Aprovado" no filtro e no badge da guia virou confuso de
verdade. `approved` agora mostra **"Autorizado"** (mesma palavra que a própria Unimed usa no
portal — `mapPortalStatus()` em `worker-unimed/src/portal.js`); `finalized` continua "Aprovado".
Só o texto mudou — nenhuma lógica de status, cor do badge (`statusTone.ts` mantém `sucesso` pros
dois) ou filtro foi alterada.

Commit: `929b88a`.

## Verificação — como cada entrega foi validada

- **1**: probes `.mjs` descartáveis contra o portal real (removidos depois), depois via
  `ConsultarStatusUnimedService::enviar()` de ponta a ponta pras 6 guias reais.
- **2 e 3**: `run-tests.sh` (backend, sqlite isolado) + `tsc -b`/`oxlint`/`verificar-design-system.mjs`
  (frontend). O item 3 também foi verificado com um browser real (Playwright) contra a produção:
  usuário QA descartável, guia de teste criada/negada/ocultada via API, checado que o alerta
  aparece em `/guias` e `/dashboard`, que o clique em "Nova Solicitação" navega com os query params
  certos e que os 4 campos do formulário vêm pré-preenchidos com o rótulo certo — tudo limpo depois
  (ver `docs/` não guarda o script, era descartável; procedimento documentado na memória de sessão
  do assistente, `gescon-deploy-feedback`).
- **4**: typecheck/lint/design-system limpos; confirmado que o texto novo foi parar no bundle
  publicado (`grep` no JS servido em produção).

## Pendente

- Rótulo "Aprovado"/"Aprovado" era só metade do problema — a distinção `finalized` vs `approved`
  em si (dois status pro mesmo conceito de "operadora autorizou") não foi revisitada
  estruturalmente, só o texto exibido.
- `atualizarCadastroSeNecessario()` continua com uma janela pequena de instabilidade sob uso muito
  intenso e sequencial do mesmo login (reproduzido só num script de diagnóstico artificial que
  disparava 5-6 "Novo Exame" seguidos numa mesma sessão — não é o padrão real de dispatch em
  produção, que é um browser por guia). Baixa urgência.
