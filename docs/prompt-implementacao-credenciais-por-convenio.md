# Prompt de implementação — change `credenciais-por-convenio`

> Para rodar dentro do repositório, numa branch própria. Copie tudo abaixo da linha.

---

Implemente o change OpenSpec `credenciais-por-convenio`, que está em
`openspec/changes/credenciais-por-convenio/`.

## Antes de escrever qualquer código

1. Leia, nesta ordem: `AGENTS.md`, `openspec/config.yaml`,
   `openspec/changes/credenciais-por-convenio/proposal.md`, `tasks.md`, e os dois arquivos de spec
   em `specs/`. Leia também `docs/decisoes-arquitetura.md` (ADR-03, ADR-07, ADR-13, ADR-14).
2. Rode a suíte completa **antes de mudar qualquer coisa** e anote os números. Esse é o baseline
   contra o qual toda regressão será medida:
   - `api`: `php artisan test` via `deploy/run-tests.sh`
   - `web`: `tsc -b`, `oxlint`, `npm run ds:check`
   - `worker-unimed`: `node --test` na imagem Playwright
   Se algum já estiver vermelho antes de você começar, pare e relate — não comece sobre base suja.
3. Rode `openspec validate credenciais-por-convenio --type change --no-interactive`.

## Regra número um: a automação da Unimed não pode regredir

Existe **um único tenant em produção** (NeuroKids), com a automação da Unimed rodando em guias
reais todos os dias. Quebrar isso custa atendimento de paciente, não só um bug.

Por isso, são invioláveis:

- **Não remova** a tabela `unimed_rda_credentials`. Ela fica no lugar, com os dados, até um change
  de limpeza posterior. A migração **copia**, não move.
- **Não remova** as rotas `/configuracoes/unimed*`. Elas passam a delegar ao controller novo e
  continuam respondendo por um ciclo. `POST /configuracoes/unimed/reativar` é usado em operação
  real — foi o caminho da reativação manual no incidente de 14/09.
- **Não toque** em `convenios.connector_driver` nem em nenhum dos seus consumidores
  (`GuiaService`, `SolicitacaoService`, o job que enfileira consultas, `VerificarGuiasDiarioJob` e
  os demais). Esse campo é o interruptor da automação; credencial é outra coisa.
- **Não mude** o comportamento observável da automação Unimed. Os testes existentes devem passar
  com adaptação apenas do arranjo (o `UnimedRdaCredential::factory` vira a credencial nova), nunca
  das asserções de comportamento.
- **Não persista segredo.** `GerarGuiaUnimedService` separa `payloadPersistido()` — o que vai para
  `automacao_execucoes.payload`, sem credencial — de `payloadParaWorker()`, que monta a credencial
  na hora do envio. Foi assim que a exfiltração da senha foi fechada em 14/09. O repositório novo
  entra em `payloadParaWorker()` e em lugar nenhum mais.

## Ordem de execução

Siga as seções de `tasks.md` na ordem: catálogo de drivers → banco → API → permissões → services →
guarda do `connector_driver` → frontend → cadastro do SC Saúde → documentação → validação.

Faça **um commit por seção**, cada um com a suíte verde. Não junte tudo num commit só: se algo
quebrar em produção, o valor está em saber qual parte foi.

## Pontos onde é fácil errar

- **Rota nova nasce fechada.** O middleware `ExigeAutorizacaoDeclarada` recusa com 403 qualquer
  rota autenticada que não declare `permission:`. As quatro rotas novas precisam declarar guarda.
  Não inscreva nada na lista de rotas abertas.
- **Binding já é isolado por tenant** desde 14/09. Não repita a checagem no controller.
- **`unique(['tenant_id','convenio_id'])`**, não `unique('tenant_id')`. O defeito que este change
  existe para corrigir é exatamente esse.
- **Cast `encrypted:array` no campo inteiro** e `auditOcultos = ['credenciais']`. A auditoria
  registra que mudou, nunca o valor.
- **Campo secreto nunca volta na resposta.** Devolva `preenchido: true`, no espírito do que
  `UnimedSettingsResource` já faz com `senha_configurada`. Campo `password` enviado em branco
  preserva o valor gravado.
- **O disjuntor passa a receber o convênio.** `UnimedCircuitBreakerService::handleResult()` hoje
  recebe só `tenantId` e pausa a única credencial existente — foi o que derrubou a automação de
  todos os itens em 14/09 a partir da falha de um. Pausar apenas o convênio da execução. Cuidado
  para não cair no oposto: ele precisa continuar pausando quando deve.
- **Migração de dados tolerante.** Tenant com credencial e sem convênio de `connector_driver =
  'unimed_rda'`: não migre, registre no log, não falhe a migration.
- **Código de procedimento não entra nesta tela.** Ele vive no cadastro da especialidade, sobre
  `convenio_especialidade_mapeamentos`. Duas fontes para o mesmo código sairiam do ar uma da outra.

## Critérios de aceite

Cada um destes precisa estar coberto por teste automatizado, não só por conferência manual:

1. Credencial Unimed existente continua funcionando após a migração, sem novo cadastro.
2. Um tenant pode ter credenciais de dois convênios ao mesmo tempo, sem uma sobrescrever a outra.
3. Falha estrutural num convênio pausa só a credencial dele; a do outro convênio segue ativa.
4. Convênio com credencial cadastrada e `connector_driver` vazio continua no fluxo manual **e
   segue aparecendo na verificação diária de guias**.
5. Nenhum campo secreto aparece em `automacao_execucoes.payload`, nos eventos, nem na resposta de
   `/automacoes/{id}`.
6. Um papel que tinha `configuracoes.unimed.manage` continua com acesso à tela nova.
7. Driver sem campos no catálogo (o caso do `scsaude`) mostra o aviso de autenticação pendente e
   não impede o uso da tela para os outros convênios.
8. As rotas `/configuracoes/unimed*` continuam respondendo.

## Antes do deploy

- Ensaie a migração sobre uma cópia do banco de produção e confirme que a automação da NeuroKids
  roda com a credencial migrada. Não descubra isso na janela.
- Backup no roteiro de 14/09: dump do banco, volume de storage do Laravel, `api/.env` e
  `deploy/.secrets.env`, com `gzip -t` e contagem de tabelas conferidos, mais a cópia no Drive.
- Deploy via `deploy/redeploy.sh`, um passo por vez.
- Plano de rollback explícito: enquanto `unimed_rda_credentials` existir e as rotas antigas
  responderem, reverter é voltar o deploy do front.

## Ao terminar

Conforme o `AGENTS.md`, registre no resumo final quais specs foram lidas e quais comandos de
validação foram executados, com os números antes e depois. Marque as tarefas concluídas em
`tasks.md` e escreva o resumo de entregas do dia em `docs/`, no padrão dos arquivos
`resumo-entregas-*.md`.

Se encontrar ambiguidade entre a spec e o código existente, **pare e pergunte** em vez de decidir
sozinho — é o que o `AGENTS.md` exige.
