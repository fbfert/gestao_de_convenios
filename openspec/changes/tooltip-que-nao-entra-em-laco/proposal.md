## Why

Em 24 e 25/09/2026 a tela de Solicitações caiu seis vezes na clínica, sempre ao filtrar por paciente. É o primeiro erro que o registro de erros do navegador (change `tela-de-erro-em-vez-de-tela-branca`) capturou de verdade: "Minified React error #185" — laço de `setState` —, com a pilha de componentes apontando um único componente, que o sourcemap do bundle de produção traduziu para `Tooltip.tsx`.

O `Tooltip` desloca o painel para caber na viewport medindo o próprio painel num `useLayoutEffect` que depende do deslocamento que ele mesmo grava. Encostado na borda direita e numa coordenada com fração de pixel — zoom do navegador, Windows a 125% —, a medição seguinte não devolve exatamente o mesmo número; o efeito grava de novo, e o React desiste. A coluna "Info" da lista de Solicitações fica na borda direita, e é por isso que o filtro por paciente é o gatilho: é ali que alguém passa o mouse.

## What Changes

- O `Tooltip` passa a medir a posição natural do painel **uma vez por abertura**, com o deslocamento zerado, e a gravar um deslocamento inteiro — não há segunda medição para divergir da primeira
- Nova verificação de ponta a ponta que abre um tooltip encostado na borda, em escala fracionária, e afirma que a página sobrevive
- Nenhuma mudança visível: o painel continua cabendo na tela, como antes

## Capabilities

### Modified Capabilities
- `resiliencia-da-interface`: componente que se posiciona medindo o DOM tem de convergir — uma medição por abertura, nunca uma que dependa do resultado da anterior

## Impact

- `web/src/components/ui/Tooltip.tsx`
- `web/src/routes/RotaDeErroSimulado.tsx`, `web/src/routes/AppRoutes.tsx` (andaime só do modo e2e)
- `web/tests/e2e/tooltip-na-borda.spec.ts` (novo)
- Sem migration, sem dependência nova. Deploy é bundle
