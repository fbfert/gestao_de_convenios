## Context

O sistema já possui um conjunto amplo de telas operacionais e um catálogo fixo de permissões por papel. Hoje a navegação começa em uma área transacional, o que faz o usuário abrir o sistema sem uma visão consolidada do estado da operação.

O Dashboard precisa ser a página inicial para todos os usuários autenticados, mas com blocos configuráveis por perfil para não expor ruído desnecessário em papéis menos privilegiados.

## Goals / Non-Goals

**Goals:**
- Tornar o Dashboard a primeira tela do sistema após login.
- Exibir resumos operacionais e atalhos úteis em um único lugar.
- Permitir que a gestão de permissões controle a visibilidade dos blocos do Dashboard por papel.
- Manter um catálogo fixo de permissões para suportar novas funções sem criação livre de permissões.

**Non-Goals:**
- Recriar as páginas operacionais existentes dentro do Dashboard.
- Substituir o sistema de permissões atual por um novo modelo.
- Implementar relatórios analíticos complexos ou exportações nesta primeira entrega.

## Decisions

- Usar endpoints de resumo no backend para agregar dados do Dashboard.
  - Alternativa considerada: consumir cada lista existente e agregar no frontend.
  - Racional: evita múltiplas chamadas, simplifica a página e permite ajustar métricas sem duplicar lógica de consulta.

- Reaproveitar o catálogo fixo de permissões e os papéis já existentes para controlar a visibilidade dos blocos.
  - Alternativa considerada: criar uma tabela específica para preferências do Dashboard.
  - Racional: o produto já tem o conceito de papel/permissão; usar isso evita duplicação conceitual e mantém o ajuste centralizado na tela de permissões.

- Modelar a visibilidade por bloco como permissões de catálogo para cada área do Dashboard.
  - Alternativa considerada: um toggle genérico por painel sem identidade de permissão.
  - Racional: quando novas funções surgirem, elas entram no mesmo fluxo de catalogação e gestão de acesso que o resto do sistema já usa.

- Redirecionar a rota raiz para o Dashboard.
  - Alternativa considerada: abrir uma página intermediária com links.
  - Racional: reduz fricção e dá ao usuário a visão operacional imediatamente.

## Risks / Trade-offs

- [Blocos demais podem poluir a primeira dobra] → agrupar por seções e deixar a configuração por perfil esconder o que for irrelevante.
- [Agregações podem ficar caras] → limitar os endpoints a contagens e recortes recentes, com queries indexadas e sem carregar listas completas.
- [Mudanças no catálogo de permissões podem exigir ajustes futuros] → manter o catálogo em seed/código e cobrir com testes.

## Migration Plan

1. Criar os endpoints de dashboard e o modelo de visibilidade por papel.
2. Atualizar o menu e o redirecionamento inicial para `/dashboard`.
3. Inserir as permissões de Dashboard no catálogo fixo e expor a configuração na tela de permissões.
4. Validar com testes de API e build do frontend.
5. Se necessário, ajustar os papéis seedados para habilitar os blocos padrão por perfil.

Rollback:
- Reverter as rotas e o redirecionamento para a tela anterior.
- Remover a exibição dos blocos do Dashboard sem afetar o restante do sistema.
- Reverter o catálogo de permissões se a mudança de visibilidade causar regressão.
