## Why

Um profissional da clínica tem deficiência de visão de cor e pediu que cada cartão tivesse borda
bem marcada. Ao medir a paleta padrão sob deuteranopia — a forma mais comum —, apareceu um problema
maior que o pedido: `sucesso` e `perigo` ficam a distância perceptual 18, e 14 sob protanopia. Na
prática, "Aprovado" e "Negado" são quase a mesma cor para ele. Borda grossa não resolveria isso.

## What Changes

- Segundo tema, "Alto contraste", com paleta de feedback redesenhada na oposição azul/laranja e
  `info` neutro. Os dez pares semânticos foram medidos nas três formas de daltonismo.
- A borda passa a ser o canal cromático: o texto fica escuro para cumprir 4,5:1 e a borda, que tem
  piso de 3:1, carrega a distinção de cor. É também o que o profissional pediu.
- Espessura de 2px em toda borda existente, divisórias e anel de foco de 3px.
- Botão de alternância na barra superior e dentro do painel de menu do celular.
- O contrato da §11 passa a fiscalizar todo bloco `[data-theme]`, não uma lista fixa de temas.

## Capabilities

### New Capabilities

- `tema-alto-contraste`: o tema, a alternância e a garantia de distinguibilidade.

### Modified Capabilities

- `design-system-visual`: deixa de haver um único tema; a alternância volta, agora por necessidade
  de acessibilidade e não por preferência.

## Non-goals

- Reintroduzir o tema escuro. Ele saiu por não ter uso; este entra por ter requisito.
- Acrescentar ícone a cada chip de status. O rótulo em texto já é o canal não cromático que a
  WCAG exige, e ícone por status exigiria decidir o vocabulário de ícones do produto inteiro.
