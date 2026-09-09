## Context

`guias.status` é lido por praticamente toda a esteira e escrito por oito lugares (a lista está no `proposal.md`). Três deles — automação, confirmação de guia incerta e importação — gravam status junto com dez outros campos, num único `fill()`. Qualquer desenho que exija "salve o status separado do resto" quebraria esses três ou os deixaria de fora, que é o mesmo que não ter trava.

## Goals / Non-Goals

**Goals:**
- Um único método de escrita de status, usado pelos oito pontos.
- Histórico fiel: de, para, quando, quem, de onde.
- `negada_em` e `aprovada_em` sempre coerentes com o histórico.
- Uma trava que reprove escrita nova por fora, e não apenas as oito de hoje.

**Non-Goals:**
- Reescrever a validação de transição (quem pode ir de onde para onde continua onde está).
- Histórico das outras entidades.
- Série temporal agregada.

## Decisions

- **O histórico é a verdade; `negada_em`/`aprovada_em` são cache.** Racional: não é redundância, é diferença de custo de leitura. O card do dashboard faz polling de 30s e precisa de uma coluna indexada; o gráfico, quando existir, varre o histórico. Os dois são escritos pela mesma transação, então não podem divergir.

- **`registrarTransicao` aceita o modelo ainda não salvo.** Assinatura: `registrarTransicao(Guia $guia, string $para, array $contexto = [])`. O chamador preenche todos os outros campos com `fill()` e chama o método, que aplica o status e salva. Racional: é a única forma que serve igualmente para os três sites que criam a guia inteira de uma vez e para os que só mudam o status. `de` sai de `getOriginal('status')`, que é `null` quando a guia está nascendo — o que já é a semântica desejada para a primeira linha do histórico.

- **A trava é um observer no model, e não varredura estática do código.** Racional: a varredura reprova o que se parece com o padrão conhecido, e um jeito novo de escrever passa batido — exatamente o caso que a trava existe para pegar. O observer reprova pelo *efeito*: `isDirty('status')` fora do método, independentemente de como o código chegou ali. O custo é o único furo abaixo.

- **A trava vale para MUDANÇA de status; criação é registrada, não recusada.** Racional: o risco que a trava existe para eliminar é a transição que não deixa rastro, e na criação não há status anterior a perder — a primeira linha do histórico é derivável do próprio registro que nasce. Recusar a criação obrigaria todo seeder, fábrica e teste a passar pelo serviço (32 pontos só na suíte) sem ganhar um grama de fidelidade. Então o observer `created` grava a linha inicial quando a criação veio de fora, e se cala quando veio de `registrarTransicao` — que é onde a linha nasce com origem e usuário de verdade. O observer `updating` continua recusando qualquer mudança por fora.

- **Furo conhecido e aceito: `update()` em massa pelo query builder não dispara eventos de model.** `Guia::query()->where(...)->update(['status' => ...])` escaparia do observer. Hoje não existe nenhuma ocorrência dessas no repositório, e a alternativa (proibir por varredura estática) traz de volta a fragilidade que se quis evitar. Registrado aqui para que quem introduzir a primeira saiba que está fora da rede.

- **O token da trava é um contador, não um booleano.** Racional: `registrarTransicao` roda dentro de uma transação e pode ser chamado de dentro de outro fluxo que já esteja com a permissão aberta; um booleano seria fechado pelo `finally` do interno e deixaria o externo sem permissão pelo resto da operação.

- **`origem` é dado do chamador, não inferido.** `manual | automacao | importacao | migracao`. Racional: inferir por `auth()->user()` daria "manual" para um job que rodasse com usuário resolvido e "automacao" para uma ação humana disparada por fila. Quem chama sabe, e é barato dizer.

- **`user_id` nulo quando é robô.** Preenchido com `auth()->id()` só quando a origem é `manual`.

- **Sem `updated_at` na tabela de histórico.** Linha de histórico não é editada; `ocorrido_em` é o único tempo que importa.

## Risks / Trade-offs

- **[Migrar oito call sites de uma vez]** -> É a parte arriscada da entrega, e a rede é a suíte existente: automação, importação e a esteira de solicitação já têm testes de feature que passam pelo status. Se algum ponto ficar de fora, o observer o transforma em exceção no primeiro teste que o exercitar — a trava serve tanto de guarda quanto de detector durante a própria migração.

- **[O observer pode reprovar código legítimo de teste]** -> Fábrica e seeder que criam guia com status também passam a precisar da permissão. É incômodo, mas é o preço de a trava valer para todos; alternativa seria isentar por ambiente, e trava que não vale em teste não é testável.

- **[Backfill a partir de `audit_logs` é incompleto por natureza]** -> A trilha guarda o diff, então dá para reconstruir transições auditadas — mas o `ExpurgarAuditoriaJob` apaga por `auditoria_retencao_meses`, e o que já foi expurgado não volta. Por isso `origem='migracao'`: a linha fica marcada como reconstruída, e não como observada. O command relata quantas guias ficaram sem histórico e segue.

- **[Duas negações seguidas]** -> A segunda vira uma linha nova no histórico (o histórico é append-only) e sobrescreve `negada_em`. É a semântica correta para as duas leituras: "quando foi negada pela última vez" para o card, "todas as vezes em que foi negada" para a série.
