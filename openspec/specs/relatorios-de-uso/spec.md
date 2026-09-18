# relatorios-de-uso Specification

## Purpose

Como a clínica consulta os próprios números agregados por período, em vez de só
o que está aberto agora.

O painel e as listagens respondem o presente. Esta capacidade responde a outra
pergunta — se melhorou —, e por isso carrega três compromissos que atravessam
todos os requisitos abaixo: o número é contado pela data do FATO (a transição de
status, não a criação do registro); todo indicador vem com o valor do período
anterior de mesmo tamanho, porque número solto não diz se piorou; e ausência de
dado é dita, nunca desenhada como zero.

O acesso é por aba — operação, financeiro, automações e uso —, para que o
financeiro não vaze para quem só opera, e o recorte de clínica é intransponível
para quem não administra o sistema.

## Requirements

### Requirement: Relatórios em quatro abas, com permissão própria

O sistema SHALL apresentar os relatórios em quatro abas — Operação, Financeiro, Automações e Uso —, cada uma exigindo a sua própria permissão, e SHALL NOT apresentar aba para a qual o usuário não tenha permissão.

O sistema SHALL ocultar a entrada de Relatórios no menu para quem não tiver nenhuma das quatro permissões.

#### Scenario: Aba sem permissão não aparece
- **WHEN** um usuário sem a permissão de uma aba abrir os relatórios
- **THEN** o sistema SHALL NOT exibir essa aba

#### Scenario: Consulta direta sem permissão
- **WHEN** um usuário sem a permissão de uma aba consultar os dados dela diretamente
- **THEN** o sistema SHALL negar o acesso

#### Scenario: Sem nenhuma permissão de relatório
- **WHEN** um usuário sem nenhuma das quatro permissões navegar pelo sistema
- **THEN** o sistema SHALL NOT oferecer a página de relatórios no menu

#### Scenario: Abrir relatórios sem nenhuma aba disponível
- **WHEN** um usuário sem nenhuma das quatro permissões abrir o endereço dos relatórios
- **THEN** o sistema SHALL redirecioná-lo para o painel

### Requirement: Período, comparação e granularidade

O sistema SHALL exigir um período de início e fim para qualquer relatório, SHALL recusar período maior que 366 dias, e SHALL recusar data final anterior à inicial.

O sistema SHALL oferecer comparação com o período imediatamente anterior de mesmo tamanho, devolvendo, para cada indicador, o valor do período anterior.

O sistema SHALL escolher a granularidade das séries pelo tamanho do período — dia, semana ou mês — e SHALL aceitar que o usuário a substitua.

#### Scenario: Período obrigatório
- **WHEN** um relatório for consultado sem período
- **THEN** o sistema SHALL recusar a consulta

#### Scenario: Período longo demais
- **WHEN** o período pedido exceder 366 dias
- **THEN** o sistema SHALL recusar a consulta

#### Scenario: Período invertido
- **WHEN** a data final for anterior à inicial
- **THEN** o sistema SHALL recusar a consulta

#### Scenario: Comparação com o anterior
- **WHEN** a comparação for pedida
- **THEN** o sistema SHALL calcular o período anterior com o mesmo número de dias, terminando no dia anterior ao início do período pedido, e SHALL devolver o valor anterior de cada indicador

#### Scenario: Granularidade escolhida pelo tamanho
- **WHEN** nenhuma granularidade for informada
- **THEN** o sistema SHALL usar dia para períodos de até 31 dias, semana para até 120 e mês acima disso

#### Scenario: Granularidade informada prevalece
- **WHEN** o usuário informar a granularidade
- **THEN** o sistema SHALL usá-la no lugar da automática

### Requirement: Isolamento entre clínicas nos relatórios

O sistema SHALL restringir todo relatório à clínica do usuário autenticado.

O sistema SHALL permitir somente ao super administrador escolher outra clínica ou somar todas, e SHALL negar essa escolha a qualquer outro usuário, ainda que ela seja enviada na consulta.

#### Scenario: Usuário comum vê só a própria clínica
- **WHEN** um usuário comum consultar um relatório
- **THEN** o sistema SHALL considerar apenas dados da clínica dele

#### Scenario: Usuário comum tentando escolher outra clínica
- **WHEN** um usuário comum enviar uma clínica na consulta
- **THEN** o sistema SHALL negar a consulta

#### Scenario: Super administrador escolhe uma clínica
- **WHEN** o super administrador escolher uma clínica
- **THEN** o sistema SHALL considerar apenas os dados dela

#### Scenario: Super administrador soma todas
- **WHEN** o super administrador pedir todas as clínicas
- **THEN** o sistema SHALL considerar os dados de todas elas

### Requirement: Filtros de convênio, especialidade e profissional

O sistema SHALL aceitar filtros de convênio, especialidade e profissional, SHALL aplicá-los apenas onde a informação exista, e SHALL informar na resposta quais filtros foram de fato aplicados.

#### Scenario: Filtro aplicado
- **WHEN** um filtro for informado e a informação existir para aquele indicador
- **THEN** o sistema SHALL restringir o cálculo a ele

#### Scenario: Filtro sem sentido para o indicador
- **WHEN** um filtro for informado e a informação não existir para aquele indicador
- **THEN** o sistema SHALL ignorá-lo e SHALL NOT listá-lo entre os filtros aplicados

### Requirement: Indicadores calculados pela data do fato

O sistema SHALL calcular os indicadores de guia — aprovação, negação, tempo em análise e tempo até a finalização — pela data em que a transição de status ocorreu, e SHALL NOT usar a data de criação da guia.

#### Scenario: Guia criada antes e decidida dentro do período
- **WHEN** uma guia for criada antes do período e negada dentro dele
- **THEN** o sistema SHALL contá-la como negação do período consultado

#### Scenario: Guia criada dentro e decidida depois
- **WHEN** uma guia for criada dentro do período e decidida depois dele
- **THEN** o sistema SHALL NOT contá-la entre as decisões do período

#### Scenario: Taxa de aprovação
- **WHEN** a taxa de aprovação for calculada
- **THEN** o sistema SHALL dividir as guias aprovadas ou finalizadas no período pelo total de guias decididas no período, sendo decidida a aprovada, a finalizada ou a negada

### Requirement: Indicadores da operação

O sistema SHALL apresentar, na aba de operação, ao menos: solicitações criadas, guias geradas, taxa de aprovação, taxa de negação, tempo médio e mediano entre a entrada em análise e a decisão, sessões realizadas, faltas, sessões canceladas, senhas vencendo dentro do prazo configurado para a clínica, e as antecipações geradas e ignoradas no período com o percentual de dispensa.

#### Scenario: Prazo de senha vem da configuração
- **WHEN** as senhas a vencer forem contadas
- **THEN** o sistema SHALL usar o prazo configurado para a clínica, e SHALL NOT usar prazo fixo em código

#### Scenario: Antecipações do período
- **WHEN** as antecipações forem contadas
- **THEN** o sistema SHALL informar quantas foram geradas, quantas foram ignoradas, e o percentual que as ignoradas representam do total

### Requirement: Indicadores financeiros

O sistema SHALL apresentar, na aba financeira, ao menos: valor executado, valor apresentado, valor pago, glosa em dinheiro e em percentual, a situação das conciliações e o repasse estimado por profissional.

O valor apresentado SHALL ser derivado da soma do pago com o glosado dos lotes de analítico do período.

O sistema SHALL devolver todo valor monetário como número inteiro em centavos.

#### Scenario: Valor apresentado derivado
- **WHEN** o valor apresentado for calculado
- **THEN** o sistema SHALL somar o pago com o glosado dos lotes do período

#### Scenario: Glosa percentual
- **WHEN** a glosa percentual for calculada
- **THEN** o sistema SHALL dividir o valor glosado pelo valor apresentado

#### Scenario: Sem lote no período
- **WHEN** não houver lote de analítico no período
- **THEN** o sistema SHALL apresentar os indicadores dependentes de analítico como ausentes, e SHALL NOT apresentá-los como zero

#### Scenario: Dinheiro em centavos
- **WHEN** um indicador monetário for devolvido
- **THEN** o sistema SHALL devolvê-lo como inteiro em centavos, deixando a formatação para a tela

### Requirement: Indicadores das automações

O sistema SHALL apresentar, na aba de automações, ao menos: execuções no período, taxa de sucesso, os erros mais frequentes, duração média e percentil 95, tempo médio em fila, reprocessamentos, e o tempo em que cada componente monitorado esteve fora do ar.

#### Scenario: Erros mais frequentes
- **WHEN** os erros forem agrupados
- **THEN** o sistema SHALL agrupá-los pelo código de erro registrado na execução

#### Scenario: Período anterior ao início do histórico de saúde
- **WHEN** o período consultado for anterior ao início do registro de estado dos componentes
- **THEN** o sistema SHALL informar que não há registro para o período, e SHALL NOT apresentar o componente como disponível o tempo todo

### Requirement: Indicadores de uso do sistema

O sistema SHALL apresentar, na aba de uso, ao menos: usuários ativos no período, quantidade de acessos, ações por dia, ações por papel, ações por hora do dia, importações por tipo, e alertas gerados e reconhecidos.

#### Scenario: Usuários ativos
- **WHEN** os usuários ativos forem contados
- **THEN** o sistema SHALL contar usuários distintos com ao menos uma ação registrada na trilha de auditoria dentro do período

#### Scenario: Acessos
- **WHEN** os acessos forem contados
- **THEN** o sistema SHALL contar os eventos de login registrados na trilha de auditoria

### Requirement: Registro do estado dos componentes ao longo do tempo

O sistema SHALL registrar cada mudança de estado dos componentes monitorados, com o momento em que ela ocorreu, e SHALL NOT registrar um evento por sinal de vida recebido.

#### Scenario: Mudança de estado registrada
- **WHEN** um componente passar de disponível para indisponível, ou o contrário
- **THEN** o sistema SHALL registrar a mudança com o momento em que ela ocorreu

#### Scenario: Sinal de vida sem mudança
- **WHEN** um componente enviar sinal de vida mantendo o estado que já tinha
- **THEN** o sistema SHALL NOT registrar evento algum

### Requirement: Exportação das tabelas

O sistema SHALL permitir exportar qualquer tabela de relatório nos formatos CSV e XLSX, aplicando os mesmos filtros da consulta, exigindo a mesma permissão da aba, e SHALL registrar cada exportação na trilha de auditoria.

#### Scenario: Exportar com os filtros da tela
- **WHEN** o usuário exportar uma tabela
- **THEN** o sistema SHALL gerar o arquivo com os mesmos filtros aplicados na consulta

#### Scenario: Exportação sem permissão
- **WHEN** um usuário sem a permissão da aba tentar exportar uma tabela dela
- **THEN** o sistema SHALL negar a exportação

#### Scenario: Exportação registrada
- **WHEN** uma exportação for concluída
- **THEN** o sistema SHALL registrar na auditoria quem exportou, qual aba, qual tabela e quais filtros

### Requirement: Resultado recente reaproveitado

O sistema SHALL reaproveitar por alguns minutos o resultado já calculado para a mesma clínica, aba e conjunto de filtros, e SHALL informar na resposta quando o resultado veio de reaproveitamento.

#### Scenario: Segunda consulta igual
- **WHEN** a mesma consulta for repetida dentro da janela de reaproveitamento
- **THEN** o sistema SHALL devolver o resultado já calculado, indicando que ele foi reaproveitado

#### Scenario: Filtro diferente não reaproveita
- **WHEN** qualquer filtro mudar
- **THEN** o sistema SHALL calcular de novo

#### Scenario: Clínicas não compartilham resultado
- **WHEN** a mesma consulta for feita por clínicas diferentes
- **THEN** o sistema SHALL calcular separadamente para cada uma

### Requirement: Painel e Relatórios agrupados no menu

O sistema SHALL agrupar Painel e Relatórios sob uma entrada única de menu, apresentando uma página de cartões para a escolha, no mesmo padrão dos outros grupos.

#### Scenario: Abrir o grupo
- **WHEN** o usuário acionar a entrada do grupo
- **THEN** o sistema SHALL apresentar os cartões de Painel e de Relatórios

#### Scenario: Painel continua alcançável
- **WHEN** o usuário escolher o Painel
- **THEN** o sistema SHALL apresentar o painel como ele é hoje

### Requirement: Estado da consulta no endereço da página

O sistema SHALL manter período, comparação e filtros no endereço da página, de modo que o endereço reproduza a mesma consulta.

#### Scenario: Endereço reproduz a consulta
- **WHEN** o endereço de um relatório filtrado for aberto de novo
- **THEN** o sistema SHALL apresentar o mesmo período e os mesmos filtros

#### Scenario: Trocar filtro muda o endereço
- **WHEN** o usuário alterar período ou filtros
- **THEN** o sistema SHALL refletir a alteração no endereço

### Requirement: Ausência de dado é dita, não desenhada como zero

O sistema SHALL informar quando não houver dado para o período em um gráfico ou tabela, e SHALL NOT apresentar ausência de dado como valor zero.

#### Scenario: Gráfico sem dado
- **WHEN** um gráfico não tiver dado no período
- **THEN** o sistema SHALL informar que não há dado no período

#### Scenario: Indicador sem base de cálculo
- **WHEN** um indicador não tiver base para ser calculado
- **THEN** o sistema SHALL apresentá-lo como ausente, e SHALL NOT apresentá-lo como zero
