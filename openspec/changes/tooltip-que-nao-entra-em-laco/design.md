## Context

Como o erro foi encontrado importa tanto quanto o erro, porque é a primeira vez que o caminho inteiro funcionou:

1. A clínica viu a tela de erro (não a tela branca) e leu um código.
2. O registro `erro-cliente` no servidor trouxe a mensagem — "Minified React error #185", laço de `setState` — e a pilha de componentes com **um** componente, `bD`, na posição `63:189171` do bundle `index-CYAt4dB7.js`.
3. O build local do mesmo código, com sourcemap, traduziu a posição para `Tooltip.tsx:44` em dois commits diferentes — o trecho do bundle antes do `Tooltip` é estável, então o deslocamento é o mesmo.
4. As seis quedas vieram de `/solicitacoes?paciente=…`: a coluna "Info" da lista tem um tooltip, e fica na borda direita.

O `Tooltip` (25/08/2026) desloca o painel para caber na viewport com um `useLayoutEffect` que:

- mede o painel com `getBoundingClientRect`;
- desconta o deslocamento atual para achar a posição "natural";
- calcula o deslocamento novo e grava se for diferente;
- e depende de `[aberto, deslocamentoX]` — ou seja, roda de novo depois de gravar.

O comentário do próprio código explica o desconto: "sem isso cada medição realimenta a anterior". O desconto resolve o caso em que a segunda medição é exata. Não resolve o caso em que ela **não é**: zoom do navegador, escala do Windows a 125%, barra de rolagem que aparece com o painel aberto. Aí `natural + novo` medido não devolve `natural`, o `novo` seguinte difere por uma fração, `novo !== deslocamentoX` é sempre verdadeiro, e o React desiste na 50ª renderização.

## Goals / Non-Goals

**Goals:**

- O efeito de posicionamento grava estado **no máximo uma vez por abertura**: não há como entrar em laço por construção
- Verificação que reproduz o estímulo real (duas medições diferentes) e reprova com o código antigo
- Zero mudança visível

**Non-Goals:**

- Trocar o tooltip por uma biblioteca de posicionamento. O componente tem 140 linhas e um único ajuste; a mudança é de duas linhas de lógica
- Reproduzir o zoom/escala de verdade no headless. O que importa é o mecanismo, e ele é simulável com exatidão

## Decisions

### Uma medição por abertura, com o deslocamento zerado

O efeito passa a depender só de `[aberto]`. Ao abrir, zera o `transform` do painel no próprio DOM, mede, calcula, grava — e nunca mais roda até fechar. Não existe segunda medição, então não existe medição que dependa da primeira.

Zerar o `transform` inline antes de medir, em vez de descontar o estado, é o que torna a medição independente de qualquer render anterior. E como é `useLayoutEffect`, tudo acontece antes da pintura: o painel não aparece na posição errada nem por um quadro.

### Deslocamento inteiro

`Math.round` no resultado. Não é o que impede o laço (isso é a dependência única), mas tira a fração de pixel do `translateX`, que é onde o subpixel começa a se acumular. Um pixel de imprecisão num painel de 288px não é visível; meio pixel de oscilação derrubou uma tela.

### O teste injeta a medição instável

O headless mede sempre igual — foi verificado: com `deviceScaleFactor: 1.25` e gatilho a 40,4px da borda, o código antigo **não** entrou em laço. O laço precisa de duas medições diferentes, e o ambiente de teste não as produz sozinho.

Então o teste substitui `getBoundingClientRect` do painel por uma versão que oscila 0,4px entre uma chamada e a seguinte. É o estímulo exato do diagnóstico, determinístico, e distingue os dois comportamentos: o código antigo entra no #185, o novo mede uma vez e segue. Verificado nas duas direções.

### Andaime só do modo e2e

A rota `/tooltip-na-borda` segue o precedente de `/erro-simulado` e `/botao-carregando`: existe só em `import.meta.env.MODE === 'e2e'`, vira `null` no bundle de produção, e a rota não é registrada.

## Risks / Trade-offs

**Zerar o `transform` inline toca o DOM fora do React.** Só dentro do `useLayoutEffect`, antes da pintura, e o React reescreve o `style` no render seguinte com o valor gravado. É o mesmo padrão de medir e ajustar que qualquer posicionador usa.

**A largura da viewport pode mudar com o painel aberto** (barra de rolagem aparecendo). O painel não se reajusta até fechar e abrir. É aceitável: um tooltip vive segundos, e a alternativa — reajustar — é exatamente o laço que se está removendo.

**A simulação no teste não é o zoom real.** É o mecanismo, não a causa física. O risco é uma causa física que produza um estímulo diferente do simulado; contra isso, a garantia de "uma medição por abertura" vale para qualquer estímulo, porque não há realimentação possível.
