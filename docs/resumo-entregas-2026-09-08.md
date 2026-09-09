# Entregas de 08/09/2026 — guias por item, número da operadora, coluna Info e a trava do banco de teste

Change do openspec: `solicitacao-guias-por-item-e-info`. O brief que originou o trabalho está em
`docs/brief-solicitacao-guias-por-item-e-info.md`.

## Contexto: o que a tela mostrava de errado

Desde a multi-especialidade cada `SolicitacaoItem` tem a sua guia, mas duas superfícies ficaram com
a suposição antiga de uma guia por solicitação. `Solicitacao::guia()` é um `hasOne` **sem
ordenação**: devolve uma guia qualquer da solicitação, variando com a ordem física das linhas — e
era dela que o modal de detalhes se alimentava. Na listagem, o pior: `Guia #{item.guia_id}` exibia o
identificador interno como se fosse o número da guia, então a atendente lia "#12" e ligava para a
operadora com um número que não existe lá.

## 1. Guia por item na API

`SolicitacaoResource` passou a expor, em cada item, um objeto `guia` com `id`, `numero_guia`,
`status` e — acréscimo ao brief — **`numero_operadora`**, já nulo quando o valor guardado é o de
preenchimento do convênio manual.

O brief mandava o front reconhecer o prefixo `GUIA-SOLICITACAO-`. Preferimos o backend decidir uma
vez: é a regra mais fácil de esquecer na terceira tela que precisar dela, e assim o front consome
`numero_operadora` e não tem como errar. O prefixo virou
`GuiaStatus::PREFIXO_NUMERO_PLACEHOLDER`, com dois auxiliares, e o
`SolicitacaoService::numeroGuiaDaSolicitacao()` passou a consumi-la.

Nenhum eager load novo: `itens.guia` já estava carregado nos sete pontos que servem a tela — um no
serviço (`listar()`) e seis no controller (`store`, `show`, `update`, `aprovar`, `negar` e
`updateStatus`). O brief falava em seis, todos no serviço; a conferência corrigiu o número e o
lugar.

`guia_id` saiu do payload e do tipo no mesmo commit, depois de os sete consumidores do front
migrarem para `item.guia`.

## 2. Modal com uma aba por item

`SolicitacaoGuiaModal` monta as abas a partir de `solicitacao.itens`, e não mais da relação legada.
Uma aba por item **inclusive sem guia** — ver que saíram 1 de 3 especialidades é a informação mais
valiosa da tela, e abas só para as guias existentes esconderiam exatamente o que exige ação. Rótulo
é especialidade mais número; item sem guia mostra "Aguardando geração da guia".

A busca do detalhe mora dentro da aba, não no componente pai: um pedido com seis especialidades não
deve disparar seis requisições ao abrir o modal.

Solicitação legada sem item nenhum mantém a mensagem antiga.

`Solicitacao::guia()` **não** foi removida — `SolicitacaoAnexos.tsx` ainda a usa para decidir se um
anexo pode ser excluído. Quando ela sair, caem oito eager loads da listagem
(`guia.paciente`, `guia.convenio`, `guia.profissional`, `guia.especialidade`,
`guia.solicitacaoItem.especialidade`, `guia.solicitacaoItem.profissional`, `guia.antecipacoes` e
`guia.conciliacoes`). Hoje toda página de Solicitações carrega antecipações e conciliações de uma
guia arbitrária para não exibir nenhuma delas. Vira change próprio, com ganho de desempenho de
graça.

## 3. Listagem: número, situação, data e rótulo

O identificador interno saiu. No lugar, os três estados tratados explicitamente: número da
operadora quando existe, "Guia gerada · sem nº da operadora" para o convênio manual e "Guia gerada ·
nº pendente" quando a guia nasceu antes da confirmação. Nos dois últimos o texto afirma que a guia
existe — a leitura errada mais cara aqui seria "a guia falhou", que levaria alguém a gerá-la de
novo.

O badge fixo "Guia gerada" virou a **situação real** da guia, traduzida por `translateStatus`, para
qualquer convênio e não só Unimed. O tom vem do `statusTone` das guias, que já cobre os
`historico_*` com neutro.

`solicitado_em` passou a linha secundária sob o nome do paciente: é o dado mais consultado da linha,
e exigir hover para o mais consultado é caro.

"Médico solicitante" virou "Médico" nos três lugares da listagem — cabeçalho, filtro e `data-rotulo`
do modo cartão. Os rótulos dos formulários seguem com a frase completa, que descreve o papel e não a
coluna.

## 4. Coluna Info

Quatro indicadores antes de Ações: datas, observações, anexos e CID. Sem consulta nova — tudo vem do
eager load que já existia, e há teste travando isso.

Ícone sem conteúdo **não some**: fica visível e apagado. Posição fixa é o que torna a coluna legível
de relance; ícone que aparece e some obriga a reler a linha. E, sem conteúdo, sai do caminho do
teclado (`aria-hidden`, não focável) — quatro gatilhos por linha, em quinze linhas, seriam sessenta
paradas de Tab entre a tabela e a paginação.

O ícone de CID é um estetoscópio, e **não** uma cruz vermelha: neste sistema vermelho significa
perigo ou negado, e uma cruz vermelha em toda linha faria o olho ler "algo errado aqui" em quinze
linhas saudáveis (ADR-23).

O tooltip do calendário traz as três datas. `solicitado_em` é a data do pedido médico e `created_at`
é quando alguém digitou no sistema; sem as duas, ninguém sabe há quanto tempo o pedido está parado
*dentro* do sistema.

Larguras da `table-fixed` redistribuídas para somar 100%: Itens 35→30, Médico 15→13, Info 7.

## 5. A trava que impede a suíte de apagar o banco

Descoberto no caminho, e o achado mais grave do dia: `npm run test:e2e` roda
`migrate:fresh --seed --env=testing --force`, e o Laravel só honra `--env=testing` se
`api/.env.testing` existir. **O arquivo não existia** — o `.gitignore` exclui `.env.*` em qualquer
diretório, então ele nunca chegaria a um clone. Numa máquina com a cópia da produção restaurada, uma
única execução da suíte teria apagado tudo. Num servidor, o mesmo.

Quatro camadas, todas com teste:

1. **`App\Support\GuardaDeBancoDestrutivo`** bloqueia `migrate:fresh`, `migrate:refresh`,
   `migrate:reset`, `migrate:rollback` e `db:wipe` quando o banco alvo não é descartável. A defesa
   olha o **alvo**, e não o ambiente: conferir `APP_ENV` não bastaria, porque o modo de falha
   original é justamente o ambiente não ser o que o comando pediu. Passam apenas SQLite em memória,
   nome terminado em `_e2e`/`_test`/`_testing`, e a válvula explícita `PERMITIR_RESET_DO_BANCO`.
2. **`php artisan db:conferir-alvo-de-teste`** roda antes do `migrate:fresh` no script, com uma
   mensagem que diz qual banco é, por que parou e como resolver.
3. **`api/.env.testing` versionado**, com exceção registrada no `.gitignore` e `APP_KEY` própria.
4. **Nove testes**, incluindo "`gestao_convenios_e2e_producao` não engana o sufixo".

Verificado de verdade: `migrate:fresh` contra o banco de desenvolvimento saiu com código 1 e as
2.294 guias continuaram lá.

## 6. Outros defeitos corrigidos no caminho

- **A suíte E2E apontava para a API errada.** Sem `web/.env.e2e` (também excluído pelo `.gitignore`),
  o `VITE_API_URL` ficava indefinido e o cliente caía no padrão `localhost:8000` — a API de
  desenvolvimento. A URL passou para o `env` do `webServer` no `playwright.config.ts`: sem segredo,
  sem arquivo a versionar, suíte autossuficiente.
- **Quatro migrations quebravam instalação nova em MySQL/MariaDB** com nomes de índice de 66 a 68
  caracteres, acima do limite de 64. Invisível na suíte, que roda em SQLite. Nomes explícitos e
  curtos nas quatro.
- **O `throttle:5,1` do login reprovava a própria suíte E2E** (oito logins em trinta segundos). O
  limite virou dado; produção segue em 5 por minuto e só o processo do Playwright sobe o teto. A
  primeira tentativa, ramificando por `environment('testing')`, estava errada: phpunit e E2E rodam
  os dois nesse ambiente, e afrouxar ali tornava inútil o `AuthApiTest`.
- **O phpunit também carrega `api/.env.testing`**, porque o `phpunit.xml` define `APP_ENV=testing`.
  Está documentado no topo daquele arquivo.
- **`PacienteSyncServiceTest` era uma bomba-relógio**, e não um defeito do serviço. Os fixtures
  remotos usam `updated_at` fixo em 05/09/2026, enquanto o paciente local era criado com o relógio
  real. `PacienteSyncService::pullUm` faz a edição local mais recente vencer, então, passado o
  calendário daquela data, o pull passou a IGNORAR em vez de atualizar. Quebrava só o cenário de
  match por CPF porque é o único em que o local já é conhecido nessa altura — nos outros o match é
  por nome e o local ainda é nulo. A classe passou a congelar o relógio em 01/09/2026 no `setUp`,
  **antes** de migrar e semear: com o seed dentro do congelamento, os pacientes semeados continuam
  fora do lote de push. `ProfissionalSyncServiceTest` usa a mesma data fixa, mas lá todo remoto é
  "sem especialidade" e nunca casa com um local, então a comparação não dispara — fica o registro do
  risco latente.

## 7. A etapa de anexos perdia o arquivo recém-enviado

Achado ao consertar a suíte, e o segundo defeito real do dia. `SolicitacaoAnexosStep` recebia a
solicitação como o **corpo do POST guardado num `useState`** da página — um retrato do instante da
criação, quando ainda não havia anexo nenhum. `useAnexarDocumento` invalida `['solicitacoes']`, mas
invalidação não alcança estado local: o upload subia (201), o botão voltava de "Enviando..." para
"Anexar arquivo" e o slot seguia dizendo **"Nenhum arquivo anexado"**. Quem estivesse cadastrando
concluiria que o envio falhou e mandaria o mesmo arquivo de novo.

A etapa passou a ler pela query (`useSolicitacao`), com o retrato do POST como valor inicial para
aparecer preenchida já no primeiro quadro. Conserta os dois caminhos de criação de uma vez — o
manual, em `SolicitacoesPage`, e o de leitura do pedido médico, em `LerPedidoMedicoPage`, que
montavam o mesmo componente com o mesmo retrato congelado.

## 8. A relação legada `Solicitacao::guia()` saiu

Change próprio: `remover-relacao-guia-legada`. Era o não-objetivo registrado na §5 do brief, adiado
porque `SolicitacaoAnexos.tsx` ainda a consumia.

`Solicitacao::guia()` virou `Solicitacao::guias()` — de `hasOne` para `hasMany`. A relação não sumiu
de propósito: a pergunta "esta solicitação já tem guia?" continua existindo, e um `hasMany` responde
isso sem eleger uma principal que não existe. Para exibir, a fonte é `itens[].guia`.

Caíram os oito eager loads que o brief tinha listado. Toda página de Solicitações carregava
antecipações e conciliações de uma guia arbitrária para não exibir nenhuma delas.

Dois defeitos apareceram ao puxar o fio:

- **`sincronizarGuiaDaSolicitacao` podia trocar a guia de item.** Ela lia `$solicitacao->guia`
  (arbitrária) e, três linhas abaixo, reescrevia o `solicitacao_item_id` dela para o do **primeiro**
  item — então a guia do terceiro item podia ser realocada para o primeiro. Passou a usar a guia do
  item que ela mesma vincula, com recuo para a guia antiga sem vínculo.
- **A trava de anexo tem um caso que só o `hasMany` cobre.** Guia anterior à multi-especialidade fica
  presa à solicitação, com `solicitacao_item_id` nulo. Se a checagem passasse a olhar só
  `itens.guia`, o anexo do pedido dessas solicitações antigas voltaria a ser removível — e ele é a
  evidência do que sustentou a autorização.
  `test_guia_antiga_sem_item_vinculado_ainda_trava_o_anexo_do_pedido` trava isso.

**BREAKING**: a chave `guia` saiu da resposta de `/api/solicitacoes` — index, show, store, update,
aprovar, negar e status. Quem precisa da guia lê `itens[].guia`.

## 9. Dashboard: Saúde e Novidades lado a lado

Novidades subiu para junto de Saúde, acima do card de guias. Os dois são cards curtos e de leitura,
não de ação; empilhados, empurravam a lista de guias para fora da primeira tela.

Flex, e não grade de duas colunas, porque **os dois cards somem sozinhos** — Saúde quando o tenant
não tem componente, Novidades quando não há publicação. Numa grade, o que sobrasse ficaria preso a
meia tela com um vazio ao lado. Com `empty:hidden` o invólucro sai do fluxo e o card restante volta a
ocupar a largura inteira. O `basis` só entra a partir de `lg`: em coluna ele valeria como altura, e
zeraria os dois.

## 10. Monitor externo (P0.3) — ensaio local, com a transição provada

Uptime Kuma no Docker desta máquina, monitor **HTTP** `Gescon API — /api/health` apontando para
`http://host.docker.internal:8000/api/health`, a cada **120 s**, aceitando **só 200** (a lista de
códigos veio com `200-299` por padrão e foi trocada por `200`).

O ciclo completo foi exercitado, e não apenas configurado:

1. Primeira verificação: **503**, monitor DOWN. O corpo dizia por quê —
   `{"db":"ok","fila":"ok","scheduler_ultima_rodada":null}`: o `schedule:work` nunca tinha rodado
   nesta máquina.
2. Com o scheduler de pé, o endpoint passou a **200** com carimbo
   (`"scheduler_ultima_rodada":"2026-09-08T20:21:00-03:00"`).
3. No ciclo seguinte o monitor virou **UP**, 273 ms. A barra de heartbeat guarda o vermelho seguido
   do verde.

Isso prova o par que o plano exige na F0: 200 com o cron vivo, 503 com o cron parado — e prova
também que o monitor lê o estado real, e não só a porta aberta.

**A F0 continua aberta**, e por duas razões que não são de código:

- O monitor aponta para a API de **desenvolvimento**. Vira produção quando a Fase 0/1 subir; troca só
  a URL.
- **Sem canal de notificação.** O Kuma mostra "Notificações: não disponível". WhatsApp e
  `suporte@xiax.com.br` exigem token/SMTP, que é credencial e não passa por aqui.

O segundo monitor "push" que o plano cita como opcional **não é necessário** neste desenho: o caso
que ele cobriria — "a API responde mas o cron morreu" — já é o 503, porque o próprio endpoint dobra a
liveness do scheduler dentro do status HTTP. Seria sinal duplicado.

Vale lembrar o que o plano exige e este ensaio não pode satisfazer: o Kuma tem que rodar **fora da
VPS do gescon**. Monitor na mesma máquina morre junto com o que monitora.

## Validação

| Comando | Resultado |
|---|---|
| `php artisan test` | 496 de 496 |
| `npm run lint` | contrato do design system OK |
| `npm run build` | compila |
| `npm run test:e2e` | 15 de 15 |
| `openspec validate solicitacao-guias-por-item-e-info --strict` | válido |
| `openspec validate remover-relacao-guia-legada --strict` | válido |

Os cenários de `mvp-flow.spec.ts` que falhavam vinham de dois lugares. A maior parte era
desalinhamento com a interface — o change `modal-busca-paciente-medico` trocou o seletor de paciente
por um modal, o CID virou obrigatório, "Aprovado" virou "Finalizado", a importação de sessões mudou
de rota. O de anexos era defeito de produto, e está na seção 7. Sobraram duas asserções que
estouravam os 5 segundos padrão do Playwright — conciliação e anexos — porque cada uma espera
mutação mais refetch de uma lista pesada, num `artisan serve` de processo único; as duas passaram a
usar a espera longa da suíte, e a de conciliação também aguarda o próprio PATCH, para que clique
perdido e refetch lento não falhem com a mesma mensagem.
