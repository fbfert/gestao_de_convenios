## 1. API

- [x] 1.1 `GuiaController@index`: aceitar `profissional_id` e `paciente_nome` na whitelist de filtros repassada ao serviço.
- [x] 1.2 `GuiaService::listar`: filtrar por `profissional_id` (igualdade) e por `paciente_nome` (`whereHas('paciente', LIKE '%nome%')`).

## 2. Frontend

- [x] 2.1 `GuiasPage`: trocar o dropdown "Paciente" do filtro por dropdown "Profissional", com lista de profissionais ativos independente do formulário de criação.
- [x] 2.2 `GuiasPage`: adicionar campo de texto "Paciente" no filtro, aplicado junto do botão "Aplicar".
- [x] 2.3 `GuiaFilters` (types.ts): trocar `paciente_id` por `profissional_id` e `paciente_nome`.

## 3. Validação

- [x] 3.1 Teste de API cobrindo os dois filtros novos (`GuiasApiTest::test_filtra_guias_por_profissional_e_por_nome_do_paciente`).
- [x] 3.2 `php vendor/bin/phpunit --filter=GuiasApiTest` passando.
- [x] 3.3 `tsc -b` e `oxlint` no frontend passando.
- [ ] 3.4 `openspec validate` — CLI do OpenSpec não disponível neste ambiente; spec revisada manualmente contra o formato usado em `listagens-ordenaveis`.
