## Why

O frontend hoje só tem um visual escuro, fixo no CSS base e nas classes Tailwind de cada tela. Usuários que trabalham em ambientes claros (recepção, salas com muita luz natural) pedem uma versão mais clara e legível. É preciso permitir a troca sem duplicar o CSS de ~11k linhas de JSX já escritas.

## What Changes

- Introduzir dois temas visuais para o app web: `escuro` (atual, padrão) e `claro` (novo).
- Reimplementar a paleta como variáveis CSS de tema (`@theme` do Tailwind v4), redefinidas em `:root[data-theme='claro']`, de modo que as classes utilitárias existentes (`text-slate-200`, `bg-white/5`, `bg-cyan-400`, ...) resolvam automaticamente para o tema ativo.
- Persistir a preferência do usuário no navegador e aplicá-la antes da primeira pintura (sem flash de tema errado).
- Expor o seletor de tema na aba **Geral** de Configurações.
- Manter o layout de impressão (`print:`) sempre em papel branco com texto escuro, independente do tema.

## Capabilities

### New Capabilities

- `tema-visual`: escolha e persistência do tema visual (claro/escuro) do frontend.

### Modified Capabilities

- Nenhuma. Não há mudança de comportamento funcional, apenas de apresentação.

## Impact

- Frontend React/Tailwind: `web/index.html`, `web/src/index.css`, `web/src/main.tsx`, novo `web/src/stores/themeStore.ts` e a aba Geral de `ConfiguracoesPage`.
- Sem impacto na API, no banco ou no worker. A preferência é local ao navegador (não é dado de tenant).

## Non-Goals

- Não há tema por tenant nem persistência da preferência na API.
- Não há opção "seguir o sistema operacional" nesta etapa.
- Não há redesenho de telas: componentes e classes existentes permanecem como estão.
