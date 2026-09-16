> Implementado em 16/09/2026. Suíte da API verde: 672 testes, 2563 asserções.
> `npx tsc -b`, `npm run lint` e `npm run build` no `web/` sem erro; `pint` passou
> nos arquivos tocados.

## 1. API — desfazer uma dispensa

- [x] 1.1 `AntecipacaoService::desfazerIgnorada()` — apaga o registro quando `status === ignorada`; recusa com `ValidationException` quando `gerada`. Provado por `AntecipacoesApiTest::test_desfazer_recusa_antecipacao_gerada`.
- [x] 1.2 `AntecipacaoController::desfazer()` devolvendo 204.
- [x] 1.3 Rota `DELETE /antecipacoes/{antecipacao}/ignorada` com `permission:antecipacoes.manage`. `DELETE /antecipacoes/{antecipacao}` continua inexistente — `test_historico_nao_pode_ser_apagado` segue verde no 405.

## 2. API — busca no histórico

- [x] 2.1 `ListarAntecipacoesRequest` validando `status`, `paciente_nome`, `numero_guia`, `convenio_id`, `data_de`, `data_ate` e `per_page`. `data_ate` exige `after_or_equal:data_de` — provado por `test_historico_recusa_periodo_invertido`.
- [x] 2.2 `AntecipacaoService::listar()` aplicando os filtros sobre a solicitação de origem. `numero_guia` casa com qualquer guia da solicitação de origem — que é onde as guias geradas também nascem, já que a antecipação cria itens na MESMA solicitação, e é o que torna um registro `ignorada` (sem guia gerada) pesquisável por número de guia. Provado por `test_historico_filtra_por_paciente_convenio_guia_e_periodo`.
- [x] 2.3 `AntecipacaoController::index()` recebendo o request validado. Combinação de critérios provada por `test_historico_combina_criterios`.

## 3. Web — confirmar ignorar

- [x] 3.1 `IgnorarAntecipacaoModal` com paciente, convênio, data prevista e campo opcional de motivo.
- [x] 3.2 Envia `data_alvo` e `observacoes` no `POST /antecipacoes/ignorar` — provado por `test_ignorar_grava_data_alvo_e_motivo`.

## 4. Web — desfazer

- [x] 4.1 `useDesfazerAntecipacaoIgnorada`, invalidando `['antecipacoes']` inteiro: o efeito aparece nos dois lados da tela (sai do histórico, volta aos elegíveis).
- [x] 4.2 Botão "Desfazer" só em linha `ignorada` e só com `antecipacoes.manage`, passando por `useConfirm`. O retorno à fila é provado por `test_desfazer_ignorada_apaga_e_devolve_a_solicitacao_a_fila`; a negação por `test_desfazer_sem_permissao_de_gerir_recusa`.

## 5. Web — busca, paginação e persistência

- [x] 5.1 `useListaNaUrl` no lugar do `useState` de página/status.
- [x] 5.2 Formulário de busca: paciente, nº guia, convênio, De/Até, status, com Aplicar e Limpar.
- [x] 5.3 Componente `Paginacao` compartilhado (Anterior/Próxima + "ir para página").
- [x] 5.4 Links do histórico e do modal de origem levam `state.from` com a URL filtrada — é o que `GuiaDetalhePage` e `SolicitacaoEditarPage` já leem para o "Voltar".

## 6. Web — contexto nas linhas

- [x] 6.1 `Tooltip` (ícone de lupa) ao lado de "Paciente · Convênio" no histórico, com `AntecipacaoTooltipDetalhe`.
- [x] 6.2 `OrigemElegivelModal` aberto ao clicar em "Paciente · Convênio" nos elegíveis.

## 7. Testes

- [x] 7.1 `test_desfazer_ignorada_apaga_e_devolve_a_solicitacao_a_fila`
- [x] 7.2 `test_desfazer_recusa_antecipacao_gerada`
- [x] 7.3 `test_desfazer_sem_permissao_de_gerir_recusa`
- [x] 7.4 `test_historico_filtra_por_paciente_convenio_guia_e_periodo`, `test_historico_combina_criterios`, `test_historico_recusa_periodo_invertido`
- [x] 7.5 Suíte da API verde (672 testes).

## 8. Cobertura de navegador

- [x] 8.1 `web/tests/e2e/antecipacoes.spec.ts` — a tela não tinha e2e nenhum, nem antes desta change. Três cenários, suíte inteira verde (21 testes):
  - **ignorar/desfazer**: o primeiro clique em Ignorar abre a confirmação em vez de agir; cancelar mantém a entrada na fila; confirmar com motivo manda para o histórico; o Desfazer também confirma antes, e confirmado devolve a solicitação aos elegíveis.
  - **busca**: filtra por paciente, por número de guia (achando um registro `ignorada`, que não gerou guia — casa pelas guias da origem) e por período; Limpar devolve tudo; e o critério sobrevive a sair da listagem e voltar, com o campo repreenchido.
  - **contexto**: o modal de origem abre pelo nome do elegível e fecha sem gerar nem dispensar; o tooltip do histórico mostra status, solicitação de origem, "nenhum item ou guia foi criado" e o motivo.

  Duas armadilhas que custaram iteração, anotadas para o próximo:
  - O painel do `Tooltip` é `aria-hidden` de propósito (o texto para leitor de tela vai no `sr-only` do `aria-describedby`), então `getByRole('tooltip')` **não** o encontra — o seletor tem de ser o CSS `[role="tooltip"]`.
  - Abrir o tooltip é `hover()`, não `click()`: no mouse o painel abre no `pointerenter` e o clique é o toggle, e o `.click()` do Playwright dispara os dois no mesmo gesto — abriria e fecharia.
  - O botão "Fechar" do rodapé do modal de origem ganhou `data-testid`: o X do cabeçalho tem o mesmo nome acessível, e o papel casava com os dois.
