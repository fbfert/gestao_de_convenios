## Purpose

Concluir na Unimed a guia que já foi cumprida na clínica: levar ao portal as sessões registradas no Gescon e as folhas assinadas, e só dar a guia por finalizada quando a operadora tiver aceitado. É o último passo do ciclo da guia, e o único que hoje ainda é digitado à mão.

## ADDED Requirements

### Requirement: Disparo da finalização pelo operador

O sistema SHALL oferecer a finalização na operadora como ação explícita do operador sobre uma guia, e SHALL NOT dispará-la sozinho por cota atingida, por agendamento ou em lote.

O sistema SHALL oferecer a ação apenas para guia de convênio com automação Unimed, apenas quando a guia estiver em situação que aceita finalização e apenas quando houver ao menos uma sessão registrada.

O sistema SHALL impedir que duas finalizações da mesma guia corram ao mesmo tempo, e SHALL apresentar a execução em andamento em vez de abrir outra.

#### Scenario: Guia pronta para finalizar
- **WHEN** o operador acionar a finalização de uma guia de convênio com automação Unimed que já tenha sessão registrada
- **THEN** o sistema SHALL enfileirar a finalização na operadora e SHALL passar a acompanhar o andamento dela

#### Scenario: Guia sem sessão registrada
- **WHEN** a guia não tiver nenhuma sessão registrada
- **THEN** o sistema SHALL NOT oferecer a finalização, pelo mesmo motivo que já recusa o encerramento sem sessão

#### Scenario: Convênio sem automação
- **WHEN** a guia for de convênio sem automação Unimed
- **THEN** o sistema SHALL NOT oferecer a finalização na operadora, e SHALL manter a finalização manual disponível

#### Scenario: Finalização já em andamento
- **WHEN** o operador acionar a finalização de uma guia que já tem finalização enfileirada ou em execução
- **THEN** o sistema SHALL NOT abrir uma segunda execução e SHALL mostrar o andamento da que já existe

### Requirement: Preenchimento da execução no portal

O sistema SHALL localizar no portal a guia pelo número registrado no Gescon e abrir a execução correspondente.

O sistema SHALL registrar regime de atendimento ambulatorial e tipo de atendimento de outras terapias em toda finalização de guia Unimed, sobrescrevendo o que o portal trouxer pré-selecionado.

O sistema SHALL preencher os campos de data da série com as sessões registradas no Gescon, uma por campo, da mais antiga para a mais recente, levando data e hora de início de cada sessão.

O sistema SHALL abortar a finalização, sem concluir no portal, quando não conseguir localizar a guia, quando a execução não abrir ou quando o portal recusar algum valor preenchido, e SHALL registrar o que falhou de forma que o operador consiga agir.

#### Scenario: Preenchimento normal
- **WHEN** a execução da guia abrir no portal e as sessões couberem nos campos disponíveis
- **THEN** o sistema SHALL fixar regime ambulatorial e tipo outras terapias, e SHALL preencher as datas da série em ordem cronológica

#### Scenario: Portal já traz outro regime ou tipo
- **WHEN** o portal apresentar a execução com regime ou tipo de atendimento diferentes dos exigidos
- **THEN** o sistema SHALL substituí-los pelos exigidos antes de prosseguir

#### Scenario: Guia não encontrada no portal
- **WHEN** a busca pelo número não devolver a guia
- **THEN** o sistema SHALL encerrar a finalização como falha, SHALL NOT alterar a situação da guia no Gescon e SHALL informar que a guia não foi localizada

#### Scenario: Portal recusa o valor de um campo
- **WHEN** o portal recusar a data, a hora ou qualquer valor preenchido
- **THEN** o sistema SHALL encerrar a finalização como falha, SHALL preservar o que o portal respondeu e SHALL NOT concluir a finalização

### Requirement: Quantidade de sessões diferente da autorizada

O sistema SHALL comparar a quantidade de sessões registradas no Gescon com a quantidade autorizada na guia e SHALL devolver a decisão ao operador sempre que elas não coincidirem, em vez de decidir sozinho.

O sistema SHALL suspender a execução nesse ponto — antes de concluir a finalização no portal — e SHALL retomá-la a partir da escolha do operador.

#### Scenario: Menos sessões que o autorizado
- **WHEN** houver menos sessões registradas do que a guia autorizou
- **THEN** o sistema SHALL apresentar as duas quantidades e SHALL exigir confirmação do operador de que a guia deve ser finalizada com menos sessões

#### Scenario: Mais sessões que o autorizado
- **WHEN** houver mais sessões registradas do que a guia autorizou
- **THEN** o sistema SHALL apresentar as duas quantidades e SHALL oferecer ao operador enviar apenas a quantidade autorizada, da mais antiga para a mais recente, ou interromper para revisar as sessões

#### Scenario: Operador interrompe para revisar
- **WHEN** o operador escolher revisar
- **THEN** o sistema SHALL encerrar a finalização sem concluir no portal e SHALL NOT alterar a situação da guia

#### Scenario: Quantidades coincidem
- **WHEN** a quantidade registrada for igual à autorizada
- **THEN** o sistema SHALL prosseguir sem pedir decisão alguma

### Requirement: Sessões em conflito impedem o envio

O sistema SHALL conferir as sessões da guia contra as regras de agenda antes de preencher o portal, e SHALL recusar a finalização enquanto houver conflito, nomeando as sessões envolvidas.

O sistema SHALL NOT corrigir horários por conta própria para caber nas regras: a folha assinada é o documento, e enviar horário diferente do que ela registra cria divergência entre o portal e o papel.

#### Scenario: Sessões dentro das regras
- **WHEN** as sessões da guia respeitarem o intervalo mínimo e os limites diários
- **THEN** o sistema SHALL prosseguir com a finalização

#### Scenario: Sessões em conflito
- **WHEN** duas sessões da guia violarem o intervalo mínimo ou o limite diário
- **THEN** o sistema SHALL recusar a finalização, SHALL apontar quais sessões conflitam e SHALL NOT abrir a execução no portal

#### Scenario: Conflito com sessões de outra guia
- **WHEN** uma sessão da guia conflitar com sessão registrada em outra guia do mesmo paciente
- **THEN** o sistema SHALL recusar a finalização pelo mesmo caminho, nomeando a outra guia

### Requirement: Envio das folhas de registro

O sistema SHALL anexar à execução no portal todas as folhas de registro de sessões vinculadas à guia, uma por vez, antes de concluir a finalização.

O sistema SHALL encerrar a finalização como falha quando alguma folha existente não puder ser anexada, e SHALL NOT concluir a finalização com anexo faltando por erro.

#### Scenario: Uma folha
- **WHEN** a guia tiver uma única folha de registro
- **THEN** o sistema SHALL anexá-la e SHALL prosseguir

#### Scenario: Várias folhas
- **WHEN** a guia tiver mais de uma folha de registro
- **THEN** o sistema SHALL anexar todas, uma por vez, antes de concluir

#### Scenario: Falha ao anexar
- **WHEN** o portal recusar uma folha ou o envio não completar
- **THEN** o sistema SHALL encerrar a finalização como falha e SHALL NOT concluir a finalização no portal

### Requirement: Guia sem folha de registro

O sistema SHALL avisar o operador quando a guia não tiver nenhuma folha de registro anexada, e SHALL permitir finalizar mesmo assim mediante confirmação explícita.

O aviso SHALL ser mais forte para guia cuja carteirinha seja da regional que exige a folha, porque ali a ausência é irregularidade e não apenas lacuna.

#### Scenario: Guia sem folha alguma
- **WHEN** o operador acionar a finalização de uma guia sem folha de registro
- **THEN** o sistema SHALL avisar e SHALL exigir confirmação explícita antes de prosseguir

#### Scenario: Guia da regional que exige folha
- **WHEN** a guia sem folha for de carteirinha da regional que exige o envio da folha
- **THEN** o sistema SHALL informar que a regional exige a folha e SHALL exigir a mesma confirmação explícita

#### Scenario: Operador não confirma
- **WHEN** o operador não confirmar
- **THEN** o sistema SHALL NOT enfileirar a finalização

### Requirement: Modo simulação

O sistema SHALL oferecer um modo de simulação, ligado por configuração, em que a finalização percorre o portal por inteiro — localiza a guia, preenche a execução, anexa as folhas — e **para antes do passo que grava e finaliza**.

O sistema SHALL guardar evidência do que foi preenchido na simulação, de forma que uma pessoa consiga conferir no portal o que teria sido enviado.

O sistema SHALL deixar claro, em toda parte onde a execução apareça, que ela correu em simulação.

O sistema SHALL NOT alterar a situação da guia no Gescon a partir de uma execução em simulação.

#### Scenario: Simulação ligada
- **WHEN** o modo simulação estiver ligado e o operador finalizar uma guia
- **THEN** o sistema SHALL preencher e anexar tudo, SHALL parar antes de gravar e finalizar, e SHALL registrar a execução como simulada

#### Scenario: Guia não muda de situação na simulação
- **WHEN** uma execução simulada terminar com sucesso
- **THEN** o sistema SHALL NOT dar a guia por finalizada

#### Scenario: Simulação desligada
- **WHEN** o modo simulação estiver desligado
- **THEN** o sistema SHALL concluir a finalização de verdade no portal

### Requirement: Situação da guia depende da operadora

Para guia de convênio com automação Unimed, o sistema SHALL dar a guia por finalizada somente quando a finalização tiver sido concluída na operadora, e SHALL NOT oferecer a finalização manual dessas guias.

O sistema SHALL manter a guia na situação anterior quando a finalização falhar, for interrompida pelo operador ou tiver corrido em simulação.

#### Scenario: Finalização concluída
- **WHEN** a finalização na operadora concluir com sucesso fora do modo simulação
- **THEN** o sistema SHALL dar a guia por finalizada, registrando que a transição veio da automação

#### Scenario: Finalização falha
- **WHEN** a finalização terminar em falha
- **THEN** o sistema SHALL manter a guia na situação em que estava e SHALL deixar a falha visível na guia

#### Scenario: Convênio sem automação continua manual
- **WHEN** a guia for de convênio sem automação Unimed
- **THEN** o sistema SHALL continuar permitindo finalizá-la manualmente, com os dados que já exige hoje

### Requirement: Acompanhamento da finalização

O sistema SHALL apresentar ao operador o andamento da finalização enquanto ela corre, e o resultado quando terminar, sem exigir que ele recarregue a tela.

O sistema SHALL registrar cada finalização com o que foi enviado, o que a operadora respondeu e o que falhou, de forma consultável depois pela guia.

O sistema SHALL apresentar a falha em linguagem que diga ao operador o que fazer, e SHALL permitir acionar a finalização de novo depois de uma falha.

#### Scenario: Andamento visível
- **WHEN** a finalização estiver enfileirada ou em execução
- **THEN** o sistema SHALL indicar isso na guia e SHALL atualizar sozinho quando o resultado chegar

#### Scenario: Consultar depois
- **WHEN** alguém abrir uma guia que já teve finalização tentada
- **THEN** o sistema SHALL mostrar quando foi, quem acionou, o resultado e, havendo falha, o motivo

#### Scenario: Tentar de novo
- **WHEN** uma finalização terminar em falha
- **THEN** o sistema SHALL permitir acioná-la novamente sem exigir refazer o registro das sessões
