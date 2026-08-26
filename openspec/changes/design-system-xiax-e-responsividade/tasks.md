## 1. Tokens e tema

- [x] 1.1 Portar os tokens da §3 verbatim, em dois níveis.
- [x] 1.2 Reapontar a paleta nativa do Tailwind para os primitivos, dentro de `@media screen` — a
      impressão precisa de `bg-white` branco de verdade.
- [x] 1.3 Base da §7: foco visível fora de `@layer`, `scroll-margin-top`, movimento reduzido.
- [x] 1.4 Plus Jakarta Sans nos títulos.
- [x] 1.5 Remover o tema escuro: store, seletor, bootstrap, script anti-piscada e bloco CSS.

## 2. Tipografia

- [x] 2.1 Configurar o compositor de classes ANTES de existir o primeiro papel (§11.4).
- [x] 2.2 Declarar os sete papéis com entrelinha, tracking e peso próprios.
- [x] 2.3 Migrar 1.045 ocorrências da escala crua; `text-4xl` e `text-3xl` eram dois tamanhos para
      o mesmo papel e viraram `display`.

## 3. Responsividade

- [x] 3.1 `Tooltip` só entra no DOM quando aberto e se desloca para caber — era a causa do estouro
      horizontal em 12 das 14 listagens, e não as tabelas.
- [x] 3.2 Modo cartão por `data-cartoes`, com dois pontos de corte; rótulo por `data-rotulo`.
- [x] 3.3 Menu em painel abaixo de `lg`, com trava de rolagem e fechamento ao navegar.
- [x] 3.4 Isolar o modelo de impressão em shadow root; `body` do modelo vira `:host`.

## 4. Alinhamento

- [x] 4.1 Altura única de 44px para campo e seletor, no base layer.
- [x] 4.2 Normalizar 21 botões feitos à mão para a altura do `Botao`.
- [x] 4.3 Ampliar 42 alvos de toque para 24px.
- [x] 4.4 `text-slate-500` pintava 4,04:1 sobre branco — passou a `text-texto-suave`.

## 5. Contrato

- [x] 5.1 Escrever as quatro guardas da §11 como script sem dependência nova.
- [x] 5.2 Ligar em `npm run lint` e `npm run ds:check`.
- [x] 5.3 Provar que reprova, injetando cada classe-veneno.
- [x] 5.4 Corrigir as 27 violações que a primeira execução encontrou.
