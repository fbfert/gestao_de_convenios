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

### Requirement: Parâmetro salvo tem efeito observável

Todo parâmetro oferecido na tela de configurações globais SHALL governar de fato o comportamento que ele descreve, e o sistema SHALL NOT manter parâmetro que possa ser salvo sem alterar comportamento nenhum.

Este requisito existe porque exigir apenas que o valor seja gravado e validado deixa passar o pior defeito possível numa tela de configuração: um campo que salva, aparece na trilha de auditoria e não faz nada. Foi o que aconteceu com o tamanho de página das listagens, que ficou como enfeite enquanto cada listagem trazia o próprio número fixo.

#### Scenario: Tamanho de página governa as listagens
- **WHEN** a clínica gravar um tamanho de página e um usuário abrir qualquer listagem paginada sem pedir tamanho específico
- **THEN** o sistema SHALL paginar pelo tamanho configurado

#### Scenario: Pedido explícito prevalece
- **WHEN** a requisição informar explicitamente um tamanho de página
- **THEN** o sistema SHALL respeitar o tamanho pedido, e SHALL NOT sobrepor o configurado — é o caso das buscas dirigidas

#### Scenario: Quantidade padrão de sessões
- **WHEN** um mapeamento de convênio e especialidade for gravado sem quantidade própria
- **THEN** o sistema SHALL adotar a quantidade padrão configurada para a clínica, e SHALL NOT recorrer a um número fixo em código

#### Scenario: Parâmetro sem efeito é defeito
- **WHEN** um parâmetro da tela não alterar comportamento algum do sistema
- **THEN** isso SHALL ser tratado como defeito, e não como configuração reservada para uso futuro

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
