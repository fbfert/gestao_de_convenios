# Entregas de 15/09/2026 — pasta do paciente e credenciais de automação por convênio

Duas frentes independentes. A primeira fechou uma tela e um documento que o sistema exigia e jogava
fora; a segunda implementou a change `credenciais-por-convenio`, que existe por causa do incidente
de 14/09.

## 1. Pasta do paciente em tela própria (`cb75e1b`, `a187f9a`)

Clicar no nome do paciente em `/pacientes` abria um drawer `max-w-xl` sem rota. Cabiam o cadastro e
os anexos; não cabia nada do histórico, e três das quatro telas que o teriam (`/solicitacoes`,
`/lancamentos`, antecipações) nem oferecem filtro por paciente.

`/pacientes/{id}` passou a reunir tudo: dados cadastrais no cabeçalho e cinco seções — solicitações,
guias, sessões, antecipações e arquivos —, todas recolhidas ao abrir com a contagem no cabeçalho. Um
endpoint só (`GET /pacientes/{paciente}/pasta`), porque a tela precisa dos totais antes de qualquer
expansão e acrescentar filtro por paciente em três serviços espalharia o assunto.

Junto veio um achado: desde `importar-sessoes-por-ia`, a confirmação da transcrição **exigia** o
`pdf_registro_sessoes` quando o cartão era da regional 0220, validava o arquivo e o descartava ao fim
da requisição. Nada o gravava. Agora ele entra na pasta como documento `registro_sessoes`, com
`metadata` amarrando a guia que originou a remessa.

Sete testes novos. Specs: change `pasta-do-paciente` criada e validada.

## 2. Change `credenciais-por-convenio` (`5177cc5` … `bd6456f`)

### O que motivou

`unimed_rda_credentials` tinha `unique('tenant_id')`: uma credencial por tenant, e o convênio era
descoberto procurando `connector_driver = 'unimed_rda'`. Em 14/09 um `WORKER_INTERNAL_FATAL` num
único item fez o disjuntor pausar essa credencial única — a automação de **todos** os itens do tenant
parou, e a credencial precisou ser reativada à mão duas vezes na mesma sessão. Com um segundo
convênio automatizado, teria derrubado também o que não tinha problema nenhum.

### O que foi feito, por seção

| Seção | Commit | O que entrou |
|---|---|---|
| Specs | `5177cc5` | Cenário de segredo fora do registro de execução; tarefas 3.5, 5.6 e 10.2.1 |
| 1. Catálogo | `4d41702` | `ConvenioDriverCatalog` — campos por driver, fonte única de API e front |
| 2. Banco | `e7cb6d2` | `convenio_credenciais` com `unique(tenant_id, convenio_id)` + migração de dados |
| 4. Permissões | `d8a83ba` | `configuracoes.convenios.manage`, herdada de quem tinha a da Unimed |
| 3. API | `63deff2` | Repositório, controller, resource, request e dez rotas |
| 5. Services | `eaeb020` | Disjuntor e credencial por convênio; `CREDENTIAL_MISSING` |
| 6. Guarda | `eb2fd08` | Testes de que credencial não liga automação |
| 7. Frontend | `bd6456f` | `ConfiguracoesConveniosPage`, formulário vindo do catálogo |
| 7. De-para | (este) | Editores de especialidade e profissional na tela nova; defeito nas rotas PATCH |

### As três decisões que mais custam se forem esquecidas

**`connector_driver` continua sendo o interruptor.** Ele tem doze consumidores, e o
`VerificarGuiasDiarioJob` **deixa de conferir** as guias do convênio quando está ligado. Cadastrar o
SC Saúde com `connector_driver = 'scsaude'` ligaria uma automação inexistente e tiraria as guias dele
da verificação diária — sem ninguém notar, porque sumir é o que o job faz de propósito. Por isso
`convenio_credenciais.driver` é coluna própria e a tela nova não escreve `connector_driver`.
Registrado no ADR-29.

**A rota legada mantém a escrita do `connector_driver`.** `PUT /configuracoes/unimed` é o único lugar
do sistema que liga e desliga a automação de um convênio. O controller novo não o faz de propósito, e
delegar essa parte faria a capacidade sumir sem aviso. Decisão tomada com o usuário, não por mim.

**A migração copia, não move.** `unimed_rda_credentials` fica no lugar, com os dados. Enquanto ela
existir e as rotas `/configuracoes/unimed*` responderem, reverter é voltar o deploy do front.

### Erros cometidos no caminho, e o que os impede de voltar

- **Migration de permissão criando papel sem tenant.** O primeiro rascunho tinha um fallback
  `Role::findOrCreate('admin', 'web')`; papel aqui é por tenant, então esse papel nascia com
  `tenant_id` nulo e colidia com os do `RoleSeeder` — catorze testes quebraram. O fallback era
  desnecessário (instalação nova recebe a permissão pelo `RoleCatalog`), e hoje há teste cobrindo
  exatamente isso: `migracao nao cria papel sem tenant`.
- **Teste vacuoso do disjuntor.** A primeira versão de `falha estrutural pausa só o convênio da
  execução` disparava a falha no **primeiro** convênio — e passava com o código antigo, porque
  `where('tenant_id')->first()` devolvia justamente esse. Disparando no segundo, o comportamento
  antigo pausa o errado e três testes o pegam. O comentário no teste registra o porquê.
- **Rotas PATCH aninhadas quebradas.** As seis rotas de de-para sob `.../{convenio}/` entraram na
  seção 3 sem teste. Com dois parâmetros na URL e só um model na assinatura, o Laravel injetava o
  **primeiro** — o `{convenio}` chegava no lugar do mapeamento e o PATCH estourava com TypeError.
  Só apareceu quando escrevi o teste da seção 7. Corrigido com `updateDoConvenio` nos dois
  controllers, que declara os dois models e ainda confere o convênio da URL contra o do registro.
  Lição repetida: rota registrada sem teste é rota não verificada.
- **Pint reformatando arquivos não tocados.** Em dois commits ele mexeu em arquivos fora do escopo
  (`AutomacaoService`, `AutomationPayloadRedactor`, `FakeUnimedWorkerClient`, `LancamentosApiTest`);
  revertidos, porque num commit que mexe em automação de produção a revisibilidade vale mais.

### Critérios de aceite

| # | Critério | Coberto por |
|---|---|---|
| 1 | Credencial Unimed existente segue funcionando após migrar | `MigracaoCredenciaisPorConvenioTest` (6) |
| 2 | Dois convênios com credencial ao mesmo tempo | `ConvenioCredenciaisApiTest` |
| 3 | Falha em um convênio não pausa o outro | `DisjuntorPorConvenioTest` (7) |
| 4 | Credencial sem driver segue no fluxo manual e na verificação diária | `CredencialNaoLigaAutomacaoTest` (4) |
| 5 | Nenhum segredo em payload, eventos ou `/automacoes/{id}` | `SegredoForaDoRegistroDeExecucaoTest` (4) |
| 6 | Papel com a permissão antiga abre a tela nova | `SincronizaPermissaoConveniosTest` (5) + API |
| 7 | Driver sem campos avisa e não trava a tela | `ConvenioCredenciaisApiTest` |
| 8 | Rotas `/configuracoes/unimed*` continuam respondendo | `UnimedSettingsApiTest` (10) |

## 3. O que ficou pendente

**Seção 8 — cadastro do SC Saúde.** É entrada de dados em produção, não código. Depois do deploy,
pela tela de Convênios: cadastrar **sem `connector_driver`** e preencher `carteirinha_blocos` com a
máscara de 17 dígitos (`0306XXXXXXXXXXXXX`). Conferir então que ele aparece com o aviso de
autenticação pendente, que a Unimed segue funcionando e que as guias do SC Saúde continuam no fluxo
manual.

**Tarefas 10.4 e 10.5 — ensaio e backup.** Na VPS, antes da janela: ensaiar a migração sobre uma
cópia do banco de produção e conferir que a automação da NeuroKids roda com a credencial migrada;
backup no roteiro de 14/09 (dump do banco, volume de storage, `api/.env`, `deploy/.secrets.env`, com
`gzip -t` e contagem de tabelas conferidos, mais a cópia no Drive).

## 4. Validação

Baseline antes de começar, e depois de cada seção:

| Suíte | Antes | Depois |
|---|---|---|
| `php artisan test` | 612 passaram | 662 passaram (2509 asserções) |
| `tsc -b` | limpo | limpo |
| `oxlint` | sem erro | sem erro |
| `npm run ds:check` | as quatro guardas da §11 | as quatro guardas da §11 |
| `npm run build` | ✓ | ✓ |
| worker `node --test` | 38 passaram | não reexecutado — nenhum arquivo do worker foi tocado |
| `openspec validate --all` | 59 / 0 | 59 / 0 |

Cada teste novo foi verificado falhando sem a sua correção, rodando a mutação e restaurando.

**Specs lidas:** `AGENTS.md`, `openspec/config.yaml`, `credenciais-por-convenio/proposal.md`,
`tasks.md`, `specs/credenciais-por-convenio/spec.md`, `specs/configuracao-unimed-rda/spec.md`,
`pacientes-crud`, `importar-sessoes-por-ia`, `guia-detail`.

**Documentação:** ADR-29 em `docs/decisoes-arquitetura.md`; `convenio_credenciais` em
`docs/schema.md`.
