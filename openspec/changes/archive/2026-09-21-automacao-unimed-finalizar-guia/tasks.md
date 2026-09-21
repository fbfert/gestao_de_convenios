# Tasks

Ordem deliberada: regras de agenda e anexos primeiro, porque valem por si e a finalização depende delas. A automação entra depois, com simulação ligada.

## 1. Regras de agenda das sessões

- [x] 1.1 Criar o avaliador de conflitos de agenda (intervalo de 50 min entre inícios, limite de 8/dia ABA e 1/dia não-ABA por especialidade, mesmo horário em especialidades diferentes), recebendo sessões candidatas e devolvendo conflitos tipados com as duas sessões e a guia envolvida; verificar com testes unitários cobrindo cada tipo de conflito, o caso sem conflito e sessão sem `hora_inicio`
- [x] 1.2 Reconhecer especialidade ABA pelo nome, normalizando acentuação e caixa e exigindo `ABA` como palavra isolada; verificar com teste unitário cobrindo `Terapia ABA`, `Fisioterapia ABA`, `aba`, `A.B.A.` e um nome que só contém as letras (`Abagail`)
- [x] 1.3 Consultar as sessões `completed` já gravadas do paciente na janela de dias envolvida, em qualquer guia, e somá-las às candidatas do lote; verificar com teste que detecta conflito entre lote novo e sessão de outra guia, e que ignora sessão cancelada/não comparecida
- [x] 1.4 Detectar choque de agenda do profissional (mesmo executante, mesma data e hora, paciente diferente) e devolvê-lo como aviso separado dos conflitos; verificar com teste que o aviso não entra na lista de bloqueios
- [x] 1.5 Aplicar o avaliador na confirmação da folha de registro, recusando com a lista completa de conflitos; verificar com teste de feature que a confirmação retorna erro de validação nomeando as sessões e não grava nada
- [x] 1.6 Aplicar o avaliador no cadastro avulso e na edição de sessão; verificar com testes de feature para os dois caminhos
- [x] 1.7 Garantir que conflito de agenda não aceita justificativa, diferente da divergência de paciente; verificar com teste que enviar justificativa junto de conflito continua recusando

## 2. Conflitos na tela de sessões

- [x] 2.1 Marcar na grade de conferência as linhas em conflito, com o motivo e a sessão/guia contra a qual bateu; verificar abrindo a importação com duas sessões a menos de 50 min e observando as duas linhas marcadas
- [x] 2.2 Permitir corrigir data e hora na grade e reconferir a cada alteração, liberando a confirmação quando nenhum conflito restar; verificar com teste e2e que corrige o horário e confirma com sucesso
- [x] 2.3 Exibir o aviso de choque do profissional sem bloquear a confirmação; verificar com teste e2e que o aviso aparece e a confirmação passa

## 3. Alerta no dashboard

- [x] 3.1 Contar guias cujas sessões registradas violam intervalo mínimo ou limite diário e expor no card de alertas do grupo Guias; verificar com teste de feature que a contagem aparece e some quando não há conflito
- [x] 3.2 Ligar o alerta à listagem filtrada dessas guias; verificar clicando no card e conferindo que a lista traz as guias contadas

## 4. Várias folhas de registro por guia

- [x] 4.1 Aceitar vários arquivos em `pdf_registro_sessoes` na confirmação da importação, gravando cada um como arquivo do paciente vinculado à guia; verificar com teste de feature enviando dois arquivos e conferindo os dois registros com `metadata.guia_id`
- [x] 4.2 Criar o anexo avulso de folha a uma guia já confirmada (endpoint + permissão); verificar com teste de feature que a folha entra sem exigir nova confirmação de sessões
- [x] 4.3 Listar as folhas da guia com data de envio e quem enviou, e permitir remover enquanto a guia não estiver finalizada na operadora; verificar com testes de feature para listar, remover e recusar remoção de guia finalizada
- [x] 4.4 Manter a exigência de ao menos uma folha na regional que a pede, aceitando uma só para confirmar; verificar com teste de feature que a confirmação sem folha continua recusada e que uma folha basta
- [x] 4.5 Mostrar as folhas anexadas na guia e permitir anexar mais pela interface; verificar abrindo uma guia com duas folhas e anexando uma terceira

## 5. Operação `finalizar_guia` no worker

- [x] 5.1 Criar `worker-unimed/src/operations/finalizarGuia.js` com login, busca da guia pelo número e abertura da execução; verificar com teste contra fixture local que a operação chega na tela de execução e falha com erro nomeado quando a guia não é encontrada
- [x] 5.2 Fixar regime ambulatorial e tipo outras terapias, sobrescrevendo o que a tela trouxer; verificar com teste que parte de uma fixture com outros valores selecionados e confere os finais
- [x] 5.3 Ler `QT_AUTORIZADA_1` e falhar com `QT_AUTORIZADA_DIVERGENTE` quando não bater com a quantidade enviada pela API; verificar com teste de divergência
- [x] 5.4 Preencher `dt_serie_N` em ordem cronológica com data e hora de início, conferindo o valor que ficou no campo e falhando com `DT_SERIE_FORMATO_RECUSADO` ou `DT_SERIE_CAMPO_INDISPONIVEL`; verificar com testes para o caminho feliz, para campo que rejeita o valor e para campo desabilitado
- [x] 5.5 Anexar as folhas uma a uma, reabrindo a popup a cada arquivo e conferindo que cada folha aparece na lista, com falha `ANEXO_NAO_CONFIRMADO` nomeando a que faltou; verificar com testes para uma folha, duas folhas e falha de confirmação
- [x] 5.6 Concluir com Gravar e Finalizar quando não for simulação, e, sendo simulação, parar antes desse passo devolvendo `simulado: true` com screenshot; verificar com testes para os dois modos, garantindo que o modo simulação não aciona o clique final
- [x] 5.7 Registrar a operação em `worker-unimed/src/server.js`; verificar com teste do servidor que `POST /operations/finalizar_guia` roteia para a operação

## 6. Service e disparo na API

- [x] 6.1 Criar o pré-voo da finalização (convênio com automação, situação da guia, ao menos uma sessão, regras de agenda, quantidade autorizada, folhas anexadas), devolvendo o que exige decisão do operador; verificar com testes unitários para cada condição
- [x] 6.2 Criar `FinalizarGuiaUnimedService` gravando `automacao_execucoes` com operação `finalizar_guia`, idempotência e eventos, no padrão de `GerarGuiaUnimedService`; verificar com teste de feature usando o worker fake
- [x] 6.3 Recusar o disparo quando uma condição detectada no pré-voo não vier confirmada (`confirmar_menos_sessoes`, `limitar_ao_autorizado`, `confirmar_sem_anexo`); verificar com testes de feature para cada confirmação faltando
- [x] 6.4 Impedir duas finalizações simultâneas da mesma guia, devolvendo a execução em andamento; verificar com teste de feature disparando duas vezes
- [x] 6.5 Enviar ao worker as sessões finais e os caminhos locais das folhas, limitando à quantidade autorizada quando o operador escolher isso; verificar com teste de feature conferindo o payload montado
- [x] 6.6 Adicionar a operação a `UnimedWorkerClient`, `FakeUnimedWorkerClient` e `AutomationErrorCatalog`, com mensagem de operador para cada código de erro novo; verificar com teste que cada código tem tradução
- [x] 6.7 Expor a rota de disparo e a de pré-voo com permissão; verificar com testes de feature de autorização

## 7. Situação da guia e modo simulação

- [x] 7.1 Adicionar a configuração global `automacao_finalizar_guia_simulacao_ativo`, ligada por padrão, e propagá-la ao payload do worker; verificar com teste de feature que o payload leva `simular` conforme a configuração
- [x] 7.2 Guardar `GuiaService::finalizar()` contra finalização manual de guia `unimed_rda`, e criar o caminho interno usado pela automação que faz o mesmo bookkeeping registrando origem automação; verificar com testes unitários para os dois convênios e com teste do histórico de status
- [x] 7.3 Transicionar a guia para finalizada apenas quando a execução concluir com sucesso fora da simulação; verificar com testes de feature para sucesso real, sucesso simulado e falha
- [x] 7.4 Manter a guia intacta e a falha visível quando a execução falhar, permitindo reacionar; verificar com teste de feature que a segunda tentativa é aceita após falha

## 8. Interface da finalização

- [x] 8.1 Condicionar o botão da tela de Sessões ao convênio: finalização na operadora para `unimed_rda`, finalização manual para os demais; verificar com teste e2e para os dois casos
- [x] 8.2 Montar os diálogos de decisão a partir do pré-voo (menos sessões que o autorizado, mais que o autorizado, nenhuma folha anexada), enviando as confirmações no disparo; verificar com testes e2e para cada diálogo
- [x] 8.3 Recusar a finalização com conflito de agenda, apontando as sessões e sem oferecer caminho para prosseguir; verificar com teste e2e
- [x] 8.4 Acompanhar o andamento da execução e atualizar a guia sozinho quando o resultado chegar; verificar com teste e2e usando o worker fake
- [x] 8.5 Mostrar o histórico de finalizações na guia (quando, quem acionou, resultado, motivo da falha) e marcar claramente as execuções em simulação; verificar abrindo uma guia com execução simulada e uma com falha
- [x] 8.6 Retirar o Finalizar das telas de guia e manter ali Aprovar e Negar, alinhando com a spec de `guia-detail`; verificar com teste e2e que a lista e o detalhe não oferecem finalizar

## 9. Fechamento

- [x] 9.1 Rodar a suíte da API, do web e do worker e deixar tudo verde; verificar com `php artisan test`, `npm test` no web e `npm test` no worker
- [x] 9.2 Rodar `openspec validate --changes automacao-unimed-finalizar-guia --strict` e deixar sem erro
- [x] 9.3 Escrever a novidade do produto e o trecho de manual cobrindo as regras de agenda, as várias folhas e a finalização na Unimed; verificar que a novidade aparece na tela de novidades
- [x] 9.4 Escrever o roteiro de homologação em produção em `docs/automacao-unimed/`, cobrindo a primeira execução em simulação, o que conferir no portal e como desligar a simulação; verificar que o documento existe e lista os códigos de erro esperados da primeira rodada
