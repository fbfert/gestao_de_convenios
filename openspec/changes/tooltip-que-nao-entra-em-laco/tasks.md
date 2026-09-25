# Tasks

Ordem: reproduzir primeiro, corrigir depois — o teste tem de reprovar com o código antigo antes de valer alguma coisa.

## 1. Reprodução

- [x] 1.1 Andaime só do modo e2e: rota `/tooltip-na-borda` com um `Tooltip` real encostado na borda direita, em `RotaDeErroSimulado.tsx` e `AppRoutes.tsx`; verificar que o bundle de produção não contém a rota
- [x] 1.2 Teste e2e que abre o tooltip com `getBoundingClientRect` do painel oscilando 0,4px entre chamadas e afirma que não há erro 185 nem tela de erro; verificar que ele REPROVA com o `Tooltip` atual (reprovou: a tela de erro tomou a rota)
- [x] 1.3 Registrar no teste por que a oscilação é injetada: o headless mede sempre igual — verificado com `deviceScaleFactor: 1.25` e gatilho a 40,4px da borda, o código antigo não entrou em laço sozinho

## 2. Correção

- [x] 2.1 `Tooltip`: o `useLayoutEffect` passa a depender só de `[aberto]`, zera o `transform` inline antes de medir e grava `Math.round(novo)` uma vez; verificar que o teste de 1.2 passa
- [x] 2.2 Verificar que o posicionamento continua funcionando: `solicitacao-guias-por-item.spec.ts` (tooltip da coluna Info em 390px sem rolagem horizontal) segue verde
- [x] 2.3 Verificar que o painel coberto pelo teste continua cabendo na viewport (asserção da borda direita)

## 3. Fechamento

- [x] 3.1 Rodar `cd web && npm run lint && npm run build` sem erro; conferir que `dist/` não contém "tooltip-na-borda"
- [ ] 3.2 Rodar `cd web && npm run test:e2e`, verde
- [x] 3.3 Rodar `openspec validate tooltip-que-nao-entra-em-laco --type change --strict` sem erro
- [x] 3.4 Novidade e prompt de deploy: só bundle, sem migration; a conferência é abrir a dica da coluna Info em Solicitações com zoom do navegador em 110% e 125%
