## Context

O `GET /api/health` (ADR-24) responde a um monitor externo sobre a infraestrutura: banco, fila, agendador. Ele foi entregue com um array `componentes` vazio, reservado por contrato justamente para esta fase.

O que falta é a camada acima: as peças que executam o trabalho da clínica. Hoje a automação Unimed é a única automatizada, mas `convenios.connector_type` (ADR-02) existe precisamente porque outros conectores virão. Uma solução chamada `worker_unimed_status` teria que ser reaberta no primeiro conector novo.

## Goals / Non-Goals

**Goals:**
- Registrar componente novo é inserir linha, sem tocar em código do dashboard.
- Estado sempre derivado do heartbeat, nunca escrito à mão.
- Um card de saúde no dashboard, uma linha por componente.
- Alimentar o array `componentes` do endpoint público sem vazar tenant.

**Non-Goals:**
- Notificar alguém. Destinatários, digest e envio imediato são a fase de notificações.
- Alertas acumulados (senha vencendo, guia negada). Saúde é estado agora; alerta é outro modelo e outro card.
- Botão de reiniciar componente, em qualquer forma.
- Histórico de disponibilidade e cálculo de uptime. A tabela guarda o último heartbeat, não a série.

## Decisions

- **Componente é linha de tabela, não coluna nem código.** Racional: a automação não vai ser só Unimed (ADR-02). Com `tipo` e `convenio_id` nullable, um conector novo entra por seed e aparece no card sem alterar o frontend.

- **O heartbeat é empurrado pelo componente, não puxado pelo sistema.** Racional: puxar exigiria que o sistema soubesse alcançar cada peça — credencial, rota, rede — e um `pull` que falha não distingue "componente morto" de "quem pergunta não consegue chegar". Empurrar transforma a pergunta em uma leitura local de data.

- **Estado é derivado na leitura, e a tabela guarda apenas o carimbo.** Racional: estado gravado é estado que envelhece sozinho — um componente que morre para de escrever, e é exatamente por isso que a última coisa escrita diria "ok" para sempre. Derivar de `now() - ultimo_heartbeat_em` faz o silêncio ser a evidência, que é o comportamento correto.

- **Três faixas, com o múltiplo 3x separando atenção de fora.** Dentro do intervalo esperado, `healthy`. Até 3x o intervalo, `warning`. Acima de 3x, ou sem heartbeat nenhum, `down`. Racional: uma rodada perdida por carga é ruído, três seguidas são um padrão. O mesmo raciocínio da tolerância de 10 minutos do ADR-24.

- **Sem heartbeat nenhum é `down`, não "desconhecido".** Racional: mesma decisão do carimbo ausente no ADR-24 — componente que nunca deu sinal de vida é indistinguível, do ponto de vista de quem opera, de componente morto. Um quarto estado "desconhecido" só empurra a decisão para a tela.

- **Valores de estado e tipo em inglês.** `healthy | warning | down` e `worker | scheduler | queue | smtp | connector`, com a tradução no mapa de labels do frontend. Racional: ADR-06 e `openspec/config.yaml`. Conflito com o texto do plano registrado no `proposal.md`.

- **Nomes de coluna seguem o resto do banco, em português.** `chave`, `nome`, `tipo`, `ultimo_heartbeat_em`, `intervalo_esperado_segundos`, `ativo`. Racional: ADR-06 rege valores de status, não identificadores de schema; divergir aqui criaria uma tabela estranha ao restante.

- **Nada de botão "Reiniciar".** Racional: ADR-25. Um botão de reinício falha justamente quando seria necessário — quando o componente está morto — e empurra execução remota de comando para a mão de usuário de clínica, risco sem contrapartida. A recuperação é da camada que continua de pé quando o processo cai: o supervisor de container.

- **`ativo` controla exibição, não o heartbeat.** Componente desativado sai do card e do `GET /saude`, mas um heartbeat que chegue continua sendo registrado. Racional: desativar é decisão de operação; descartar a escrita faria o componente parecer morto no dia em que fosse reativado.

## Risks / Trade-offs

- **[A frase "o suporte foi notificado automaticamente" pode ser mentira]** -> É o risco principal desta fase, porque uma mensagem falsa é pior que nenhuma: ela faz a clínica parar de avisar. Nesta fase nada notifica — a fase de notificações é a 5. O que torna a frase verdadeira antes disso é o item 6 desta entrega: o array `componentes` do `GET /api/health` faz o componente fora derrubar o endpoint público, e o monitor externo do P0.3 avisa o suporte. Portanto a frase só pode aparecer na tela depois que aquele monitor existir de fato; até lá, a UI informa o estado sem prometer notificação.

- **[Componente fora derrubando o `/health` público]** -> Um componente de tenant fora passa a devolver 503 num endpoint que é global. É o comportamento desejado (é o que faz o monitor externo acordar alguém), mas significa que um conector quebrado de um cliente alarma o suporte como se fosse falha de infraestrutura. Com um tenant em produção o custo é zero; com dez, o critério de composição precisa ser revisto — provavelmente separando "infraestrutura fora" de "algum componente de algum tenant fora".

- **[Heartbeat só no caminho de sucesso]** -> Registrar heartbeat ao final de cada execução bem-sucedida significa que um componente que roda e falha sempre fica silencioso e cai para `down`, o que está certo. O efeito colateral é que um componente correto mas ocioso — que simplesmente não teve trabalho no período — também silencia. Por isso `intervalo_esperado_segundos` é dado por componente, e não constante: o intervalo precisa refletir a frequência do agendamento daquela peça, não a do trabalho da clínica.

- **[`GET /saude` no polling de 30s do dashboard]** -> É uma leitura da tabela inteira do tenant, sem `count()` e sem `join`; a tabela tem uma linha por peça, na ordem de unidades. O estado é calculado em PHP a partir do carimbo, e não em SQL, para a mesma regra valer no card, no `/health` e em qualquer consumidor futuro.
