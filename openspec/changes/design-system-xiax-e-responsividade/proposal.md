## Why

Três problemas separados, com a mesma raiz: a pele do produto nunca teve contrato.

**Identidade.** O gescon e o xiax-agenda (clinica.gestaonossa.com.br) são sistemas irmãos e da mesma
clínica, mas pareciam produtos de empresas diferentes — o gescon em ciano sobre cinza frio, o irmão
em verde-petróleo sobre neutros quentes. O documento `design-system-xiax-agenda.md`, na raiz, é a
receita que o irmão já roda em produção.

**Celular e tablet.** Nenhuma tela cabia. O `Tooltip` ficava `invisible` — escondido da vista, mas
com a caixa ainda no layout — e um painel de 288px ancorado à esquerda esticava a página inteira
para 634px num viewport de 390px, em 12 das 14 listagens. O menu empilhava cinco grupos em três
linhas e dependia de *hover*, gesto que não existe em toque. Tabelas de até 15 colunas só rolavam
na horizontal: nada quebrava, mas ler uma linha exigia arrastar e perder o cabeçalho de vista.

**Deriva silenciosa.** Sem fiscalização, cada tela escolhia o próprio tamanho de fonte, raio, sombra
e altura de controle. Uma fila de filtro tinha campo de 47px, select de 50px e botão de 40px lado a
lado, e nada reprovava.

## What Changes

- Tokens do xiax-agenda 1.0.0 portados verbatim (§3), com a paleta nativa do Tailwind reapontada
  para os primitivos — é o que troca a pele das ~1.500 ocorrências de utilitário cru sem tocar JSX.
- Tema único, como no irmão. O tema escuro foi removido: store, seletor, bootstrap e bloco CSS.
- Sete papéis tipográficos (§5) substituindo a escala crua do framework em 1.045 ocorrências.
- Tabela vira cartão em tela estreita, com ponto de corte por número de colunas.
- Menu mobile, `Tooltip` reescrito, altura única de controle (44px) e alvo de toque mínimo de 24px.
- Suíte de contrato (§11) ligada ao `npm run lint`, reprovando o build.

## Capabilities

### New Capabilities

- `design-system-visual`: tokens, tipografia, tema único, escala de sombra e raio.
- `listagem-responsiva`: tabela em modo cartão e navegação em tela estreita.
- `contrato-de-design`: as quatro guardas automatizadas que impedem a deriva.

### Modified Capabilities

- `guia-detail`: as seções de cartões passam a preencher a fila; o CSS do modelo de impressão
  deixa de vazar para a página.

## Non-goals

- Migrar os raios de campo e controle (`rounded-2xl` em input e botão) para `rounded-campo`/
  `rounded-controle`. É a mesma classe da migração de tipografia e merece guarda própria; hoje
  ela reprovaria o build.
- Adotar o clamp de cor vinda do banco (§9): o gescon não tem cor cadastrável por tenant.
- Trocar as 21 tabelas pelo componente `Tabela` do design system. O modo cartão é opt-in por
  atributo e não exigiu a migração.
