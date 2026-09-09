## Why

A automação já roda em produção e a falha é descoberta por reclamação do cliente: hoje não existe, dentro do produto, nenhuma resposta para "o robô está funcionando agora?". O `GET /api/health` da observabilidade externa (ADR-24) responde por banco, fila e agendador, mas não sabe dizer nada sobre as peças que de fato executam o trabalho da clínica.

Saúde não é alerta. Alerta é acumulado e exige ação de quem opera ("10 negadas pendentes"); saúde é estado agora e não tem ação do lado do cliente ("o worker respondeu há 2 minutos"). São dois modelos e dois cards, e misturá-los faz o operador ignorar os dois.

## What Changes

- Criar a tabela `saude_componentes`, onde cada peça que pode estar viva ou morta é uma linha por tenant.
- Cada componente empurra um heartbeat quando funciona; o sistema deriva o estado do tempo desde o último heartbeat, sem ninguém escrever o estado à mão.
- Expor `GET /saude` com os componentes do tenant e o estado derivado de cada um.
- Alimentar o array `componentes` do `GET /api/health` com chave e estado, sem identificar tenant.
- Exibir um card de saúde no dashboard, com uma linha por componente.
- Quando um componente está fora, informar que o suporte foi notificado automaticamente — sem oferecer botão de reiniciar (ADR-25).

## Capabilities

### New Capabilities
- `saude-componentes`: registro, heartbeat e estado derivado das peças do sistema que podem estar indisponíveis.

### Modified Capabilities

## Impact

- API Laravel: nova tabela, model, service de heartbeat, rota autenticada `GET /saude`, e a chamada de heartbeat nos pontos que já existem (worker de automação, agendador, envio de e-mail).
- `GET /api/health`: o array `componentes`, hoje vazio por contrato, passa a ser preenchido.
- Frontend React: card de saúde no dashboard, no mesmo `refetchInterval` de 30s já usado lá.
- Banco de dados: uma tabela nova; nenhuma alteração em tabela existente.

## Conflitos registrados

Conforme a regra 6 do `AGENTS.md`, dois conflitos entre o plano de construção e regras já vigentes do projeto:

1. **Idioma dos valores de estado.** O plano descreve os estados como `saudavel | atencao | fora` e os tipos com `fila`, mas o ADR-06, o `openspec/config.yaml` e o próprio princípio 5 do plano determinam valor de status em inglês no banco e na API, com tradução só na UI. Esta spec adota inglês (`healthy | warning | down`, `worker | scheduler | queue | smtp | connector`). Se a intenção era abrir exceção, é decisão humana e a spec muda.

2. **"O suporte foi notificado automaticamente" precisa ser verdade.** Nesta fase nada notifica ninguém: a notificação por e-mail é a fase 5, e a única vigilância real é o monitor externo do P0.3 observando o `GET /api/health`. Ver o tratamento em `design.md`.
