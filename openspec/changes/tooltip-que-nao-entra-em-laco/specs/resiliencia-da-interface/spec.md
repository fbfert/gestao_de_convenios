## ADDED Requirements

### Requirement: Componente que se posiciona medindo o DOM converge

O sistema SHALL garantir que todo componente que ajuste a própria posição a partir de uma medição do DOM chegue a um resultado num número fixo de passos — e SHALL NOT fazer uma medição depender do resultado da anterior.

Medir o painel depois de aplicar o deslocamento, para calcular o próximo deslocamento, é um laço: basta a segunda medição não devolver exatamente o mesmo número — zoom do navegador, escala do Windows, barra de rolagem que aparece — para o componente gravar estado sem parar, e o React encerrar a árvore inteira. Foi o que derrubou a tela de Solicitações em 24 e 25/09/2026, seis vezes, sempre ao passar o mouse na dica da coluna Info.

O componente SHALL medir a posição natural — com o deslocamento zerado — **uma vez por abertura**, e SHALL gravar o deslocamento como número inteiro de pixels.

#### Scenario: Painel encostado na borda
- **WHEN** um tooltip abrir com o painel ultrapassando a borda da viewport
- **THEN** o sistema SHALL deslocá-lo para caber, com uma única medição, e a página SHALL continuar funcionando

#### Scenario: Medição que não se repete
- **WHEN** duas medições seguidas do mesmo painel devolverem números diferentes
- **THEN** o sistema SHALL NOT entrar em laço de renderização, porque não há segunda medição de que dependa

#### Scenario: Fechar e reabrir
- **WHEN** o tooltip fechar e abrir de novo
- **THEN** o sistema SHALL medir de novo, uma vez, e SHALL posicionar o painel a partir da posição natural — não da anterior
