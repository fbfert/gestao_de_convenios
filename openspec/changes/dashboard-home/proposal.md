## Why

O sistema ainda abre em uma área operacional genérica, o que obriga o usuário a navegar antes de enxergar os dados mais importantes do dia. Um Dashboard como tela inicial centraliza o estado do sistema, reduz fricção e dá ao administrador um ponto único para acompanhar operação, auditoria e pendências.

## What Changes

- Tornar o Dashboard a página inicial padrão após o login e o primeiro item do menu lateral.
- Criar um Dashboard com blocos de resumo para convênios, guias, solicitações, antecipações, lançamentos, conciliações, pacientes, profissionais, médicos, usuários e auditoria.
- Expor relatórios e atalhos para as telas operacionais existentes a partir do Dashboard.
- Adicionar controle de visibilidade dos blocos do Dashboard por perfil/role dentro da tela de permissões.
- Documentar o Dashboard e seus blocos no catálogo de permissões para que novas funções sigam o mesmo padrão de visibilidade configurável.

## Capabilities

### New Capabilities
- `dashboard-home`: Página inicial com visão geral operacional, relatórios resumidos, atalhos e acesso rápido à auditoria.
- `dashboard-permissions`: Configuração, por papel/perfil, de quais blocos do Dashboard ficam visíveis.

### Modified Capabilities
- Nenhuma.

## Impact

- Frontend React: nova página Dashboard, menu e tela de permissões com configuração de visibilidade.
- API Laravel: novos endpoints de resumo do Dashboard e persistência das preferências de visibilidade por perfil.
- Catálogo de permissões: inclusão de permissões relacionadas ao Dashboard para uso futuro em novas funções e telas.
- Auditoria e operação: atalhos e relatórios consolidados em um ponto de entrada único.
