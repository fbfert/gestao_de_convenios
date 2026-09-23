## Why

O isolamento entre clínicas é o ADR-01, e hoje ele funciona: uma varredura de 73 rotas da API não encontrou nenhum registro de outra clínica alcançável, nem para ler nem para escrever. Mas duas coisas ficaram descobertas, e as duas importam porque produção vai deixar de ter um único tenant.

A primeira é um **oráculo de existência**: seis regras de validação `exists:` não recortam por clínica, então um id de outra clínica passa na validação e só depois é barrado — 404 —, enquanto um id inexistente é recusado na validação — 422. A diferença entre as duas respostas permite descobrir, um número por vez, o que existe na base de outra clínica. Não é acesso ao dado; é confirmação de que ele existe. `RelatorioFiltrosRequest` já resolve isso e explica por quê, então o padrão existe e não está aplicado nos outros.

A segunda é a **fila**. Fora de requisição HTTP não há `TenantContext`, e sem contexto o `TenantScope` é no-op: toda consulta Eloquent dentro de um job lê todas as clínicas, a menos que alguém tenha passado o tenant à mão. Os jobs de hoje passam, e comentam por quê — mas isso é disciplina de quem escreveu, não garantia verificada. Nada reprova se o próximo job esquecer.

## What Changes

- Regras `exists:` de id vindo do corpo ou da query passam a recortar por clínica, para a validação não distinguir "não existe" de "não é sua"
- Nova varredura automática que ataca toda rota da API com registro de clínica vizinha — leitura, listagem e escrita —, descobrindo as rotas pelo roteador em vez de por lista mantida à mão
- Novo teste que prende o comportamento da fila: job que consulta sem recorte explícito de clínica reprova
- Nenhuma mudança no fluxo de quem usa o sistema: para id da própria clínica, todas as respostas continuam as mesmas

## Capabilities

### New Capabilities
- `isolamento-entre-clinicas`: o que o sistema garante sobre uma clínica não alcançar dado de outra, e o que precisa ser verificado para essa garantia não depender de disciplina

### Modified Capabilities
<!-- Nenhuma: as capacidades existentes não mudam de comportamento para quem opera
     dentro da própria clínica, que é o único caso que elas descrevem. -->

## Impact

- `api/app/Http/Requests/`: `StoreLancamentoRequest`, `UpdateLancamentoRequest`, `ListConciliacaoRequest`, `ImportLancamentosTranscricaoRequest`
- `api/app/Http/Controllers/`: `LancamentoController` (validação em linha), `SolicitacaoController` (validação em linha)
- `api/tests/Feature/VazamentoEntreClinicasTest.php` (novo), `IsolamentoNaFilaTest.php` (novo)
- Sem migration, sem dependência nova, sem mudança de rota
