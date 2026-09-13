> **Change retroativa.** O comportamento já está em produção (`7fd6020`, `6985efe`,
> `c4d2b1d`, `81acc08`, `94dcdca`, `afbfa41`). As tarefas abaixo foram marcadas
> uma a uma conferindo o código e o teste que as prova, em 13/09/2026, com a
> suíte inteira verde (546 testes na API, 18 no e2e). O que não tem cobertura
> ficou aberto, e está identificado no grupo 6.

## 1. Estrutura de dados

- [x] 1.1 Trocar `lancamentos.antecipacao_id` por `lancamentos.guia_id`, com o drop na ordem que o MySQL exige — verificado em `2026_09_10_195508_replace_antecipacao_id_with_guia_id_on_lancamentos_table.php` e na correção `afbfa41`; as 12 migrations aplicam limpo (`php artisan migrate`, 0 pendentes).
- [x] 1.2 Derrubar as tabelas do modelo de cota (`antecipacoes`, `antecipacao_import_lotes`, `antecipacao_import_linhas`) — verificado em `2026_09_10_195509_drop_antecipacao_tables.php`.
- [x] 1.3 Criar `antecipacoes` no formato de histórico, com `solicitacao_origem_id`, `itens_selecionados`, `status` e autoria — verificado em `2026_09_11_180100_create_antecipacoes_table.php` e `2026_09_11_190000_drop_solicitacao_gerada_id_from_antecipacoes_table.php`.
- [x] 1.4 Adicionar `antecipacao_dias` e `antecipacao_referencia` em `configuracoes_globais`, o override em `convenios` e os campos em `guias` — verificado nas migrations `2026_09_10_210000/210001/210002` e no enum de `2026_09_11_180000`.

## 2. Cálculo da data-alvo e elegibilidade

- [x] 2.1 Resolver a data-alvo na cascata guia → convênio → global, sobre o campo de referência configurado — verificado em `Guia::antecipacaoDataAlvo()`; provado por `CentralDeAlertasTest::test_antecipacao_convenio_sobrescreve_o_padrao_global` e `test_antecipacao_data_manual_na_guia_sobrescreve_tudo`.
- [x] 2.2 Tratar guia sem o campo de referência preenchido como sem data-alvo — verificado no retorno `null` de `antecipacaoDataAlvo()` e no filtro de `Guia::elegiveisParaAntecipacao()`.
- [x] 2.3 Filtrar elegíveis por status aprovado/finalizado, não histórica, não dispensada e data-alvo alcançada — verificado em `Guia::elegiveisParaAntecipacao()`; provado por `test_antecipacao_nao_alerta_antes_da_data_alvo` e pelas duas variantes de referência `guia criada`.
- [x] 2.4 Isolar a fila por clínica — verificado no escopo por `tenant_id` de `elegiveisParaAntecipacao()`; provado por `AntecipacoesApiTest::test_sem_permissao_de_ver_a_listagem_recusa` em conjunto com o `BelongsToTenant` do model.

## 3. Alerta

- [x] 3.1 Implementar a regra `antecipacao.devida` na Central de Alertas, sem gerar nada sozinha — verificado em `Services/Alertas/Regras/AntecipacaoDevida.php`.
- [x] 3.2 Escalar o nível quando o atraso passar o limiar configurável — verificado no `limiar_vermelho` da regra; provado por `test_antecipacao_vira_vermelho_apos_limiar_de_atraso`.
- [x] 3.3 Oferecer as ações de dispensar e de abrir a geração — verificado em `dados.acoes`; provado por `test_antecipacao_alerta_leva_para_a_tela_de_geracao`.
- [x] 3.4 Dispensar pelo alerta tira a guia da fila e resolve o alerta — verificado em `GuiaController::ocultarAlertaAntecipacao` e no filtro `alerta_antecipacao_ocultado_em`; provado por `test_antecipacao_ocultar_resolve_o_alerta`.

## 4. Fila, geração e histórico

- [x] 4.1 Agrupar elegíveis por solicitação, exibindo a data-alvo mais antiga do grupo — verificado em `AntecipacaoService::listarElegiveis()`; provado por `AntecipacoesApiTest::test_elegiveis_lista_solicitacao_candidata`.
- [x] 4.2 Omitir da fila as solicitações que já têm registro de antecipação — verificado no `whereNotIn` de `listarElegiveis()`; provado por `test_elegiveis_exclui_solicitacao_ja_gerada_ou_ignorada`.
- [x] 4.3 Gerar criando item de renovação encadeado na mesma solicitação — verificado em `AntecipacaoService::criar()`, que reusa `SolicitacaoService::adicionarItem()` com `renovacao_de_item_id`; provado por `test_criar_gera_item_e_guia_por_renovacao_na_mesma_solicitacao`.
- [x] 4.4 Recusar par especialidade/profissional que não pertença à solicitação — verificado na `ValidationException` de `criar()`; provado por `test_criar_recusa_item_que_nao_pertence_a_solicitacao`.
- [x] 4.5 Dispensar sem gerar nada, registrando como ignorada — verificado em `AntecipacaoService::ignorar()`; provado por `test_ignorar_cria_registro_sem_gerar_nada`.
- [x] 4.6 Registrar autoria e momento da geração — verificado nos campos `criado_por_id`/`gerado_em` gravados por `criar()`.
- [x] 4.7 Filtrar o histórico por status e paginá-lo — verificado em `AntecipacaoService::listar()`.
- [x] 4.8 Excluir do histórico sem afetar itens e guias gerados — verificado em `AntecipacaoService::remover()`, que só apaga o registro; a confirmação na UI declara isso ao operador (`AntecipacoesPage`).

## 5. Permissões e disponibilidade de sessões

- [x] 5.1 Separar `antecipacoes.view` de `antecipacoes.manage` nas rotas — verificado nos middlewares de `routes/api.php`; provado por `test_sem_permissao_de_ver_a_listagem_recusa` e `test_sem_permissao_de_gerir_nao_cria`.
- [x] 5.2 Sincronizar `antecipacoes.*` com o catálogo de papéis nas clínicas existentes — verificado em `2026_09_11_180200_sync_antecipacoes_permissions_to_existing_roles.php` (`94dcdca`).
- [x] 5.3 Calcular a disponibilidade de sessões ao vivo e oferecer para lançamento só as guias com saldo — verificado no filtro `disponivel_para_lancamento` de `GuiaService`; provado por `GuiasApiTest::test_filtro_disponivel_para_lancamento`.
- [x] 5.4 Refletir o saldo na exibição da guia após o lançamento — verificado em `GuiaResource::sessoes_disponiveis` e no resumo da guia; provado pelo e2e `mvp-flow` (`0 lançada(s) · 1 disponível(is)` → `1 lançada(s) · 0 disponível(is)`).

## 6. Lacunas de cobertura encontradas ao mapear

- [x] 6.1 Cobrir a exclusão de um registro que gerou itens, provando que itens **e guias** sobrevivem — `test_excluir_do_historico_preserva_item_e_guia_gerados`. Correção ao levantamento inicial: a exclusão não estava descoberta, ela já era exercitada no fim de `test_criar_gera_item_e_guia_por_renovacao_na_mesma_solicitacao`; o que faltava era conferir a **guia** (só o item era verificado) e ter a falha apontando a exclusão em vez da criação.
- [x] 6.2 Cobrir o filtro por status do histórico — `test_historico_filtra_por_status`, conferindo `gerada`, `ignorada` e a listagem sem filtro.
- [x] 6.3 Cobrir o isolamento cross-tenant da fila de elegíveis — `test_elegiveis_nao_vaza_solicitacao_de_outro_tenant`. A fila não passa por `Antecipacao`: sai de `Guia::elegiveisParaAntecipacao()`, que derruba o `TenantScope` e filtra `tenant_id` na mão, então o `BelongsToTenant` não cobria esse caminho. O teste também prova que a solicitação da outra clínica **é** elegível para a dona dela — sem isso a asserção passaria mesmo com o escopo quebrado.

## 7. Achado colateral, fora do escopo desta change

- [ ] 7.1 `ConfiguracaoGlobal::doTenant()` não é seguro quando há um `TenantContext` de **outro** tenant ativo: o `firstOrCreate` roda sob o `TenantScope`, não enxerga a linha existente e esbarra no índice único de `configuracoes_globais.tenant_id`. Apareceu ao escrever 6.3, e no teste foi contornado fixando o contexto.

  Conferido antes de registrar: **não afeta produção hoje.** `TenantScope` é no-op quando o contexto é nulo, e `AvaliarAlertasJob` percorre os tenants num worker sem contexto nenhum, chamando `AvaliadorDeAlertas::avaliarTenant()`, que escopa por `withoutGlobalScope` + `where('tenant_id')`. O tiro só sai se alguém avaliar um tenant de dentro de uma requisição autenticada em outro. Fica anotado como armadilha para quem for mexer aí, não como bug aberto.
