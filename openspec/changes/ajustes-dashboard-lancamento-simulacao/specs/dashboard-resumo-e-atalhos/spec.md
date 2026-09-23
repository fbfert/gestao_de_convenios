## ADDED Requirements

### Requirement: Atalho para relatórios no dashboard

O sistema SHALL oferecer, no Acesso rápido do dashboard e ao lado do atalho para a auditoria, um atalho para a tela de Relatórios.

O atalho SHALL aparecer somente para quem tiver permissão de ver ao menos um relatório.

#### Scenario: Usuário com acesso a relatórios
- **WHEN** um usuário com permissão de algum relatório abrir o dashboard
- **THEN** o sistema SHALL exibir o atalho "Ver relatórios" levando à tela de Relatórios

#### Scenario: Usuário sem acesso a relatórios
- **WHEN** um usuário sem permissão de relatório algum abrir o dashboard
- **THEN** o sistema SHALL NOT exibir o atalho

### Requirement: Antecipações no resumo por área

O sistema SHALL exibir no resumo por área do dashboard, para quem tiver a permissão de ver antecipações no dashboard, dois blocos:

- **Antecipações elegíveis**, com a mesma contagem de entradas que a fila de elegíveis da tela de Antecipações apresenta, levando a essa tela;
- **Antecipações realizadas**, com o total de antecipações geradas e, no detalhe, as geradas no mês corrente e as dispensadas, levando ao histórico filtrado pelas geradas.

#### Scenario: Contagem igual à da fila
- **WHEN** a fila de elegíveis da tela de Antecipações tiver N entradas
- **THEN** o bloco de elegíveis SHALL exibir N

#### Scenario: Realizadas
- **WHEN** a clínica tiver antecipações geradas e dispensadas
- **THEN** o bloco de realizadas SHALL exibir o total de geradas e, no detalhe, as geradas no mês corrente e as dispensadas

#### Scenario: Sem permissão
- **WHEN** o perfil do usuário não tiver a permissão de antecipações no dashboard
- **THEN** o sistema SHALL NOT exibir nenhum dos dois blocos
