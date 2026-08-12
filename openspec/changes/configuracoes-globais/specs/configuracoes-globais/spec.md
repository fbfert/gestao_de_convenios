## ADDED Requirements

### Requirement: Parâmetros globais por clínica
O sistema SHALL oferecer uma tela de configurações globais, restrita a quem administra configurações, com valores padrão prontos desde o primeiro acesso.

#### Scenario: Primeiro acesso
- **WHEN** o usuário abrir a tela sem que a clínica tenha configuração gravada
- **THEN** o sistema SHALL apresentar os valores padrão, sem erro

#### Scenario: Salvar fora da faixa
- **WHEN** o usuário informar um valor fora da faixa aceita para um parâmetro
- **THEN** o sistema SHALL recusar a gravação e explicar o limite

#### Scenario: Isolamento entre clínicas
- **WHEN** duas clínicas tiverem configurações diferentes
- **THEN** o sistema SHALL aplicar a cada usuário a configuração da própria clínica

### Requirement: Expiração da sessão
O sistema SHALL encerrar o acesso depois do tempo configurado pela clínica, contado a partir da entrada.

#### Scenario: Dentro do prazo
- **WHEN** o usuário fizer uma requisição antes do prazo terminar
- **THEN** o sistema SHALL atendê-la normalmente

#### Scenario: Prazo vencido
- **WHEN** o usuário fizer uma requisição depois do prazo
- **THEN** o sistema SHALL recusá-la com 401 e uma mensagem indicando que a sessão expirou

#### Scenario: Credencial descartada
- **WHEN** uma sessão expirar
- **THEN** o sistema SHALL invalidar a credencial de forma que ela não volte a funcionar

#### Scenario: Retorno à tela de login
- **WHEN** a sessão expirar durante o uso
- **THEN** o sistema SHALL levar o usuário de volta à tela de login na próxima ação

#### Scenario: Expiração desligada
- **WHEN** o tempo de sessão estiver configurado como zero
- **THEN** o sistema SHALL manter o acesso válido até que o usuário saia
