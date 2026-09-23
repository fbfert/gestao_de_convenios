## Context

O isolamento tem hoje três camadas, e elas não cobrem as mesmas coisas:

1. `TenantScope`, global scope que filtra por `tenant_id` **quando há `TenantContext`** — alimentado pelo middleware `ResolveTenant` a partir do usuário autenticado.
2. `BelongsToTenant::resolveRouteBinding()`, que prende o `{modelo}` da URL ao tenant do usuário. Existe porque o `SubstituteBindings` roda **antes** do `ResolveTenant`: no instante em que o Laravel resolve o parâmetro, o contexto ainda está vazio e o scope é no-op.
3. Dezoito `Route::bind` manuais no `AppServiceProvider`, com `where('tenant_id', ...)` explícito — a compensação anterior ao item 2, que continua no lugar.

A varredura feita para esta change mediu a cobertura real de cada uma. Desarmando as camadas 2 e 3 e mantendo os binds manuais, **apenas uma rota vazou** (`api/analiticos/{analiticoLote}`): os outros parâmetros têm bind manual. Desarmando as três, vazaram 41 rotas de escrita, incluindo um `DELETE` que apagou registro da clínica vizinha.

Ou seja: metade do sistema tem duas defesas e a outra metade tem uma. A que tem uma funciona, e é por desenho — mas nada verifica isso continuamente.

Duas frentes ficaram fora de qualquer camada:

- **Regras `exists:`** rodam no query builder, não no Eloquent, então o global scope não se aplica. Seis pontos validam id do cliente sem recorte de clínica.
- **Fora de requisição** não há `TenantContext`. Job, comando e rotina agendada consultam sem recorte automático.

## Goals / Non-Goals

**Goals:**

- Fechar o oráculo de existência nas regras `exists:`, sem mudar nada para quem opera na própria clínica
- Ter uma verificação que descubra rotas pelo roteador, para rota nova nascer coberta
- Prender o comportamento da fila, que hoje depende de disciplina de quem escreve o job

**Non-Goals:**

- Trocar o modelo de isolamento (ADR-01 segue: banco único, coluna `tenant_id`)
- Remover os `Route::bind` manuais. São redundantes com o trait, mas redundância aqui é a defesa em profundidade — tirar seria reduzir camada em nome de elegância
- Cobrir o caminho do super admin, que atravessa clínicas **por desenho** e tem teste próprio (`ElevacaoDePrivilegioApiTest`)
- Cobrir o worker Playwright, que não consulta o banco

## Decisions

### A varredura pergunta ao roteador, e não a uma lista

Uma lista de rotas escrita à mão cobre o que alguém lembrou no dia. O modo de falha deste projeto — documentado no próprio `BelongsToTenant` — é "todo parâmetro novo nascia vazando", e uma lista manual repete esse modo de falha no teste.

A varredura usa `Route::getRoutes()`, descobre por reflexão o model de cada parâmetro (inclusive em controller invocável, onde a ação não tem `@`) e ataca todos. Parâmetro que não resolve model entra numa constante declarada, para aparecer em vez de sumir.

### Os registros da clínica vizinha saem de `replicate()`

Montar um registro campo a campo para cada um dos 59 models significaria um teste que quebra a cada coluna nova. `replicate()` de uma linha semeada dá um registro válido sem o teste saber o formato de nada. Campos únicos globalmente (`email`, `slug`, `cnpj`, `cpf`, `idempotency_key`) recebem prefixo, senão a cópia bate na constraint em vez de virar o ataque.

Para os três models que a semente não cobre — `Lancamento`, `AutomacaoExecucao`, `AnaliticoUnimedLote` — há criação explícita, porque é justamente neles que mora dado sensível e deixá-los fora seria o pior lugar para economizar.

### Toda frente tem asserção de controle

Um 404 só prova barramento se a rota responde a alguém. Rota que 404 para todo mundo passaria verde sem ter sido testada — foi o que aconteceu com duas versões iniciais destes testes:

- o ataque via `profissional_id` morria em "esta guia ainda não está aprovada", sem chegar ao trecho que resolve o profissional
- a varredura ignorava controllers invocáveis, deixando `pacientes/{paciente}/pasta` de fora

Então cada frente confere primeiro o caminho legítimo. Na escrita, o controle roda numa **cópia** do registro dentro da própria clínica, nunca no original: um `DELETE` de controle no original apagaria dado semeado de que outros testes dependem.

### O ataque é feito com o admin da clínica

Usuário sem permissão recebe 403, e 403 passaria verde com o isolamento inteiramente derrubado. O atacante precisa de permissão para chegar ao ponto onde o isolamento é a única coisa entre ele e o dado — e o teste afirma que ele **não** é super admin, senão mediria outra coisa.

### `exists:` recortado por clínica, seguindo o precedente que já existe

`RelatorioFiltrosRequest::existeNaClinica()` já resolve isto e explica o motivo em comentário. A mesma forma é aplicada aos outros seis pontos, em vez de inventar um mecanismo novo.

A consequência é uma mudança de resposta **só no caso de ataque**: id de outra clínica passa de 404 (depois da validação) para 422 (na validação), igualando-se a id inexistente. Para id da própria clínica nada muda, e é isso que os testes existentes já garantem.

### A fila é verificada pelo que o job alcança, não por leitura de código

Um teste que procure `withoutGlobalScopes` no fonte vira briga com o grep. O teste roda o job com duas clínicas povoadas e afirma que ele tocou só na que estava processando — é a garantia que interessa, e não a forma de escrevê-la.

## Risks / Trade-offs

**A varredura depende de reflexão e da semente.** Se a semente deixar de criar um model, a rota dele sai da varredura. Mitigado pelos contadores mínimos (`assertGreaterThan`): cobertura que desaba reprova em vez de silenciar.

**A varredura é um teste grande, com muitas rotas num único método.** Em troca, a mensagem de falha nomeia rota por rota o que vazou, o que é mais útil que 73 testes separados — e não haveria como gerar 73 métodos a partir do roteador sem perder isso.

**Mudar 404 para 422 é mudança observável.** Só em caso de ataque, e para melhor: parar de distinguir "não existe" de "não é sua" é o objetivo. Mas se alguma tela hoje dependesse de 404 nesse caminho, ela veria 422 — nenhuma depende, porque nenhuma manda id de outra clínica.

**A lista `ROTAS_COM_404_DE_NEGOCIO` é dívida declarada.** `reativar` responde 404 quando o convênio não tem credencial, e a cópia criada pela varredura não tem. Manter a lista explícita faz rota nova reprovar primeiro e alguém decidir, em vez de a exceção entrar sozinha.
