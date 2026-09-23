## Purpose

Dizer o que o sistema garante sobre uma clínica não alcançar dado de outra — e o que precisa ser verificado para essa garantia não depender de alguém ter lembrado, já que o banco é único e o isolamento é uma coluna.

## ADDED Requirements

### Requirement: Registro de outra clínica não é alcançável pela API

O sistema SHALL responder como inexistente a qualquer requisição que nomeie, pela URL, um registro de outra clínica — tanto para ler quanto para escrever.

O sistema SHALL barrar o registro alheio ANTES de olhar o corpo da requisição: resposta de validação sobre um registro que não é da clínica de quem pediu já é resposta a mais.

O sistema SHALL NOT alterar nem apagar registro de outra clínica em nenhuma circunstância alcançável pela API.

O sistema SHALL NOT incluir registro de outra clínica em listagem alguma.

#### Scenario: Leitura de registro alheio
- **WHEN** uma requisição nomear pela URL um registro de outra clínica
- **THEN** o sistema SHALL responder como inexistente, e SHALL NOT devolver dado algum do registro

#### Scenario: Escrita em registro alheio
- **WHEN** uma requisição de escrita nomear pela URL um registro de outra clínica
- **THEN** o sistema SHALL responder como inexistente, SHALL NOT aplicar a alteração e SHALL NOT responder erro de validação, porque validar já seria ter aceitado o registro

#### Scenario: Listagem
- **WHEN** existirem registros de outra clínica no banco
- **THEN** a listagem SHALL devolver apenas os da clínica de quem pediu

#### Scenario: Id vindo do corpo
- **WHEN** o corpo da requisição apontar para registro de outra clínica
- **THEN** o sistema SHALL recusar a operação e SHALL NOT gravar a referência

### Requirement: A validação não revela o que existe em outra clínica

O sistema SHALL recortar por clínica toda regra de existência aplicada a id recebido do cliente.

Sem o recorte, a resposta distingue "este id não existe" de "este id existe e não é seu" — e essa diferença, repetida, enumera a base de outra clínica sem nunca mostrar um dado. É vazamento de existência, e é suficiente para descobrir quantos pacientes, guias ou convênios a clínica vizinha tem.

O sistema SHALL tratar id de outra clínica como id que não existe.

#### Scenario: Id de outra clínica
- **WHEN** a requisição informar id que existe apenas em outra clínica
- **THEN** o sistema SHALL responder o mesmo que responderia a um id inexistente

#### Scenario: Id inexistente
- **WHEN** a requisição informar id que não existe em clínica alguma
- **THEN** o sistema SHALL recusá-lo na validação

#### Scenario: Id da própria clínica
- **WHEN** a requisição informar id da própria clínica
- **THEN** o sistema SHALL aceitá-lo, sem mudança alguma em relação ao comportamento anterior

### Requirement: A garantia é verificada por varredura, não por lista

O sistema SHALL ter verificação automática que descobre as rotas da API pelo próprio roteador e ataca cada uma com registro de outra clínica.

A verificação SHALL falhar quando uma rota nova passar por fora do isolamento — o modo de falha real não é o isolamento estar mal escrito, é uma rota nascer fora dele.

A verificação SHALL provar que a rota atacada responde para a própria clínica: rota que não responde a ninguém devolve "inexistente" por outro motivo, e contaria como aprovada sem ter sido testada.

A verificação SHALL atacar com usuário que tem permissão para a operação, e SHALL NOT usar usuário sem permissão — recusa por permissão passaria verde mesmo com o isolamento derrubado.

#### Scenario: Rota nova sem isolamento
- **WHEN** uma rota que alcança registro de outra clínica for acrescentada
- **THEN** a verificação SHALL falhar nomeando a rota

#### Scenario: Rota que não responde
- **WHEN** a rota atacada não responder nem para registro da própria clínica
- **THEN** a verificação SHALL falhar, em vez de contar o resultado como aprovado

#### Scenario: Parâmetro que não resolve registro
- **WHEN** um parâmetro de rota não identificar registro de banco
- **THEN** a verificação SHALL deixá-lo de fora de forma declarada, e SHALL NOT omiti-lo em silêncio

### Requirement: Consulta fora de requisição declara a clínica

O sistema SHALL recortar explicitamente por clínica toda consulta feita fora de uma requisição HTTP — job de fila, comando de console, rotina agendada.

O recorte automático depende do contexto da requisição; fora dela ele não existe, e a consulta que parece igual às outras passa a ler todas as clínicas. É o ponto onde o isolamento falha sem ninguém perceber, porque não há resposta HTTP errada para alguém notar.

O sistema SHALL ter verificação automática dessa garantia.

#### Scenario: Job consulta sem declarar a clínica
- **WHEN** um job consultar registros sem recorte explícito de clínica
- **THEN** a verificação SHALL falhar

#### Scenario: Job declara a clínica
- **WHEN** um job recortar a consulta pela clínica que está processando
- **THEN** ele SHALL alcançar apenas os registros dela
