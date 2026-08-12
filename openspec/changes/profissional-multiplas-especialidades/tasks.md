## 1. Modelo de dados

- [x] 1.1 Migration `2026_08_12_210000`: tabela `especialidade_profissional` com backfill do estado atual.
- [x] 1.2 Índice único com nome explícito — o gerado pelo Laravel passa dos 64 caracteres do MySQL.
- [x] 1.3 Relação `especialidades()` e `sincronizarEspecialidades()` no model.
- [x] 1.4 Evento `saved` garantindo a principal na ligação, para cobrir seeder e factory.

## 2. API

- [x] 2.1 `ProfissionalController` grava a lista na criação e na edição.
- [x] 2.2 Filtro por especialidade passa a usar a ligação.
- [x] 2.3 `ProfissionalResource` expõe `especialidades` e `especialidade_ids`.
- [x] 2.4 Requests aceitam `especialidade_ids`.

## 3. Interface

- [x] 3.1 Seleção múltipla na tela de Profissionais, com a principal travada.
- [x] 3.2 `SolicitacaoItensFields` oferece todos os profissionais que atendem na especialidade da linha.

## 4. Validação

- [x] 4.1 Teste do backfill: todo profissional ligado à própria principal.
- [x] 4.2 Testes de criação, edição, principal fora da lista enviada e filtro pela ligação.
- [x] 4.3 Suíte da API e `tsc`/`oxlint` do web.
- [x] 4.4 Verificação em produção, com restauração do estado original.
- [ ] 4.5 Remover a coluna `especialidade_id` e migrar os consumidores restantes.
