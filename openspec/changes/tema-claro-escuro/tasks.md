## 1. Base de tema

- [x] 1.1 Definir as variáveis de cor do tema claro em `web/src/index.css`, sob `:root[data-theme='claro']` e restritas a `@media screen`.
- [x] 1.2 Tornar o fundo da página e o `color-scheme` dependentes do tema ativo.

## 2. Estado e persistência

- [x] 2.1 Criar `web/src/stores/themeStore.ts` (zustand) com os temas `escuro`/`claro`, leitura e escrita em `localStorage`.
- [x] 2.2 Aplicar o tema persistido em `web/index.html` antes da primeira pintura e sincronizar em `web/src/main.tsx`.

## 3. Interface

- [x] 3.1 Adicionar o seletor de tema na aba Geral de `ConfiguracoesPage`, indicando o tema ativo.

## 4. Validação

- [x] 4.1 `npm run build` e `npm run lint` no `web/`.
- [x] 4.2 Conferir visualmente login, shell, listas, dashboard, dropdown em portal e a aba Geral nos dois temas (via Playwright, com API offline).
- [x] 4.3 `openspec validate tema-claro-escuro`.
