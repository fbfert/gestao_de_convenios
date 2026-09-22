# Tasks

Ordem deliberada: a marca primeiro (é inerte e não depende de portal), depois o worker, depois o que liga os dois, e por fim as telas.

## 1. A marca na guia

- [x] 1.1 Migration com `finalizada_na_operadora_em` e `conferida_na_operadora_em` em `guias`, ambas nulas; verificar com `migrate` e `migrate:rollback` numa base de teste
- [x] 1.2 Expor as duas datas no `GuiaResource` e a marca no `SolicitacaoResource` (junto do que o item já traz da guia); verificar com testes de feature que os campos aparecem
- [x] 1.3 Filtro `finalizada_na_operadora` na listagem de guias, no padrão dos filtros existentes de `GuiaService::listar`; verificar com teste de feature que devolve só as marcadas e que a ausência do filtro não muda nada
- [x] 1.4 Garantir que a marca não afeta cota nem elegibilidade: `Guia::aceitaLancamento()` e `sessoesDisponiveis()` continuam decidindo só pelo status; verificar com teste unitário que marcar uma guia não altera nenhum dos dois

## 2. Operação `conferir_guia_finalizada` no worker

- [x] 2.1 Criar `worker-unimed/src/operations/conferirGuiaFinalizada.js` com login e abertura de "Exames finalizados", reaproveitando o padrão de `abrirExamesAbertos`; verificar com teste contra fixture que chega na tela e falha com `TELA_EXAMES_FINALIZADOS_NAO_ABRIU` quando ela não abre
- [x] 2.2 Limpar `s_dt_ini` e **conferir que ficou vazio**, falhando com `FILTRO_DATA_NAO_LIMPO` quando não ficar; verificar com teste em que a fixture repõe a data no blur
- [x] 2.3 Filtrar por `s_nr_guia` e decidir pela presença da guia, esperando o rótulo `exame(s) encontrado(s)` como `statusSenha.js` faz; verificar com testes para guia encontrada e não encontrada
- [x] 2.4 Percorrer `payload.guias[]` num login só, acumulando o desfecho de cada uma e seguindo adiante quando uma falha; verificar com teste de lote misto (uma encontrada, uma não, uma que falha)
- [x] 2.5 Registrar a operação em `worker-unimed/src/server.js`; verificar com teste que `POST /operations/conferir_guia_finalizada` chega na operação e não no eco de mock

## 3. Service e disparo na API

- [x] 3.1 Criar `ConferirGuiaFinalizadaUnimedService` com disparo avulso e em lote, gravando `automacao_execucoes` no padrão de `ConsultarStatusUnimedService`; verificar com teste de feature usando o worker fake
- [x] 3.2 Elegibilidade do lote (convênio `unimed_rda`, não histórica, com número da operadora, ainda não conferida); verificar com testes unitários para cada condição e para o caso "nada a conferir"
- [x] 3.3 Aplicar o resultado: encontrada grava as duas datas, não encontrada grava só a da conferência, e guia que deixou de aparecer tem a marca retirada; verificar com testes de feature para os três desfechos
- [x] 3.4 Falha de uma guia no lote não apaga nem inventa marca nenhuma para ela; verificar com teste de feature de lote misto
- [x] 3.5 Adicionar a operação a `ExecutarAutomacaoUnimedJob`, `FakeUnimedWorkerClient` e `AutomationErrorCatalog`, com mensagem de operador para cada código novo; verificar com teste que cada código tem rótulo próprio
- [x] 3.6 Expor as rotas de disparo avulso e em lote com permissão; verificar com testes de feature de autorização
- [x] 3.7 Impedir no `FinalizarGuiaPreVoo` a finalização de guia já marcada; verificar com teste de feature que o pré-voo acusa o impedimento e que o disparo é recusado

## 4. Telas

- [x] 4.1 Selo "Finalizada na operadora" com tom próprio, distinto do verde de `approved`/`finalized`, exibido ao lado do status com a data da conferência; verificar abrindo uma guia marcada e uma não marcada
- [x] 4.2 Botão "Conferir na Unimed" por guia e ação de conferência em lote, com acompanhamento da execução; verificar com teste e2e do disparo avulso
- [x] 4.3 Resultado do lote informando quantas foram confirmadas, quantas não estavam e quantas falharam; verificar com teste e2e
- [x] 4.4 Filtro de guias finalizadas na operadora na tela de Guias; verificar com teste e2e
- [x] 4.5 Item recolhido em Solicitações quando a guia está marcada — especialidade, número e selo, sem as ações — com clique para expandir; verificar com teste e2e
- [x] 4.6 Solicitação com todos os itens marcados nasce recolhida, e a de itens mistos recolhe só os marcados; verificar com teste e2e para os dois casos
- [x] 4.7 Marca e data no detalhe da guia, separadas do status; verificar abrindo uma guia marcada

## 5. Fechamento

- [x] 5.1 Rodar a suíte da API, do web e do worker e deixar tudo verde; verificar com `php artisan test`, `npm test` no web e `npm test` no worker
- [x] 5.2 Rodar `openspec validate conferir-guias-finalizadas-unimed --type change --strict` sem erro
- [x] 5.3 Escrever a novidade e o trecho de manual explicando o que a marca significa e — principalmente — o que ela NÃO significa (não é o mesmo que finalizar pelo fluxo normal, não cria sessão, não muda cota); verificar que a novidade aparece na tela de novidades
- [x] 5.4 Acrescentar ao prompt de deploy o passo do primeiro lote em produção, com a conferência das três contagens antes de confiar no resultado; verificar que o documento existe e diz o que fazer quando o lote devolve zero finalizadas

## 6. Reconferência, convênio do lote e cobertura e2e real

Acrescentado depois da implementação, a partir dos três riscos que ela expôs.

- [x] 6.1 Lote aceita incluir as já conferidas, para que um lote errado custe um clique e não uma conferência avulsa por guia; verificar com testes de feature para o lote padrão (só não conferidas) e para o que inclui todas
- [x] 6.2 Botão de reconferir tudo na tela de Guias, separado do lote normal e com confirmação; verificar com teste e2e que o disparo leva o pedido de incluir as já conferidas
- [x] 6.3 O lote informa qual convênio cobriu e quantas guias de outros convênios continuam esperando; verificar com teste de feature com dois convênios automatizados
- [x] 6.4 Subir o worker de verdade na suíte e2e, apontando a credencial da Unimed para a fixture local (`UNIMED_PERMITIR_FIXTURES_LOCAIS`); verificar que o caminho API → worker → marca → tela roda de ponta a ponta
- [x] 6.5 Com o worker no ar, cobrir em e2e o selo na guia e o item recolhido em Solicitações — o que hoje só tem cobertura na API; verificar com teste e2e que a conferência marca a guia e a tela reflete
