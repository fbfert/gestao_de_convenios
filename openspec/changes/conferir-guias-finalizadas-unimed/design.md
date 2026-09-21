## Context

Ver `proposal.md` — Why. O que condiciona a solução:

- `statusSenha.js` já faz exatamente esta mecânica em **"Exames em aberto"**: `abrirExamesAbertos()` → `s_nr_guia`.fill → `Button_FIltro`.click → esperar o rótulo `exame(s) encontrado(s)` → `rowByGuia()`. Cada uma dessas linhas tem um comentário explicando um achado de produção (o `.fill()` direto no lugar de `fillIfVisible`, a espera pelo rótulo em vez de só `waitProcessing`). **"Exames finalizados" é a mesma tela com outro ícone**, mais o passo de limpar `s_dt_ini`.
- O padrão de lote existe: `consultarStatusBatch` faz um login e percorre `payload.guias`, acumulando `results[]`. `ConsultarStatusUnimedService` é o par disso do lado da API.
- `AutomacaoExecucao` já tem `payload`/`resultado` JSON e o disjuntor por credencial. Nada de tabela nova.
- `GuiaStatus` tem seis valores mais os `historico_*`. Muita coisa decide por `finalized`: `Guia::aceitaLancamento()`, `sessoesDisponiveis()`, elegibilidade de antecipação, conciliação, relatórios.
- A decisão da change `automacao-unimed-finalizar-guia`: guia Unimed só vira `finalized` pela operadora, e `finalizar()` exige sessão.

## Goals / Non-Goals

**Goals**

- Uma operação de worker a mais, reaproveitando o que `statusSenha.js` já aprendeu sobre essa tela.
- Liquidar o passivo numa ida só ao portal, sem uma execução por guia.
- Registrar o desfecho de forma que a próxima varredura saiba o que já foi olhado.
- Marca que não mexe em nada que já funciona.

**Non-Goals**

- Trazer qualquer dado da guia finalizada além do fato de ela estar lá.
- Conferência agendada.
- Reconciliar a marca com o status por algum caminho automático.

## Decisions

### 1. Marca própria, e não status

**Escolhido**: duas colunas em `guias` — `finalizada_na_operadora_em` (a data que o portal confirmou) e `conferida_na_operadora_em` (a data da última conferência, qualquer que tenha sido o desfecho). O status não é tocado.

**Alternativa descartada**: virar `finalized`, dispensando a trava de sessão neste caminho. É o que a primeira leitura do pedido sugere ("anotar como finalizadas no crud"), e foi descartado por três razões que se somam:

1. **Apaga a distinção que importa.** Depois de marcadas, não haveria como separar "guia que percorreu o ciclo aqui, com as sessões lançadas" de "guia que alguém finalizou no portal em maio e este sistema nunca viu". A segunda é passivo histórico; a primeira é trabalho feito.
2. **`finalized` tem consequências.** `Guia::aceitaLancamento()` inclui `FINALIZED`, então marcar essas guias as tornaria alvo de lançamento de sessão — o contrário do que se quer. Antecipação, conciliação e relatórios também leem o status.
3. **A trava de sessão existe por um motivo vivo.** Furá-la para este caso abriria o caminho para furá-la de novo.

**Alternativa também descartada**: status novo `finalizada_na_operadora`. Resolveria a distinção, mas obrigaria a revisar todo lugar que hoje faz `in_array($status, [...])` — e cada esquecimento vira um bug silencioso numa contagem.

**Consequência**: o "verde claro" do pedido é um **selo ao lado do badge de status**, não uma cor nova para o badge. As duas informações convivem porque são duas informações.

### 2. Duas datas, não um booleano

`conferida_na_operadora_em` existe separada de propósito. Um booleano `finalizada_na_operadora` não distinguiria "nunca conferida" de "conferida e não estava lá" — e é essa diferença que o lote precisa para não reconferir tudo do zero toda vez, e que o operador precisa para saber que a guia foi olhada.

A regra fica: `finalizada_na_operadora_em` preenchida = marcada; `conferida_na_operadora_em` preenchida com a outra nula = conferida e não estava lá; ambas nulas = nunca conferida.

**Guia que deixa de aparecer** volta a ter `finalizada_na_operadora_em` nulo. A marca afirma o que o portal diz agora, não o que disse uma vez — ver a spec.

### 3. Lote com uma execução, resultado por guia

**Escolhido**: uma `AutomacaoExecucao` para o lote inteiro, com `payload.guias[]` e `resultado.results[]`, exatamente como `consult_status_batch`. Uma ida ao portal, um login.

**Alternativa descartada**: uma execução por guia. Dá granularidade no acompanhamento, mas o passivo pode ter dezenas de guias e cada execução refaz o login — que é o passo mais lento e mais frágil do fluxo (ver o `LOGIN_TIMEOUT` de 30s em `portal.js`).

**Falha de uma guia não derruba o lote**: o worker acumula o desfecho de cada uma e segue. Mesma escolha que `consultarStatusBatch` já faz.

### 4. Elegibilidade do lote

Entram as guias de convênio `unimed_rda`, não históricas, com número da operadora, **ainda não conferidas** (`conferida_na_operadora_em` nulo). Guias já conferidas ficam de fora do lote — reconferir é o botão avulso.

Uma guia já `finalized` pelo fluxo normal **também entra**: saber que a operadora concorda é informação legítima, e a marca não vai atrapalhar nada nela.

### 5. Limpar `s_dt_ini` é o passo que não pode falhar

É a única diferença real em relação ao que `statusSenha.js` já faz, e é ela que decide se as guias antigas aparecem. Por isso o worker **confere que o campo ficou vazio** depois de limpar, e falha com `FILTRO_DATA_NAO_LIMPO` se não ficou — em vez de filtrar com a data padrão e concluir, errado, que nenhuma guia antiga está finalizada.

Esse é o modo de falha caro aqui: um falso negativo em lote marcaria dezenas de guias como "conferida, não estava lá" de uma vez.

### 6. Recolher em Solicitações é estado de tela

O item recolhido é apresentação, não dado: nada é gravado sobre estar recolhido ou expandido. Solicitação com todos os itens marcados nasce recolhida; o clique expande e vale enquanto a tela estiver aberta.

**Por quê não persistir**: um "recolhido" guardado por usuário seria preferência, e preferência que ninguém pediu é estado a mais para manter sincronizado.

## Risks / Trade-offs

- **A tela "Exames finalizados" nunca foi vista pelo worker** → o ícone (`ico16examFinalizada.gif`) e o formulário vieram da descrição de quem opera. O seletor de entrada fica isolado numa função com erro próprio (`TELA_EXAMES_FINALIZADOS_NAO_ABRIU`), como a change anterior fez com a tela de execução.
- **Falso negativo em lote é o dano assimétrico** → uma guia marcada errado como "não finalizada" some no meio de dezenas. Mitigação: a verificação de `s_dt_ini` (decisão 5), e o resultado do lote informa a contagem dos três desfechos, para um lote que devolva "0 finalizadas" chamar atenção.
- **Marca que não muda status pode confundir** → alguém pode ler "Finalizada na operadora" e esperar que a guia saia das listagens de pendência. A spec exige a data junto da marca, e o selo é visualmente distinto do badge de status; o manual precisa explicar a diferença.
- **Número da guia no Gescon divergente do portal** → a conferência responderia "não finalizada" para uma guia que está lá com outro número. Não há como distinguir isso de uma guia genuinamente não finalizada. Fica registrado como limite conhecido.
- **Guia de convênio manual não tem conferência** → nada a fazer: a tela é do portal da Unimed.

## Migration Plan

1. Migration com as duas colunas, ambas nulas. Nada muda de comportamento até alguém conferir.
2. Worker e service entram; a operação só roda por clique.
3. Em produção, rodar o lote **uma vez** e olhar a contagem dos três desfechos antes de confiar nela. Um lote que devolva zero finalizadas é sinal de que `s_dt_ini` não foi limpo — e não de que a clínica nunca finalizou nada.
4. Conferido o primeiro lote, o passivo está marcado e o botão avulso cobre o resto.

**Rollback**: as colunas são inertes. Voltar o código deixa a marca invisível sem quebrar nada; `migrate:rollback` remove as colunas se necessário.

## Open Questions

- O ícone `ico16examFinalizada.gif` é um link direto ou abre por um menu? O worker tenta os dois caminhos (o mesmo padrão de `abrirExamesAbertos`, que aceita `#exames-abertos` ou o texto do link) e falha nomeando a tela quando nenhum funciona.
