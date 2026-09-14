## Why

A credencial de automação hoje é da Unimed, não do convênio. `unimed_rda_credentials`
tem `unique('tenant_id')` e nenhuma coluna de convênio: um tenant tem no máximo uma
credencial, e o convênio correspondente é descoberto procurando
`connector_driver = 'unimed_rda'`. Toda a superfície repete o nome do fornecedor —
`UnimedSettingsController`, `UnimedSettingsResource`, `useUnimedSettings`, a rota
`/configuracoes/unimed`, a permissão `configuracoes.unimed.manage` e a aba
"Unimed RDA" dentro de `ConfiguracoesPage.tsx`.

Isso já cobrou o preço em produção. Em 14/09/2026 (ver `docs/resumo-entregas-2026-09-14.md`
§2), um `WORKER_INTERNAL_FATAL` num único item fez o `UnimedCircuitBreakerService` pausar a
credencial do tenant inteiro — `handleResult()` recebe apenas `tenantId` e grava
`ativo = false` na única credencial que existe. A automação de todos os itens parou, e a
credencial precisou ser reativada manualmente duas vezes na mesma sessão. Com um segundo
convênio automatizado, esse mesmo disjuntor derrubaria também o convênio que não tinha
problema nenhum.

O SC Saúde entra como segundo convênio. Copiar a estrutura atual criaria
`scsaude_credentials` com o mesmo defeito e um terceiro conjunto no convênio seguinte.
Some-se a isso que a forma de autenticação do SC Saúde ainda é desconhecida: o plano
confirmou que existe WebService, mas a clínica está em fila de implementação e não recebeu
documentação. Desenhar hoje um formulário de login e senha para ele é chute.

Este change generaliza a credencial para o convênio e transforma os campos do formulário em
dado de catálogo, para que um convênio novo entre sem tela nova — na linha do ADR-03, que
manda tratar regra de convênio como dado e não como código.

## What Changes

- Nova tabela `convenio_credenciais`, chaveada por `tenant_id + convenio_id`, com os campos
  específicos do fornecedor num JSON encriptado.
- Migração de dados de `unimed_rda_credentials` para a nova tabela com `driver = 'unimed_rda'`.
  A tabela antiga **não é removida neste change** — fica para o change seguinte, depois de um
  ciclo em produção.
- Catálogo de drivers em código: para cada driver, o rótulo e a definição dos campos (chave,
  rótulo, tipo, obrigatoriedade, dica). Fonte única — a API valida contra ele e o front
  renderiza a partir dele.
- Driver `scsaude` registrado **sem campos**, com aviso de autenticação pendente.
- O disjuntor passa a pausar a credencial do convênio afetado, não a do tenant.
- Rotas `/configuracoes/convenios-credenciais`, mantendo `/configuracoes/unimed` como alias
  depreciado por um ciclo.
- Permissão `configuracoes.convenios.manage`, com `configuracoes.unimed.manage` aceita como
  sinônimo por um ciclo.
- Tela própria `ConfiguracoesConveniosPage.tsx`, extraída de `ConfiguracoesPage.tsx`, com
  seletor de convênio e formulário renderizado do catálogo.

## Decisões

- **O driver da credencial é separado de `convenios.connector_driver`.** `connector_driver`
  não é um rótulo de fornecedor: é o interruptor de toda a automação, com doze consumidores,
  entre eles `GuiaService`, `SolicitacaoService`, o job que enfileira consultas ao portal e o
  `VerificarGuiasDiarioJob` — que **deixa de conferir manualmente** as guias do convênio por
  assumir que a automação cuida delas. O change `carteirinha-formato-por-convenio` já
  registrou esse acoplamento ao desamarrar a máscara de carteirinha dele.
  Consequência direta: cadastrar o SC Saúde com `connector_driver = 'scsaude'` agora ligaria
  uma automação que não existe, falhando em toda guia nova e tirando essas guias da
  verificação diária. Por isso `convenio_credenciais.driver` é coluna própria, e o SC Saúde
  entra como convênio comum, sem `connector_driver`.
- **JSON encriptado inteiro, não coluna por campo.** O cast é `encrypted:array` no campo todo.
  Perde-se a busca por login — que ninguém faz — e ganha-se um campo novo de qualquer driver
  futuro sem migration, inclusive o arquivo de um certificado digital, se o WebService do SC
  Saúde exigir. `auditOcultos` cobre o campo inteiro: fica registrado que mudou, nunca o valor.
- **A senha não volta na resposta.** `UnimedSettingsResource` hoje devolve
  `senha_configurada => filled($credential->password)` em vez do valor. Isso vira requisito
  para todo campo do tipo `password` do catálogo, e é o que a spec de `configuracao-unimed-rda`
  já exigia.
- **Código de procedimento não entra nesta tela.** O change
  `codigo-por-convenio-na-especialidade` move o código por par convênio × especialidade para o
  cadastro da especialidade, reaproveitando `convenio_especialidade_mapeamentos`. Duas fontes
  para o mesmo código sairiam do ar uma da outra. Esta tela mantém apenas o de-para que a
  automação usa, operando sobre o convênio selecionado.
- **Rollback.** Enquanto `unimed_rda_credentials` existir e as rotas antigas responderem,
  reverter é voltar o deploy do front.

## Conflito registrado

Conforme `AGENTS.md`, fica registrado antes da alteração: a capability
`configuracao-unimed-rda` (change `automacao-unimed-multi-itens`) declara o requisito
**"Credenciais Unimed seguras por tenant"**, com o cenário de salvar "a credencial Unimed do
tenant". Este change o contraria deliberadamente e o substitui por uma credencial por
convênio. A substituição está declarada em `specs/configuracao-unimed-rda/spec.md`.

## Capabilities

### New Capabilities

- `credenciais-por-convenio`: cadastro de credencial de automação por convênio, com campos
  definidos pelo driver.

### Modified Capabilities

- `configuracao-unimed-rda`: o requisito de credencial por tenant passa a ser por convênio, e
  a pausa do disjuntor deixa de alcançar o tenant inteiro.

## Non-Goals

- Nenhum código de automação do SC Saúde, nem geração de XML TISS.
- Nenhum campo de credencial do SC Saúde antes da documentação do WebService chegar.
- Nenhuma remoção da tabela `unimed_rda_credentials` — fica para o change de limpeza.
- Nenhuma mudança em `connector_driver` ou nos seus doze consumidores.

## Impact

- **Banco**: nova tabela e migração de dados com a NeuroKids em produção (73 tabelas). Exige
  janela e backup conferido (`docs/backup.md`, e o roteiro usado em 14/09).
- **API**: novo controller, resource, request e repositório; `UnimedSettingsController` passa a
  delegar enquanto o alias existir. Cinco services de automação trocam a consulta da
  credencial, e `UnimedCircuitBreakerService::handleResult()` muda de assinatura.
- **Permissões**: entrada nova no `PermissionCatalog` (ADR-14: catálogo fixo em código) e
  migration de sincronização para os papéis existentes.
- **Frontend**: novo item de navegação, nova página, hooks renomeados.
- **Testes**: `UnimedSettingsApiTest`, `GerarGuiaUnimedApiTest` e
  `ConfirmarGuiaIncertaUnimedApiTest` instanciam `UnimedRdaCredential` direto.
- **ADR**: propor ADR-29 registrando que credencial de automação pertence ao convênio, e que
  `connector_driver` continua sendo interruptor de automação, não rótulo de fornecedor.
