# Bloco 7 — Resumo

Resumo do frontend navegável do MVP, validado em Chrome real antes da camada E2E.

## Telas entregues

- [`web/src/features/solicitacoes/SolicitacoesPage.tsx`](../web/src/features/solicitacoes/SolicitacoesPage.tsx)
- [`web/src/features/guias/GuiasPage.tsx`](../web/src/features/guias/GuiasPage.tsx)
- [`web/src/features/antecipacoes/AntecipacoesPage.tsx`](../web/src/features/antecipacoes/AntecipacoesPage.tsx)
- [`web/src/features/lancamentos/LancamentosPage.tsx`](../web/src/features/lancamentos/LancamentosPage.tsx)
- [`web/src/features/conciliacao/ConciliacaoPage.tsx`](../web/src/features/conciliacao/ConciliacaoPage.tsx)

## Decisões registradas

- O `referenceData.ts` foi substituído por queries reais da API.
- O shell ganhou navegação direta para os 5 domínios do MVP.
- ADR-06 segue aplicado no frontend com `translateStatus()`.
- ADR-13 segue aplicado na prática via isolamento cross-tenant observado no browser.

## Resultado final

- Solicitações: criar, listar, aprovar e negar.
- Guias: criar, finalizar, negar e disparar conciliação.
- Antecipações: painel de cota utilizada vs autorizada.
- Lançamentos: registro de sessão refletindo em tempo real na cota.
- Conciliação: gerar, conferir e pagar com trava visual no botão de pagamento.

## Validações feitas

- `npm run build`
- `npm run lint`
- `php artisan test`
- Browser real para cada domínio
- Logout e guard de rota sem autenticação
- Isolamento cross-tenant via browser

