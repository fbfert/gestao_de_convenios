## Why

A automação de finalização nasceu hoje. As guias que a clínica finalizou no portal da Unimed **antes** dela — à mão, ao longo de meses — continuam aparecendo no Gescon como se ainda houvesse trabalho a fazer. Não há registro nenhum aqui de que já foram encerradas lá.

Isso custa de duas formas. A operadora enxerga uma guia concluída e o Gescon enxerga a mesma guia pendente, então toda listagem de guias e de solicitações carrega um passivo que ninguém consegue distinguir do que é real. E a automação nova, se apontada para uma dessas guias, tentaria finalizar no portal o que já está finalizado.

O portal sabe a resposta: a tela **"Exames finalizados"** lista exatamente essas guias. Buscar cada número ali é uma pergunta de sim ou não — e é o tipo de pergunta que o robô já sabe fazer, porque é a mesma mecânica de filtro (`s_nr_guia` + `Button_FIltro`) que a consulta de status usa em "Exames em aberto".

## What Changes

**Conferência no portal**

- Nova operação `conferir_guia_finalizada` no worker Unimed: abre "Exames finalizados", **limpa a data inicial** do filtro (`s_dt_ini`, que vem preenchida e esconderia guias antigas), busca pelo número da guia e responde se ela aparece.
- Disparo por **botão em cada guia** e por **ação em lote** sobre as guias Unimed ainda não conferidas. O lote existe para liquidar o passivo de uma vez; o botão, para o caso avulso depois.

**Marca na guia**

- A guia ganha a marca **"Finalizada na operadora"**, com a data em que a conferência viu isso no portal, e **mantém o status que tem**. A marca convive com o badge de status, em verde claro.
- **Não vira `finalized`** e não passa por `GuiaService::finalizar()`: essas guias não têm sessão registrada, e a trava de sessão existe por um motivo que continua valendo. Forçar o status apagaria a diferença entre "finalizada com as sessões lançadas aqui" e "finalizada lá atrás, sem registro nenhum neste sistema" — e é justamente essa diferença que alguém vai precisar ver.
- Conferência que **não** encontra a guia também fica registrada, com a data. Serve para o lote seguinte saber o que já foi olhado, e para o operador ver que a guia foi conferida e não está lá.

**Efeito nas telas**

- Em **Guias**, a marca aparece ao lado do status, e é filtrável.
- Em **Solicitações**, o item cuja guia está finalizada na operadora aparece **recolhido** — especialidade, número e selo, sem os botões de ação. Um clique expande. Solicitação com todos os itens nessa situação nasce recolhida.
- Guia marcada como finalizada na operadora **não é oferecida** para finalização pela automação: o pré-voo a impede, porque finalizar de novo o que já está finalizado não é uma operação que faça sentido pedir ao portal.

**Não faz parte desta change**

- Trazer do portal qualquer dado da guia finalizada além do fato de ela estar lá (datas de execução, valores, anexos).
- Criar as sessões que faltam. A marca diz que a guia foi encerrada na operadora, não inventa o histórico que nunca foi digitado.
- Mudar o status das guias antigas, ou o que quer que dependa de `finalized` hoje (cota, antecipação, conciliação, relatórios).
- Conferência automática agendada. O disparo é sempre de alguém.

## Capabilities

### New Capabilities

- `conferencia-de-guias-finalizadas`: a conferência de uma guia em "Exames finalizados" no portal da Unimed — disparo avulso e em lote, o que fica registrado na guia em cada desfecho, e o que a marca significa.

### Modified Capabilities

- `automacao-unimed-finalizar-guia`: o pré-voo passa a impedir a finalização de guia já marcada como finalizada na operadora.
- `guia-detail`: o detalhe da guia passa a exibir a marca de finalizada na operadora e a data da conferência.

## Impact

**Código**

- `worker-unimed/src/operations/conferirGuiaFinalizada.js` (novo), registrado em `worker-unimed/src/server.js`. Reaproveita `rowByGuia` e o padrão de filtro de `statusSenha.js`; a novidade é a tela e o passo de limpar `s_dt_ini`.
- `api/app/Services/Automation/ConferirGuiaFinalizadaUnimedService.php` (novo), no padrão de `ConsultarStatusUnimedService` (lote) e `CapturarSenhaValidadeUnimedService` (avulso); `ExecutarAutomacaoUnimedJob` e `AutomationErrorCatalog` ganham a operação.
- `api/app/Services/Automation/FinalizarGuiaPreVoo.php`: novo impedimento.
- `api/app/Services/GuiaService.php`: filtro por guia finalizada na operadora.
- `web/src/features/guias/` e `web/src/features/solicitacoes/`: selo, filtro e o recolhimento do item.

**Dados**

- `guias` ganha duas colunas: a data em que a operadora confirmou a finalização e a data da última conferência (que também marca a conferência sem resultado).
- `automacao_execucoes` ganha a operação `conferir_guia_finalizada` — sem coluna nova.

**Conflito entre spec e código**

Nenhum identificado. A spec `automacao-unimed-finalizar-guia`, arquivada hoje, não previa guias já finalizadas fora do sistema; esta change acrescenta o caso em vez de contradizê-lo.
