## Purpose

Define quais telas se atualizam sozinhas e com que frequência. O padrão do sistema é não reconsultar por conta própria; as telas de acompanhamento — painel, saúde, alertas e progresso de automação — são a exceção declarada, porque são deixadas abertas e respondem sobre o agora.

## ADDED Requirements

### Requirement: Reconsulta automática é exceção, não padrão

O sistema SHALL manter, como padrão de todas as consultas, a ausência de reconsulta automática ao voltar o foco para a janela, e SHALL religá-la apenas nas telas de acompanhamento que declararem essa necessidade.

Deixar toda tela reconsultando ao foco transforma cada troca de aba em uma rajada de requisições, sem que nada na tela tenha mudado. O custo aparece no servidor e não se converte em informação melhor.

#### Scenario: Tela comum volta ao foco
- **WHEN** o usuário voltar o foco para uma tela que não seja de acompanhamento
- **THEN** o sistema SHALL NOT reconsultar os dados só por causa do foco

#### Scenario: Tela de acompanhamento volta ao foco
- **WHEN** o usuário voltar o foco para o painel inicial ou para o card de saúde
- **THEN** o sistema SHALL reconsultar os dados, para que o que está na tela valha para o agora

### Requirement: Painel inicial e saúde se atualizam sozinhos

O sistema SHALL reconsultar automaticamente, em intervalo curto e igual para os dois, os dados do painel inicial e os do card de saúde dos componentes, sem exigir recarga manual da página.

Os dois andam juntos de propósito: um card de saúde que só muda no F5 responde sobre o passado, que é o contrário do que ele existe para dizer — e o pior é que ele não se anuncia desatualizado.

#### Scenario: Painel deixado aberto
- **WHEN** o painel inicial permanecer aberto sem interação
- **THEN** o sistema SHALL atualizar os números periodicamente, sem recarga manual

#### Scenario: Componente cai com a tela aberta
- **WHEN** um componente deixar de responder enquanto o painel está aberto
- **THEN** o sistema SHALL refletir a mudança de estado no card de saúde dentro do mesmo intervalo, sem recarga manual

### Requirement: Acompanhamento de execução para sozinho

O sistema SHALL consultar o progresso de uma execução de automação em intervalo curto **enquanto** ela não tiver chegado a um status terminal, e SHALL cessar a consulta quando chegar.

Não há WebSocket nem SSE no projeto: "tempo real" aqui é consulta curta e limitada. Sem a parada no status terminal, uma tela esquecida aberta consultaria para sempre uma execução que já acabou.

#### Scenario: Execução em andamento
- **WHEN** o usuário acompanhar uma execução que ainda não terminou
- **THEN** o sistema SHALL consultar o progresso em intervalo curto, atualizando a tela

#### Scenario: Execução alcança status terminal
- **WHEN** a execução acompanhada chegar a um status terminal
- **THEN** o sistema SHALL parar de consultar, e SHALL manter o resultado final na tela

#### Scenario: Tela sem acompanhamento ativo
- **WHEN** a tela de automações não estiver acompanhando execução alguma
- **THEN** o sistema SHALL NOT consultar em intervalo curto

### Requirement: Intervalo proporcional ao que a tela promete

O sistema SHALL usar intervalos de reconsulta proporcionais à urgência do que cada tela informa, e SHALL NOT adotar um intervalo único para todas.

#### Scenario: Visão geral e saúde
- **WHEN** a tela for o painel inicial ou o card de saúde
- **THEN** o sistema SHALL usar o intervalo mais curto entre as telas de listagem, por serem as que respondem "como está agora"

#### Scenario: Alertas e sincronização
- **WHEN** a tela for a de alertas ou a de sincronização com a clínica
- **THEN** o sistema SHALL usar intervalo mais longo que o do painel, por serem informações que mudam em ritmo mais lento
