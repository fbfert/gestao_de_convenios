## Why

A tabela `audit_logs` existe desde o começo, mas só três pontos do sistema escrevem nela: duas gravações em `UnimedSettingsController` e uma no circuit breaker da automação. Alterar um convênio, criar um usuário, trocar as permissões de um papel, mexer numa guia — nada disso deixa rastro. Na prática o sistema não tem trilha de auditoria, tem um registro de configuração da Unimed.

A tela `/auditoria` acompanha o mesmo estado: devolve os últimos 50 registros, sem filtro, sem busca e sem paginação. Serve para olhar de relance e nada além disso.

Espalhar `AuditLog::create()` pelos controllers resolveria hoje e apodreceria amanhã: todo endpoint novo nasceria sem auditoria e ninguém notaria até precisar do histórico que não existe.

## What Changes

- Trait `Auditable` com observer do Eloquent: `created`, `updated` e `deleted` viram registro sozinhos, com o valor anterior e o novo de cada campo alterado.
- Campos sensíveis nunca entram no registro — nem o valor antigo, nem o novo. Fica gravado que o campo mudou, quem mudou e quando.
- Cobertura de configurações e segurança, cadastros, operação e acessos (login, logout e acesso negado).
- Importação de analítico registra **um** evento por lote, com arquivo, quantidade de linhas e totais; alteração manual de uma linha depois disso registra o evento dela.
- Eventos de acesso guardam IP e navegador; os demais não.
- Ação disparada por job ou pelo worker fica atribuída ao sistema, não a uma pessoa.
- Tela de auditoria com filtro por período, usuário, entidade e ação, paginação, detalhe mostrando o que mudou e exportação do resultado filtrado em CSV.
- Retenção configurável por clínica, padrão de 12 meses: um job diário exporta o lote vencido em CSV e só então apaga.
- **BREAKING**: `GET /auditoria` passa a devolver resposta paginada, e não uma lista dos últimos 50.

## Capabilities

### New Capabilities

- `trilha-de-auditoria`: registro automático de alterações, consulta com filtros e retenção com expurgo.

### Modified Capabilities

- Nenhuma.

## Non-goals

- Auditar leitura: registrar quem *abriu* uma tela multiplicaria o volume por uma ordem de grandeza e não foi pedido. Só alteração e acesso entram.
- Assinatura ou encadeamento criptográfico dos registros: a trilha é somente-acréscimo pela aplicação, o que cobre o caso de uso; prova contra adulteração no banco é outro problema.
- Reverter uma alteração a partir do registro: a trilha explica o que aconteceu, não desfaz.

## Impact

- API: novo trait e observer, `AuditController` (filtros, paginação, CSV), job de expurgo no scheduler que já existe, migration para `ip` e `user_agent` em `audit_logs` mais índice por `(tenant_id, created_at)`.
- Configurações Globais: prazo de retenção.
- Frontend: tela de auditoria reescrita.
- Operação: o CSV do expurgo vai para `storage/app/auditoria`, dentro do volume nomeado `gescon_storage` — sobrevive ao redeploy do container.
- Volume: auditar a operação inteira aumenta a escrita no banco em toda alteração. É o motivo de a retenção entrar junto, e não depois.
