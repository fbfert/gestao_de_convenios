## Context

A tela de solicitações já lista os registros e oferece ações de aprovar/negar. No backend, as ações existentes chamam endpoints específicos e o serviço só permite transições quando a solicitação está `under_review`, o que impede corrigir uma solicitação negada para aprovada.

## Goals / Non-Goals

**Goals:**
- Tratar `under_review`, `approved` e `denied` como os três estados operacionais selecionáveis na lista.
- Reutilizar o modelo atual de API autenticada, permissões e invalidação de queries.
- Manter tradução de status apenas na UI.

**Non-Goals:**
- Alterar o vocabulário completo de status legado dos filtros.
- Migrar dados existentes.
- Alterar regras de status de guias, antecipações ou conciliação.

## Decisions

- Criar uma mutation genérica de status para solicitações usando endpoint dedicado por status. Racional: preserva o padrão REST atual e evita codificar textos de UI no backend.
- Manter compatibilidade com os endpoints existentes de aprovar/negar enquanto a tela passa a usar as três ações. Racional: reduz impacto em consumidores existentes da API.
- Usar confirmação nativa do navegador para a primeira entrega. Racional: atende ao pedido de popup de confirmação sem introduzir novo componente modal nem dependência.

## Risks / Trade-offs

- [Aprovar novamente pode ressincronizar a guia vinculada] -> O serviço deve continuar usando a sincronização existente e preservar o número da guia quando houver guia.
- [Ações rápidas podem ser clicadas por engano] -> Cada botão exige confirmação antes da mutation.
- [Status visual pode parecer acionável no estado atual] -> O botão do status atual deve ficar desabilitado.
