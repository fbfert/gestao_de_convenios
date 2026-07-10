# Bloco 6 — Parcial

Checkpoint do frontend após os itens 1 a 3.

## O que entrou

- Scaffold Vite React TS em [`web/`](../web/)
- Dependências instaladas:
  - `@tanstack/react-query`
  - `axios`
  - `zustand`
  - `react-router-dom`
  - `tailwindcss`
  - `@tailwindcss/vite`
- `.env` local do frontend com `VITE_API_URL=http://localhost:8000/api`
- Cliente HTTP com Bearer token e reação a `401`
- Store de auth persistida em `localStorage`
- Hooks de login/logout com React Query
- Rotas protegidas com shell autenticado
- Página funcional de login
- Tailwind configurado no Vite
- CORS da API preparado para `http://localhost:5173` e `Authorization`

## Validação feita aqui

- `npm run build`
- `npm run lint`

## Pendências

- `statusLabels.ts` central ainda não foi criado
- estrutura vazia de `src/features/<dominio>/` ainda não foi criada
- validação manual no navegador ainda falta

