## Contexto

Fases 3 a 5 da estratégia acordada em 2026-08-13; as fases 1 e 2 saíram no
change `perfis-e-permissoes`.

Decisões desta rodada: campo sensível registra só que mudou, quem mudou e
quando; importação de analítico gera um evento por lote; IP e navegador apenas
nos eventos de acesso; o expurgo exporta em CSV antes de apagar; quem vê a
trilha continua sendo quem tem `dashboard.auditoria`.

## 1. Base do registro

- [x] 1.1 Migration: `ip` e `user_agent` em `audit_logs` (nullable) e índice `(tenant_id, created_at)` para o filtro por período e para o expurgo.
- [x] 1.2 Trait `Auditable` com observer de `created`, `updated` e `deleted`, gravando só os campos que mudaram.
- [x] 1.3 Autor pela requisição; sem usuário no contexto (job, worker, console), o evento fica atribuído ao sistema.
- [x] 1.4 Não gravar evento quando a gravação não altera campo nenhum.

## 2. Censura de campos sensíveis

- [x] 2.1 Lista declarada por entidade (`$auditOcultos`).
- [x] 2.2 Filtro por padrão de nome, para campo sensível novo não vazar por esquecimento. **`senha`, `chave` e `key` soltos ficaram de fora**: neste domínio "senha" é o código de autorização do convênio (`guias.senha`, `validade_senha`, `senha_alerta_dias`, `chave_conciliacao`), e escondê-los tiraria da trilha justamente o que ela precisa mostrar. A lista ficou em `password`, `passwd`, `secret`, `api_key`, `apikey`, `private_key`, `access_token`, `refresh_token`, `token`, `credential`.
- [x] 2.3 Payload registra o nome do campo oculto sem valor anterior nem novo.
- [x] 2.4 Teste que altera senha da Unimed, chave da OpenAI e senha do SMTP e confirma que nenhum valor aparece na trilha.

## 3. Cobertura

- [x] 3.1 Configurações e segurança: globais, e-mail, IA, Unimed, papéis, permissões e usuários.
- [x] 3.2 Cadastros: convênios com regras e valores, pacientes, profissionais, especialidades e médicos.
- [x] 3.3 Operação: solicitações, guias, sessões, antecipações e conciliações.
- [x] 3.4 Acessos: login, logout e recusa por falta de permissão, com IP e navegador.
- [x] 3.5 Importação de analítico: um evento por lote, com arquivo, quantidade de linhas e totais.
- [x] 3.6 Substituir as três chamadas manuais que já existem (`UnimedSettingsController`, `UnimedCircuitBreakerService`) pelo caminho novo, sem perder o que elas registravam.

## 4. Consulta

- [x] 4.1 `GET /auditoria` com filtro de período, usuário, entidade e ação, paginado (BREAKING: deixa de ser lista dos últimos 50).
- [x] 4.2 Detalhe do evento mostrando campo a campo o antes e o depois, e os ocultos como "alterado".
- [x] 4.3 Exportar CSV do resultado filtrado, sem valores sensíveis.
- [x] 4.4 Tela reescrita: filtros, paginação, detalhe e botão de exportar.
- [x] 4.5 Garantir que a trilha é somente-acréscimo — nenhuma rota altera ou exclui registro.

## 5. Retenção

- [x] 5.1 Prazo de retenção em Configurações Globais, padrão 12 meses.
- [x] 5.2 Job diário no scheduler: exporta o lote vencido em `storage/app/auditoria` e só então apaga.
- [x] 5.3 Falha na exportação aborta o expurgo daquele lote.
- [x] 5.4 Registrar na própria trilha que houve expurgo, com o intervalo e a quantidade.

## 6. Validação

- [x] 6.1 Testes de API: registro automático, censura, autor sistema, lote da importação, filtros, CSV e expurgo.
- [x] 6.2 `openspec validate trilha-de-auditoria --type change --no-interactive`.
- [x] 6.3 `tsc -b`, `oxlint`, `vite build` e `php artisan test` (213 testes, 1027 asserções).
- [ ] 6.4 Rodar a suíte e2e do Playwright — o servidor não tem Node e PHP fora dos containers.

## 7. Ajustes pedidos depois de ver a tela (2026-08-13)

- [x] 7.1 Busca por nome da pessoa no lugar do seletor de usuário (LIKE sobre `users`, tabela pequena, e não sobre a trilha).
- [x] 7.2 Seletor `Autor`: todos, somente pessoas ou somente o sistema — preserva o recorte do que job, worker e expurgo fizeram sozinhos.
- [x] 7.3 Seletor `Tipo de ação` (Acesso, Criação, Alteração, Exclusão, Importação, Manutenção), com o seletor de `Ação` mostrando só o que pertence ao tipo escolhido.
- [x] 7.4 `AuditoriaCatalogo` com rótulo legível de ação e de entidade, usado pela tela, pelos filtros e pelo CSV — a mesma fonte para o que o seletor promete e o que a consulta filtra.

## 8. Achados durante a implementação

- `audit_logs` não tinha `ip` nem `user_agent` no `$fillable`, então o mass assignment descartava os dois em silêncio. Só apareceu porque o teste de acesso conferiu o IP gravado.
- O filtro de usuário da tela ia consumir `GET /usuarios`, que exige `usuarios.manage`. Quem audita pode não ter essa permissão, e o 403 viraria evento na trilha só por abrir a tela. Os autores passaram a sair da própria trilha, via `/auditoria/opcoes`.
- `withCount('users')` do Spatie não funciona em `Role` (a relação depende do `guard_name` da instância) — contorno já usado no change anterior, repetido aqui na contagem por subconsulta.
