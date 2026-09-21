## MODIFIED Requirements

### Requirement: Disparo da finalização pelo operador

O sistema SHALL oferecer a finalização na operadora como ação explícita do operador sobre uma guia, e SHALL NOT dispará-la sozinho por cota atingida, por agendamento ou em lote.

O sistema SHALL oferecer a ação apenas para guia de convênio com automação Unimed, apenas quando a guia estiver em situação que aceita finalização e apenas quando houver ao menos uma sessão registrada.

O sistema SHALL NOT oferecer a finalização de guia já marcada como finalizada na operadora: pedir ao portal que finalize o que ele já deu por finalizado não é uma operação que faça sentido, e a guia chegaria lá fora da tela de execução que o robô espera.

O sistema SHALL impedir que duas finalizações da mesma guia corram ao mesmo tempo, e SHALL apresentar a execução em andamento em vez de abrir outra.

#### Scenario: Guia pronta para finalizar
- **WHEN** o operador acionar a finalização de uma guia de convênio com automação Unimed que já tenha sessão registrada
- **THEN** o sistema SHALL enfileirar a finalização na operadora e SHALL passar a acompanhar o andamento dela

#### Scenario: Guia sem sessão registrada
- **WHEN** a guia não tiver nenhuma sessão registrada
- **THEN** o sistema SHALL NOT oferecer a finalização, pelo mesmo motivo que já recusa o encerramento sem sessão

#### Scenario: Guia já finalizada na operadora
- **WHEN** a guia estiver marcada como finalizada na operadora
- **THEN** o sistema SHALL NOT oferecer a finalização, e SHALL dizer que a operadora já a deu por finalizada

#### Scenario: Convênio sem automação
- **WHEN** a guia for de convênio sem automação Unimed
- **THEN** o sistema SHALL NOT oferecer a finalização na operadora, e SHALL manter a finalização manual disponível

#### Scenario: Finalização já em andamento
- **WHEN** o operador acionar a finalização de uma guia que já tem finalização enfileirada ou em execução
- **THEN** o sistema SHALL NOT abrir uma segunda execução e SHALL mostrar o andamento da que já existe
