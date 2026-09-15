## 1. Catálogo de drivers

- [x] 1.1 Criar `api/app/Support/ConvenioDriverCatalog.php` com, por driver: rótulo, se está
      implementado e a lista de campos (`chave`, `rotulo`, `tipo` em `text|password|url`,
      `obrigatorio`, `dica`).
- [x] 1.2 Registrar `unimed_rda` com `login` (text, obrigatório), `password` (password,
      obrigatório), `base_url` (url, opcional) e `nome_contratado` (text, opcional) — os mesmos
      campos de hoje. Usar a string `unimed_rda`, que é o valor real de `connector_driver`.
- [x] 1.3 Registrar `scsaude` com lista de campos **vazia** e rótulo de autenticação pendente.
      Não inventar campos antes da documentação do WebService chegar.
- [x] 1.4 Teste unitário do catálogo: driver desconhecido, driver sem campos, campo obrigatório.

## 2. Banco

- [x] 2.1 Migration `create_convenio_credenciais_table`: `id`, `tenant_id`, `convenio_id`,
      `driver`, `credenciais` (text), `ativo` (bool, default true), `automation_paused_at`,
      `automation_paused_reason`, timestamps; `unique(['tenant_id','convenio_id'])` e índice
      `['tenant_id','driver']`.
- [x] 2.2 Model `ConvenioCredencial` com `BelongsToTenant`, `Auditable`, cast
      `credenciais => 'encrypted:array'` e `auditOcultos = ['credenciais']`.
- [x] 2.3 Migration de dados: copiar cada linha de `unimed_rda_credentials` para a nova tabela,
      resolvendo `convenio_id` pelo convênio do tenant com `connector_driver = 'unimed_rda'`,
      montando `credenciais` a partir de login, password, base_url e nome_contratado, e
      preservando `ativo` e os campos de pausa.
- [x] 2.4 Na mesma migration, registrar no log os tenants com credencial e sem convênio
      correspondente, sem migrá-los e sem falhar.
- [x] 2.5 **Não** remover `unimed_rda_credentials` neste change. Abrir change de remoção depois
      de um ciclo em produção.
- [x] 2.6 Teste de migração com credencial existente: valores preservados e legíveis após migrar.

## 3. API

- [x] 3.1 `ConvenioCredencialRepository::paraConvenio(int $tenantId, int $convenioId)`, ponto
      único de leitura da credencial.
- [x] 3.2 `ConvenioCredenciaisController` com `index` (convênios do tenant + estado da credencial
      + catálogo do driver), `update`, `health` e `reativar`.
- [x] 3.3 `ConvenioCredencialResource`: devolve `preenchido: true` para campo `password`, nunca o
      valor. Espelhar o que `UnimedSettingsResource` já faz com `senha_configurada`.
- [x] 3.4 `UpdateConvenioCredencialRequest`: validar as chaves contra o catálogo do driver
      escolhido; rejeitar chave desconhecida; campo `password` em branco preserva o valor gravado.
- [x] 3.5 Rotas `/configuracoes/convenios-credenciais`, `.../{convenio}`,
      `.../{convenio}/worker-health` e `.../{convenio}/reativar`. **Cada uma precisa declarar
      `permission:`** — desde 14/09 o middleware `ExigeAutorizacaoDeclarada` recusa com 403
      qualquer rota autenticada sem guarda declarada, e a lista de rotas abertas por decisão é
      curta de propósito. O `{convenio}` do binding já vem isolado por tenant por padrão desde o
      mesmo dia; não repetir a checagem no controller.
- [x] 3.6 Manter `/configuracoes/unimed*` respondendo por um ciclo, delegando ao novo controller,
      com comentário de depreciação e data. **`POST /configuracoes/unimed/reativar` é usado em
      operação** — foi o caminho da reativação manual em 14/09 — então não pode simplesmente sumir.
- [x] 3.7 Mover os de-para de especialidade e profissional para
      `.../{convenio}/mapeamentos/*`, mantendo alias. Não incluir edição de código de
      procedimento aqui: ela vive no cadastro da especialidade (change
      `codigo-por-convenio-na-especialidade`), sobre a mesma tabela
      `convenio_especialidade_mapeamentos`.

## 4. Permissões

- [x] 4.1 Adicionar `configuracoes.convenios.manage` ao `PermissionCatalog` (ADR-14: catálogo
      fixo em código).
- [x] 4.2 Migration de sincronização concedendo a permissão nova a todo papel que já tem
      `configuracoes.unimed.manage`, no padrão de
      `2026_08_03_200001_sync_unimed_manage_permission_to_existing_roles.php`.
- [x] 4.3 Aceitar as duas permissões nas rotas por um ciclo; remover a antiga no change de limpeza.

## 5. Services de automação

- [x] 5.1 Trocar a consulta de credencial pelo repositório em `GerarGuiaUnimedService`,
      `ConsultarStatusUnimedService`, `CapturarSenhaValidadeUnimedService`,
      `ConfirmarGuiaIncertaUnimedService` e `UnimedCircuitBreakerService`.
- [x] 5.2 Resolver o convênio a partir do item ou da guia em execução, não por
      `connector_driver = 'unimed_rda'`.
- [x] 5.3 `UnimedCircuitBreakerService::handleResult(int $tenantId, array $result)` passa a
      receber também o convênio e pausa apenas a credencial dele. Sem isso, a falha de um item
      continua derrubando a automação de todos — foi o que aconteceu em 14/09.
- [x] 5.4 Manter a auditoria da pausa com `doSistema: true` e a entidade correta; a ação passa a
      referenciar `convenio_credenciais`.
- [x] 5.5 Erro tratado e identificável quando o convênio não tiver credencial ativa — conferir o
      `AutomationErrorCatalog` antes de criar código novo.
- [x] 5.6 Preservar a separação que já existe em `GerarGuiaUnimedService`:
      `payloadPersistido()` é o que vai para `automacao_execucoes.payload`, sem credencial;
      `payloadParaWorker()` monta a credencial na hora do envio. Esse desenho é o que fechou a
      exfiltração da senha do portal em 14/09 — o repositório novo entra em `payloadParaWorker()`
      e em nenhum outro lugar.
- [x] 5.7 Sem mudança de comportamento observável na automação Unimed: a suíte existente deve
      passar com adaptação só do arranjo dos testes.

## 6. Guarda do `connector_driver`

- [x] 6.1 Confirmar, antes de codar, que nenhum dos doze consumidores de
      `convenios.connector_driver` passa a depender da existência de credencial — em especial
      `GuiaService`, `SolicitacaoService`, o job que enfileira consultas ao portal e o
      `VerificarGuiasDiarioJob`, que deixa de conferir manualmente as guias do convênio quando o
      driver está ligado.
- [x] 6.2 Teste de regressão: convênio com credencial cadastrada e `connector_driver` nulo
      continua no fluxo manual e segue aparecendo na verificação diária.

## 7. Frontend

- [x] 7.1 Criar `web/src/features/configuracoes/ConfiguracoesConveniosPage.tsx`, tirando a aba
      Unimed de `ConfiguracoesPage.tsx`.
      > Página criada. A aba antiga NÃO foi removida: a rota `/configuracoes/unimed`
      > segue respondendo por um ciclo e é por ela que se liga e desliga a automação
      > de um convênio; removê-la deixaria a rota depreciada sem interface.
- [x] 7.2 Seletor de convênio no topo; formulário renderizado a partir do catálogo devolvido
      pela API.
- [x] 7.3 Driver sem campos: mostrar o aviso de autenticação pendente, sem formulário e sem erro.
- [x] 7.4 Renomear os hooks de `useUnimedSettings` para `useConvenioCredenciais`, ajustando as
      chaves de cache do TanStack Query.
- [x] 7.5 `navigation.ts`: item `Convênios e credenciais` em `/configuracoes/convenios`; remover
      o item `Unimed RDA`.
- [ ] 7.6 Passar o convênio selecionado para os de-para de especialidade e profissional.
      > PENDENTE. As rotas `.../{convenio}/mapeamentos/*` existem e respondem, mas a
      > tela nova ainda não renderiza os editores de de-para — eles continuam só na
      > aba antiga, que segue funcional. Falta portar ~400 linhas de UI.
- [x] 7.7 Rodar `npm run ds:check` — o contrato de design reprova valor mágico e classe fora do
      token.

## 8. Cadastro do convênio SC Saúde

- [ ] 8.1 Cadastrar o convênio SC Saúde no tenant da NeuroKids **sem `connector_driver`** — ligar
      o driver hoje acionaria uma automação inexistente e tiraria as guias do convênio da
      verificação diária.
- [ ] 8.2 Preencher `convenios.carteirinha_blocos` conforme a máscara de 17 dígitos com zero à
      esquerda (`0306XXXXXXXXXXXXX`), pela tela de Convênios — o formato já é independente do
      driver desde `carteirinha-formato-por-convenio`.
- [ ] 8.3 Conferir na tela que o convênio aparece com o aviso de autenticação pendente, que a
      Unimed segue funcionando e que as guias do SC Saúde continuam no fluxo manual.

## 9. Documentação

- [x] 9.1 Propor ADR-29 em `docs/decisoes-arquitetura.md`: credencial de automação pertence ao
      convênio, e `connector_driver` continua sendo interruptor de automação, não rótulo de
      fornecedor.
- [x] 9.2 Atualizar `docs/schema.md` com `convenio_credenciais`.

## 10. Validação

- [x] 10.1 `openspec validate credenciais-por-convenio --type change --no-interactive`.
- [x] 10.2 Suíte PHP completa; adaptar `UnimedSettingsApiTest`, `GerarGuiaUnimedApiTest` e
      `ConfirmarGuiaIncertaUnimedApiTest`, que instanciam `UnimedRdaCredential` direto.
- [x] 10.2.1 Teste de não-vazamento: nenhum campo secreto aparece em
      `automacao_execucoes.payload`, nos eventos, nem na resposta de `/automacoes/{id}`.
- [x] 10.3 `tsc -b`, `oxlint`, `vite build` e os e2e de configurações.
- [ ] 10.4 Ensaiar a migração sobre uma cópia do banco de produção antes da janela, conferindo
      que a automação da NeuroKids roda com a credencial migrada.
- [ ] 10.5 Backup pré-deploy no roteiro de 14/09: dump do banco, volume de storage, `api/.env` e
      `deploy/.secrets.env`, com `gzip -t` e contagem de tabelas conferidos.
