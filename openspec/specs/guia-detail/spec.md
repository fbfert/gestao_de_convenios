# guia-detail Specification

## Purpose

Mostrar uma guia por inteiro numa tela só — o que a operadora autorizou, quem
vai executar, quanto da cota já foi usado e o que ainda pode ser feito com ela.

É a tela para onde vai quem precisa entender uma guia específica: por que está
negada, quando vence a senha, de qual solicitação veio. As ações que cabem aqui
são as que dependem apenas da guia (aprovar, negar); as que dependem de outra
coisa vivem onde essa outra coisa está — finalizar exige sessão registrada, e
por isso mora na tela de Sessões.

## Requirements

### Requirement: Acesso ao detalhe de guia
O sistema SHALL disponibilizar a rota autenticada `/guias/:id` e SHALL carregar a guia pelo endpoint existente `GET /api/guias/{id}`, sem criar endpoint adicional. A resposta de detalhe SHALL incluir os dados de paciente, convênio, profissional, especialidade, antecipações e conciliações já vinculados à guia.

#### Scenario: Abrir uma guia pela lista
- **WHEN** o usuário clicar no número de uma guia exibida na lista
- **THEN** o sistema SHALL navegar para `/guias/{id}` e apresentar os dados daquela guia

#### Scenario: Carregamento do detalhe
- **WHEN** a consulta da guia estiver em andamento
- **THEN** o sistema SHALL exibir um estado de carregamento visível

#### Scenario: Guia inexistente ou fora do tenant
- **WHEN** a API retornar 404 para o identificador informado na rota
- **THEN** o sistema SHALL exibir um estado de erro tratado em vez de uma tela em branco

### Requirement: Informações operacionais da guia
O sistema SHALL exibir número, status traduzido por `translateStatus('guias', ...)`, tipo de terapia, paciente e carteirinha, convênio, profissional, especialidade, datas de solicitação e finalização, senha e validade da senha.

O sistema SHALL exibir, quando a guia estiver marcada como finalizada na operadora, essa marca e a data da conferência que a produziu — separadas do status, porque dizem outra coisa: o status conta o que o Gescon sabe do ciclo da guia, e a marca conta o que o portal respondeu.

#### Scenario: Exibir uma guia finalizada
- **WHEN** uma guia finalizada for carregada
- **THEN** o sistema SHALL apresentar a senha, a validade e a data de finalização retornadas pela API

#### Scenario: Exibir a marca de finalizada na operadora
- **WHEN** a guia estiver marcada como finalizada na operadora
- **THEN** o sistema SHALL apresentar a marca e a data da conferência, sem alterar a apresentação do status

#### Scenario: Guia sem a marca
- **WHEN** a guia não estiver marcada como finalizada na operadora
- **THEN** o sistema SHALL NOT apresentar a marca

#### Scenario: Destacar validade próxima
- **WHEN** a validade da senha estiver dentro de sete dias a partir da data atual
- **THEN** o sistema SHALL aplicar o mesmo destaque visual de prazo próximo usado na lista de guias

### Requirement: Vínculos financeiros da guia
O sistema SHALL exibir a cota `qtd_utilizada/qtd_autorizada` para antecipações vinculadas e o status traduzido para a conciliação vinculada, incluindo links às respectivas páginas de domínio.

#### Scenario: Guia com antecipação vinculada
- **WHEN** a guia possuir uma ou mais antecipações
- **THEN** o sistema SHALL exibir a cota de cada antecipação e um link para `/antecipacoes`

#### Scenario: Guia com conciliação gerada
- **WHEN** a guia possuir conciliação financeira
- **THEN** o sistema SHALL exibir seu status traduzido e um link para `/conciliacao`

### Requirement: Ações de status em telas de guia

O sistema SHALL disponibilizar as ações de aprovar e negar uma guia em análise tanto na lista quanto no detalhe, reutilizando as mesmas mutations.

O sistema SHALL NOT oferecer a finalização nas telas de guia: finalizar depende de haver sessão registrada, e por isso a ação vive na tela de Sessões, junto do grupo da guia.

A finalização oferecida na tela de Sessões SHALL depender do convênio da guia: guia de convênio com automação Unimed SHALL ser finalizada na operadora, e guia de convênio sem automação SHALL continuar sendo finalizada manualmente, com senha, validade e data.

O sistema SHALL refletir no detalhe a situação resultante de qualquer dessas ações sem exigir recarregar o navegador.

#### Scenario: Aprovar pelo detalhe
- **WHEN** o usuário aprovar uma guia em análise na página de detalhe
- **THEN** o sistema SHALL atualizar o status exibido no detalhe sem recarregar manualmente o navegador

#### Scenario: Negar pelo detalhe
- **WHEN** o usuário negar uma guia em análise na página de detalhe
- **THEN** o sistema SHALL atualizar o status exibido no detalhe sem recarregar manualmente o navegador

#### Scenario: Finalizar pelo detalhe
- **WHEN** o usuário abrir a lista ou o detalhe de uma guia
- **THEN** o sistema SHALL NOT oferecer ali a ação de finalizar, porque finalizar depende de sessão registrada e a ação vive na tela de Sessões

#### Scenario: Finalização de guia com automação
- **WHEN** a guia for de convênio com automação Unimed
- **THEN** o sistema SHALL oferecer, na tela de Sessões, a finalização na operadora, e SHALL NOT oferecer a finalização manual

#### Scenario: Finalização de guia sem automação
- **WHEN** a guia for de convênio sem automação Unimed
- **THEN** o sistema SHALL oferecer, na tela de Sessões, a finalização manual com os dados exigidos

#### Scenario: Detalhe reflete a finalização feita na operadora
- **WHEN** a finalização na operadora concluir para uma guia aberta no detalhe
- **THEN** o sistema SHALL passar a exibir a guia como finalizada, com senha, validade e data de finalização
