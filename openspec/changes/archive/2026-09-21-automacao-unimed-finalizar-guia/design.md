## Context

Ver `proposal.md` — Why. O que importa aqui é o que já existe e condiciona a solução:

- O worker Unimed (`worker-unimed/`) é um serviço HTTP que expõe `POST /operations/<nome>` e hoje atende `gerar_guia`, `consult_status_batch`, `capture_authorization_data_batch` e `confirmar_guia_incerta`. Cada operação abre um Playwright próprio, faz login e devolve `{status, error_code?, message?, ...}`. `worker-unimed/src/portal.js` concentra login, popups, espera de processamento e helpers de preenchimento.
- Do lado da API, cada operação tem um service em `api/app/Services/Automation/`, todos gravando em `automacao_execucoes` (com `payload`/`resultado` JSON, `idempotency_key`, `parent_id` e eventos em `automacao_eventos`). Os status já em uso são `queued`, `running`, `uncertain`, `needs_attention`, `succeeded`, `failed`.
- `gerarGuia.js` já sobe anexo no portal: clica em `#item_anexos_1`, espera a popup por `waitForPopup`, faz `setInputFiles` e confirma. O upload de folha de registro na finalização é o mesmo padrão, em outra tela.
- A guia no Gescon já tem `sessoes_autorizadas`, e as sessões (`lancamentos`) já têm `data_sessao` + `hora_inicio`.
- A folha de registro já é guardada como `PacienteArquivo` com `tipo = 'registro_sessoes'` e `metadata.guia_id` — a estrutura para N folhas por guia já existe; o que falta é aceitar mais de uma e listá-las.
- `connector_driver = 'unimed_rda'` é o discriminador de convênio com automação, já usado em `EnfileirarConsultasUnimedDueJob` e `CapturarSenhaValidadeUnimedService`.

Restrição forte: **não há ambiente de homologação da Unimed**. Tudo é construído local contra fixtures e só encontra o portal real em produção. O HTML de duas telas (a execução preenchida e a popup de anexos) não está disponível hoje.

## Goals / Non-Goals

**Goals**

- Uma operação de worker a mais, no mesmo formato das existentes — sem novo transporte, sem nova tabela.
- Decisões que dependem de gente acontecem **antes** de o navegador abrir, não no meio da execução.
- Todo passo cujo HTML não conhecemos fica isolado atrás de um seletor nomeado e um erro próprio, para que a primeira execução em produção diga exatamente onde parou.
- O clique irreversível é o último, e pode ser desligado por configuração.

**Non-Goals**

- Reaproveitar a sessão de navegador entre operações (cada uma continua abrindo a sua).
- Fila/retentativa automática da finalização: falhou, o operador reaciona.
- Generalizar a finalização para outros conectores.

## Decisions

### 1. Operação única `finalizar_guia`, sem protocolo de suspensão

**Escolhido**: a API resolve tudo o que depende de decisão humana *antes* de chamar o worker. O worker recebe a lista final de sessões e a lista final de anexos, e executa do início ao fim sem parar para perguntar.

**Alternativa descartada**: suspender a execução no meio (status `needs_attention`), devolver a divergência e retomar por uma operação filha, como `confirmar_guia_incerta` faz. Faz sentido lá, porque a incerteza só aparece depois do submit e não havia como prever. Aqui a divergência de quantidade é conhecida antes de abrir o navegador — `sessoes_autorizadas` está no Gescon. Suspender no meio significaria manter um navegador vivo ou refazer o login na retomada, com um estado a mais para errar.

**Consequência**: a checagem de quantidade acontece duas vezes. A API compara `sessoes_autorizadas` com as sessões registradas e pede a decisão ao operador; o worker lê `QT_AUTORIZADA_1` na tela e, se não bater com o que a API mandou, **falha** com `QT_AUTORIZADA_DIVERGENTE` em vez de adivinhar. Divergência entre o Gescon e o portal é um problema de dado, não de execução.

### 2. Pré-voo na API como porta única

Antes de enfileirar, a API confere, nesta ordem: convênio é `unimed_rda`; guia aceita finalização; há ao menos uma sessão; as sessões passam nas regras de agenda; a quantidade bate com a autorizada; há ao menos uma folha anexada. As três últimas podem ser respondidas pelo operador na tela (confirmar com menos, enviar só o autorizado, finalizar sem folha). Conflito de agenda é a única que **não** tem resposta — só correção.

O resultado do pré-voo é o que a tela usa para montar o diálogo. A confirmação do operador volta como campos explícitos no disparo (`confirmar_menos_sessoes`, `limitar_ao_autorizado`, `confirmar_sem_anexo`), e a API recusa o disparo se uma condição detectada no pré-voo não vier confirmada. Sem isso, um disparo direto na API driblaria o diálogo.

### 3. Regras de agenda num único avaliador, chamado de todos os caminhos de entrada

Um avaliador recebe um conjunto de sessões candidatas (paciente, especialidade, data, hora) e devolve a lista de conflitos, cada um com tipo (`intervalo`, `limite_diario`, `mesmo_horario`), as duas sessões envolvidas e a guia da sessão já gravada. Ele é chamado pela confirmação da folha, pelo cadastro avulso, pela edição de sessão e pelo pré-voo da finalização.

**Por quê um só**: a regra tem três formulações (50 min entre inícios, 8/dia ABA, 1/dia não-ABA) que interagem — 8 sessões ABA num dia só cabem se o intervalo for respeitado. Duplicar isso em quatro lugares garante que divirjam.

**ABA pelo nome da especialidade**: decisão do usuário. A comparação normaliza acentuação e caixa, remove os pontos internos (`A.B.A.` vira uma palavra só) e procura `ABA` como palavra isolada — `Fisioterapia ABA`, `terapia aba` e `Psicologia (ABA)` casam; `Abagail` e `Terapia Cabana` não. Continua sendo texto livre: um nome que escreva a sigla de outra forma escaparia, e uma flag na especialidade seria mais robusta. Fica registrada como caminho de evolução, não como escopo.

**Consulta**: o avaliador busca as sessões `completed` do paciente na janela dos dias envolvidos (não a base inteira), somando as candidatas do lote em memória. O choque de profissional é uma segunda consulta, por executante e data, e sai como aviso separado dos conflitos.

### 4. Preenchimento das datas: formato provável, erro nomeado

O formato de `dt_serie_N` é presumido `dd/mm/aaaa hh:mm`. Como não há como confirmar sem o portal, o preenchimento fica atrás de uma função única (`formatarDataSerie`) e o worker **verifica o valor que ficou no campo depois de preencher**. Se o campo tiver rejeitado, normalizado para outra coisa ou ficado vazio, a operação falha com `DT_SERIE_FORMATO_RECUSADO`, carregando o que foi enviado e o que o campo aceitou. Assim a primeira execução em produção devolve o formato certo em vez de um "falhou".

Mesmo tratamento para os campos 2..N: o usuário informa que já aparecem habilitados, mas o worker confere se o campo está habilitado antes de escrever e falha com `DT_SERIE_CAMPO_INDISPONIVEL` quando não estiver — em vez de escrever no vazio e seguir.

### 5. Anexos: sequenciais, verificados um a um

As folhas são baixadas do storage para arquivos temporários e enviadas ao worker por caminho local, como `gerarGuia` já faz. O worker abre a popup de anexos, envia uma folha, confirma, fecha e **reabre** para a próxima — não assume que a popup aceita várias em sequência. Depois de cada envio confere que a folha aparece na lista de anexos; não aparecendo, falha com `ANEXO_NAO_CONFIRMADO` nomeando qual.

A popup de anexos da tela de execução é uma das que não temos HTML. Ela fica isolada numa função própria, com seletores por `name`/`title` (`File_NM_ARQUIVO_FISICO_File`, `Button_Insert`, `btn_finalizar`) em vez de posição, que é o que o texto do portal dá.

### 6. Modo simulação: configuração global, decidida na API, obedecida no worker

Flag em `configuracoes_globais` (`automacao_finalizar_guia_simulacao_ativo`, ligada por padrão). A API a envia no payload como `simular: true`. O worker faz tudo e, no lugar de clicar em Gravar e Finalizar, tira screenshot da tela preenchida e devolve `status: 'succeeded'` com `simulado: true`.

A API, ao ver `simulado: true`, grava a execução como bem-sucedida e **não** transiciona a guia. A tela mostra a execução marcada como simulação, com o screenshot.

**Por quê padrão ligado**: a primeira execução em produção será contra uma guia real de um paciente real, e Gravar e Finalizar não tem volta. O custo de ligar a flag depois de conferir é um clique; o custo do contrário é uma guia finalizada errada na operadora.

### 7. Status da guia: `finalized` só pela automação, e só para `unimed_rda`

`GuiaService::finalizar()` ganha uma guarda: se `convenio.connector_driver === 'unimed_rda'` e a chamada não vier da automação, recusa. O serviço da automação chama um caminho interno que faz o mesmo bookkeeping de hoje (senha, validade, `data_finalizacao`) e registra a transição com origem automação, não manual.

Senha e validade continuam vindo de onde vêm hoje (captura da automação ou regra do convênio). A finalização na operadora não traz senha nova.

**Risco conhecido**: se o portal estiver fora do ar, guia Unimed nenhuma finaliza. O usuário optou por isso, sem escape manual. Fica registrado em Riscos.

## Risks / Trade-offs

- **Portal fora do ar bloqueia toda finalização Unimed** → não há escape manual por decisão do usuário. Mitigação parcial: a falha fica visível na guia e a ação é reacionável; se virar problema recorrente, o escape com justificativa auditada já está desenhado (foi a opção 3 da decisão) e pode ser ligado depois sem mudar o resto.
- **HTML de duas telas desconhecido (execução preenchida e popup de anexos)** → cada passo incerto tem erro próprio e verifica o efeito do que fez, para que a primeira execução real aponte a linha, não o arquivo. Os testes do worker cobrem esses passos contra fixtures locais montadas a partir do HTML que o usuário forneceu.
- **Formato de `dt_serie_N` presumido** → ver decisão 4; o worker confere o valor que ficou no campo em vez de confiar no que escreveu.
- **Divergência entre `sessoes_autorizadas` no Gescon e `QT_AUTORIZADA_1` no portal** → falha explícita, nunca preenchimento parcial silencioso.
- **`ABA` detectado pelo nome da especialidade** → grafias fora das cobertas (caixa, acento, pontos internos) escapariam e o limite de 1/dia seria aplicado onde cabem 8, bloqueando gravação legítima. A mensagem de bloqueio nomeia a especialidade, então o operador vê o motivo; a correção é renomear a especialidade ou, depois, adotar a flag.
- **Regras de agenda valem só para o novo** → guias antigas com sessões em conflito não serão finalizáveis pela automação, porque o pré-voo confere as sessões da guia, não só as recém-gravadas. Mitigação: o alerta no dashboard existe exatamente para tornar esse passivo visível e corrigível antes de alguém tentar finalizar.
- **Bloqueio duro no registro de sessões** → se a regra dos 50 minutos estiver errada para algum caso real da clínica, não há como gravar. Decisão consciente do usuário ("só passa se corrigido"); o alerta do dashboard e a mensagem nomeando as sessões dão o caminho de correção.

## Migration Plan

1. Regras de agenda e N folhas por guia entram primeiro, sem nada de automação. São independentes e já valem por si.
2. Worker e service da finalização entram com o modo simulação **ligado** por padrão.
3. Deploy em produção. Primeira guia real roda em simulação; conferir no portal o que foi preenchido e anexado.
4. Corrigir o que a simulação apontar (provavelmente formato de data e seletores da popup de anexos), redeployar, repetir.
5. Desligar a simulação por configuração e finalizar a primeira guia de verdade, acompanhando.

**Rollback**: religar a flag de simulação desarma o passo irreversível sem deploy. Para desfazer a regra de status, a guarda em `finalizar()` é um único ponto — voltar a permitir finalização manual de guia Unimed é uma reversão pequena e isolada.

## Open Questions

- O portal aceita `dd/mm/aaaa hh:mm` em `dt_serie_N`, ou data e hora são campos separados? Resolvido pela primeira execução em produção; a decisão 4 garante que ela responda isso em vez de só falhar.
- A popup de anexos da tela de execução aceita mais de um arquivo por abertura? O worker assume que não (abre uma vez por folha), que é o comportamento mais conservador e funciona nos dois casos.
