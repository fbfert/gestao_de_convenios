# Design System — xiax-agenda

**Para quem vai construir o sistema irmão.** Este documento é a receita completa da pele do
xiax-agenda: os valores exatos, as regras que valem, e as armadilhas que já custaram caro aqui.
Copiando o bloco de tokens da §3 e seguindo as regras da §11, as duas telas ficam indistinguíveis.

> Versão do DS: **1.0.0** · documento gerado em **26/08/2026** a partir do código em produção
> (`web/src/index.css`, `web/src/design-system.contrato.test.tsx`).

---

## 1. A regra de ouro

> **Base neutra + UM accent. Cor com contenção.**

O sistema que substituímos era funcionalmente completo e **visualmente poluído**: cores saturadas
por todo lado, hierarquia fraca, densidade sem respiro. A direção aqui é o oposto — **bonito,
calmo, profissional e funcional**.

Três consequências práticas, e elas não são negociáveis:

1. **Status é chip discreto** — cor suave + ícone + rótulo. **Nunca bloco saturado.**
2. **Cor de categoria é marcador fino** — uma barra de 3px na lateral do card, jamais o card
   preenchido inteiro. É isso que elimina a "bagunça de cores" numa tela densa.
3. **Densidade se resolve com whitespace e hierarquia**, não com tudo gritando ao mesmo tempo.

O tom é clínica infantil: **confiável e leve** — não infantilizado, não frio.

---

## 2. Como os tokens são organizados

Dois níveis, e a separação entre eles é o que faz o sistema durar:

| Nível | O que é | Quem consome |
|---|---|---|
| **1 · primitivos** | A paleta crua (`--neutro-600`, `--marca-600`, `--vermelho-texto`) | **Ninguém.** Nenhum componente toca aqui. |
| **2 · semânticos** | O papel (`--texto-suave`, `--acento`, `--perigo-suave`) | **Só este.** Telas e componentes usam exclusivamente o nível 2. |

**Por que isso importa:** quando o tema escuro entrar, um bloco `[data-theme="dark"]` redefine
**só o nível 2** e o produto inteiro acompanha. Se um componente tivesse escrito `--neutro-600`
direto, ele ficaria claro num tema escuro e ninguém saberia por quê.

A mesma regra vale para o irmão: **o componente pede o papel, nunca a cor.**

---

## 3. Os tokens — copie este bloco

São CSS custom properties puras. Funcionam em qualquer stack — Blade, React, Vue, HTML estático.
A ponte da §4 é só para quem usa Tailwind v4.

```css
:root {
  /* ── Nível 1 · neutros QUENTES (matiz ~82–85° OKLCH) ──
     Não são cinzas frios. É o que dá o ar calmo e "de papel" ao produto —
     um #F5F5F5 neutro no lugar de #FAF9F7 já muda o caráter da tela. */
  --neutro-0: #FFFFFF;   --neutro-50: #FAF9F7;  --neutro-100: #F1EFEB;
  --neutro-200: #E5E2DC; --neutro-300: #D3CFC7; --neutro-400: #A9A49B;
  --neutro-450: #8F8A80; --neutro-500: #837E74; --neutro-600: #5F5A51;
  --neutro-700: #494540; --neutro-800: #32302B; --neutro-900: #211F1B;

  /* ── Nível 1 · marca (verde-petróleo) ── */
  --marca-50: #EDF7F5;  --marca-100: #D6EDE9; --marca-200: #ADDCD5;
  --marca-300: #7CC3BA; --marca-400: #47A399; --marca-500: #23867C;
  --marca-600: #16695F; --marca-700: #10554E; --marca-800: #0D443F; --marca-900: #0B3733;

  /* ── Nível 1 · feedback (padrão "tom suave": fundo suave + texto forte) ── */
  --vermelho-600: #B3372E; --vermelho-700: #992F27;
  --vermelho-texto: #8F2B23; --vermelho-suave: #FBEEEC;
  --verde-texto: #1D6B41;   --verde-suave: #EAF5EE;
  --ambar-texto: #8A5A00;   --ambar-suave: #FCF3E1;
  --azul-texto: #1D5C96;    --azul-suave: #EBF3FB;

  /* ── Escalas físicas ── */
  --raio-controle: 6px; --raio-campo: 8px; --raio-superficie: 12px;
  --raio-janela: 16px;  --raio-pilula: 999px;
  --sombra-e1: 0 1px 2px 0 rgb(33 31 27 / 0.05);
  --sombra-e2: 0 2px 8px -1px rgb(33 31 27 / 0.08), 0 1px 2px 0 rgb(33 31 27 / 0.04);
  --sombra-e3: 0 16px 40px -8px rgb(33 31 27 / 0.16), 0 4px 12px -2px rgb(33 31 27 / 0.06);
  --z-fixo: 10; --z-fixo-canto: 11; --z-scrim: 40;
  --z-dialogo: 50; --z-menu: 55; --z-toast: 60; --z-tooltip: 70;
  --duracao-1: 100ms; --duracao-2: 160ms; --duracao-3: 220ms;
  --curva-padrao: cubic-bezier(0.2, 0, 0, 1);
  --curva-saida:  cubic-bezier(0.4, 0, 1, 1);

  /* ── Nível 2 · papéis semânticos (tema claro — o único que existe hoje) ── */
  --fundo: var(--neutro-50);
  --superficie: var(--neutro-0);
  --superficie-elevada: var(--neutro-0);
  --texto: var(--neutro-900);
  --texto-suave: var(--neutro-600);
  --texto-desativado: var(--neutro-400);
  --neutro-desativado: var(--neutro-100);
  --linha: var(--neutro-200);
  --linha-forte: var(--neutro-300);
  --borda-campo: var(--neutro-450);
  --acento: var(--marca-600);
  --acento-intenso: var(--marca-700);
  --sobre-acento: var(--neutro-0);
  --acento-suave: var(--marca-50);
  --selecao: var(--marca-100);
  --foco: var(--marca-600);
  --perigo: var(--vermelho-600);
  --perigo-intenso: var(--vermelho-700);
  --sobre-perigo: var(--neutro-0);
  --perigo-texto: var(--vermelho-texto);
  --perigo-suave: var(--vermelho-suave);
  --sucesso-texto: var(--verde-texto);
  --sucesso-suave: var(--verde-suave);
  --alerta-texto: var(--ambar-texto);
  --alerta-suave: var(--ambar-suave);
  --info-texto: var(--azul-texto);
  --info-suave: var(--azul-suave);
  --tooltip: var(--neutro-800);
  --sobre-tooltip: var(--neutro-0);
}
```

### O que cada papel de cor significa

| Token | Papel |
|---|---|
| `--fundo` | fundo da página / app shell |
| `--superficie` | cards, painéis, inputs |
| `--texto` | texto principal |
| `--texto-suave` | secundário, metadados |
| `--linha` | divisórias decorativas |
| `--linha-forte` | bordas de card, cabeçalho |
| `--borda-campo` | borda de input/select |
| `--acento` | ação primária, link, item ativo |
| `--acento-suave` | seleção leve, hover fantasma |
| `--selecao` | linha/slot selecionado |
| `--perigo` | **botão destrutivo — o ÚNICO bloco sólido do produto** |
| `--perigo-suave` | erros de campo, banners, realces de atenção |
| `--sucesso-suave` | toast de sucesso |
| `--alerta-suave` | avisos |
| `--info-suave` | informativos |

**A linha do `--perigo`:** vermelho sólido é exclusivo do botão destrutivo. Qualquer outra coisa
vermelha na tela usa o par `--perigo-suave` (fundo) + `--perigo-texto` (texto/borda). Isso existe
para que o vermelho que significa *erro de verdade* não seja diluído por vermelhos decorativos.

---

## 4. Ponte para Tailwind v4 (pule se não usar Tailwind)

Cada semântico vira utilitário com o **nome do papel** — `bg-superficie`, `text-texto-suave`,
`border-linha-forte`:

```css
@theme inline {
  --color-fundo: var(--fundo);
  --color-superficie: var(--superficie);
  --color-texto: var(--texto);
  --color-texto-suave: var(--texto-suave);
  --color-linha: var(--linha);
  --color-linha-forte: var(--linha-forte);
  --color-borda-campo: var(--borda-campo);
  --color-acento: var(--acento);
  --color-acento-intenso: var(--acento-intenso);
  --color-sobre-acento: var(--sobre-acento);
  --color-acento-suave: var(--acento-suave);
  --color-selecao: var(--selecao);
  --color-foco: var(--foco);
  --color-perigo: var(--perigo);
  --color-perigo-intenso: var(--perigo-intenso);
  --color-sobre-perigo: var(--sobre-perigo);
  --color-perigo-texto: var(--perigo-texto);
  --color-perigo-suave: var(--perigo-suave);
  --color-sucesso-texto: var(--sucesso-texto);
  --color-sucesso-suave: var(--sucesso-suave);
  --color-alerta-texto: var(--alerta-texto);
  --color-alerta-suave: var(--alerta-suave);
  --color-info-texto: var(--info-texto);
  --color-info-suave: var(--info-suave);
  --color-tooltip: var(--tooltip);
  --color-sobre-tooltip: var(--sobre-tooltip);
  /* únicos primitivos expostos, com papel documentado */
  --color-neutro-100: var(--neutro-100); /* hover de linha */
  --color-neutro-200: var(--neutro-200); /* skeleton */

  --radius-controle: var(--raio-controle);
  --radius-campo: var(--raio-campo);
  --radius-superficie: var(--raio-superficie);
  --radius-janela: var(--raio-janela);
  --radius-pilula: var(--raio-pilula);
  --shadow-e1: var(--sombra-e1);
  --shadow-e2: var(--sombra-e2);
  --shadow-e3: var(--sombra-e3);
  --ease-padrao: var(--curva-padrao);
  --ease-saida: var(--curva-saida);
}
```

`z-index` e `duração` **não têm namespace** no Tailwind v4 — consuma via
`z-(--z-menu)` e `duration-(--duracao-2)`.

---

## 5. Tipografia — sete papéis, e só eles

```css
--font-display: "Plus Jakarta Sans Var", ui-sans-serif, system-ui, "Segoe UI", Roboto, sans-serif;
--font-texto:   ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;

--text-display:   1.75rem;    /* line-height 1.25 · letter-spacing -0.015em */
--text-titulo:    1.375rem;   /* line-height 1.3  · letter-spacing -0.01em  */
--text-subtitulo: 1.125rem;   /* line-height 1.4                            */
--text-corpo-lg:  1rem;       /* line-height 1.5                            */
--text-corpo:     0.875rem;   /* line-height 1.5   ← o corpo padrão         */
--text-rotulo:    0.8125rem;  /* line-height 1.4  · font-weight 500         */
--text-meta:      0.75rem;    /* line-height 1.35                           */
```

Títulos (`h1`–`h3`) usam `--font-display`; o resto usa `--font-texto`.

**Nunca use a escala crua do framework** (`text-sm`, `text-xs`, `text-lg`…). Não é purismo:
`text-sm` traz line-height 1.43 e `text-corpo` traz 1.5. Uma área do sistema escrita com a escala
crua **desalinha o ritmo vertical do produto inteiro** — e foi exatamente o que aconteceu aqui
(§12). Cada papel carrega line-height e peso próprios que a classe crua não herda.

---

## 6. Raio, sombra, empilhamento, movimento

**Raio por papel**, não por tamanho: `controle` 6px (botão, chip) · `campo` 8px (input, card) ·
`superficie` 12px (painel) · `janela` 16px (dialog) · `pilula` 999px.

**Sombra em três níveis:** `e1` repouso · `e2` elevado (dropdown, card em hover) · `e3` flutuante
(dialog, popover). Repare que todas usam `rgb(33 31 27 / …)` — a sombra é **quente**, do mesmo
matiz dos neutros. Sombra cinza-azulada sobre neutro quente suja a tela.

**Empilhamento — a ordem tem motivo:**
`z-fixo 10` → `z-fixo-canto 11` → `z-scrim 40` → `z-dialogo 50` → `z-menu 55` → `z-toast 60` →
`z-tooltip 70`.

- **`menu` (55) acima de `dialogo` (50)** porque um dropdown aberto *dentro* de um dialog precisa
  ficar na frente, não atrás da janela que o contém.
- **`fixo-canto` (11) acima de `fixo` (10)** porque o canto duplamente sticky de uma grade (a
  célula "Horário") cruza os cabeçalhos de coluna ao rolar — no mesmo z, quem vem depois no DOM
  cobriria o canto.

**Movimento:** `100ms` micro-interação · `160ms` padrão · `220ms` entrada de camada.
Curva de entrada `cubic-bezier(0.2, 0, 0, 1)`, de saída `cubic-bezier(0.4, 0, 1, 1)`.

---

## 7. O que colar na base, sempre

```css
body {
  background: var(--fundo);
  color: var(--texto);
  font-family: var(--font-texto);
  -webkit-font-smoothing: antialiased;
}
h1, h2, h3 { font-family: var(--font-display); }

/* foco visível universal — NUNCA suprimir (WCAG 2.4.7) */
:focus-visible { outline: 2px solid var(--foco); outline-offset: 2px; }

/* foco nunca escondido sob cabeçalho fixo (2.4.11) */
[id], [tabindex], a, button, input, select, textarea { scroll-margin-top: 4rem; }

/* movimento reduzido: colapsa tudo */
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}
```

---

## 8. Componentes

Os 22 do sistema, construídos sobre **Radix** (headless) + `class-variance-authority` para as
variantes:

`aviso-erro` · `botao` · `campo-busca` · `campo-checkbox` · `campo-cor` · `campo-select` ·
`campo-texto` · `campo-texto-longo` · `chip-status` · `dialog` · `dialog-confirmacao` ·
`dica-visual` · `envelope-campo` · `estado-erro` · `estado-vazio` · `lista-descricao` ·
`marcador-especialidade` · `paginacao` · `popover` · `skeleton` · `tabela` · `toast`

### O botão, como referência de API

```js
variantes: {
  primario:   'bg-acento text-sobre-acento hover:bg-acento-intenso',
  secundario: 'border border-borda-campo bg-superficie text-texto hover:bg-fundo',
  perigo:     'bg-perigo text-sobre-perigo hover:bg-perigo-intenso',
  fantasma:   'text-texto hover:bg-acento-suave',
}
tamanhos: { sm: 'h-8 px-3 text-meta', md: 'h-10 px-4 text-corpo', lg: 'h-11 px-5 text-corpo' }
```

Regras de uso: **um `primario` por região de tela**; `perigo` só dentro do diálogo de confirmação
ou depois dele; botão só-ícone exige nome acessível.

**Detalhe que vale copiar:** o estado desabilitado usa `aria-disabled`, não `disabled`, quando o
botão precisa **explicar por que está travado**. `disabled` tira o elemento do ciclo de Tab e o
motivo (via `aria-describedby`) some junto — o usuário fica com um botão morto e nenhuma pista.

### Estados obrigatórios

Todo controle tem os cinco: **repouso · hover · foco · ativo · desabilitado**. E toda tela
assíncrona tem os quatro: **carregando · vazio · erro · com dado** — daí existirem componentes
dedicados de `skeleton`, `estado-vazio` e `estado-erro`.

### Confirmação antes de destruir

Excluir, desativar, cancelar, e qualquer alteração de regra passam por **diálogo de confirmação
que nomeia o alvo e diz a consequência**. Nada irreversível em um clique.

---

## 9. Cor que vem do banco (o caso mais delicado)

Nos dois sistemas há cor **cadastrada pelo cliente** — status de agendamento, categorias,
especialidades. É o ponto onde um design system normalmente desmorona: basta um tenant escolher
vermelho puro e a tela vira o sistema poluído que se queria evitar.

**A solução aqui:** a cor crua do banco **nunca** vai direto para a tela. Ela passa por uma função
que deriva um trio suave (fundo / texto / borda) com contraste AA **por construção**. Mesmo que o
cliente cadastre `#FF0000`, o chip renderiza discreto e legível.

Três regras que acompanham:

1. **Cor nunca é canal único.** O chip sempre traz **ícone + rótulo em texto**. Quem não distingue
   as cores lê o nome.
2. **Ícone vem por slug com allowlist.** Slug desconhecido cai num ícone neutro — nunca renderiza
   o que veio do banco sem validar.
3. **Cor inválida ou maliciosa cai no tom neutro.** Um valor como `red;position:fixed` não pode
   virar CSS.

E a cor de categoria (especialidade, no nosso caso) aparece como **marcador de 3px na lateral do
card** — nunca como preenchimento. A paleta default de 12, já harmonizada com os neutros quentes:

```
#A85550  #A25E2B  #8F6B09  #707920  #448247  #09856E
#0A818B  #177BA8  #526FB2  #7763AB  #915A95  #A35475
```

---

## 10. Acessibilidade — o piso, não o extra

Alvo: **WCAG 2.2 AA** nos fluxos principais.

- **Contraste** 4,5:1 para texto, 3:1 para borda de campo e anel de foco. **Calculado, nunca
  estimado** — veja §11.
- **Cor nunca é o único sinal.** Sempre acompanha ícone + rótulo.
- **Teclado fluido.** A recepção opera rápido; todo fluxo principal se completa sem mouse. Todo
  gesto de mouse (arrastar, por exemplo) tem alternativa nomeada por teclado.
- **Alvos de toque de 24×24 no mínimo** (2.5.8).
- **Foco nunca suprimido** e nunca escondido sob cabeçalho fixo.
- **Hierarquia de cabeçalhos sem salto** — `h1` → `h2` → `h3`. Se um nível só existe visualmente,
  ele entra invisível (`sr-only`), não se pula.

---

## 11. As regras FISCALIZADAS por teste

Esta é a parte que faz o design system sobreviver ao segundo ano. **Cada regra abaixo é um teste
automatizado que reprova o build** — não é convenção de code review, que se esquece.

Recomendo fortemente portar esta suíte. Ela é barata (lê os arquivos-fonte como texto) e cada
regra nasceu de um estrago real.

### 11.1 Contraste calculado a partir do próprio CSS

O teste lê o `index.css`, resolve as variáveis (inclusive `var()` encadeado) e calcula a razão de
contraste de **cada par semântico documentado**. Nada é estimado a olho.

Os 25 pares fiscalizados incluem: `texto`/`superficie`, `texto`/`fundo`, `texto-suave`/`superficie`,
`acento`/`superficie`, `sobre-acento`/`acento`, `perigo-texto`/`perigo-suave`,
`sucesso-texto`/`sucesso-suave`, `alerta-texto`/`alerta-suave`, `sobre-tooltip`/`tooltip` — a 4,5:1;
e `foco`/`superficie`, `borda-campo`/`fundo` — a 3:1.

**Consequência:** ninguém consegue trocar um token para um tom mais bonito e quebrar o contraste
sem o build reprovar.

### 11.2 Nenhum valor mágico

Varrendo todo componente de produção, **reprova**:

| Proibido | Por quê |
|---|---|
| Hex literal (`#FFF`, `#B3372E`) | é um token que não passou pela §11.1 |
| `bg-[…]`, `text-[#…]`, `rounded-[…]`, `shadow-[…]`, `z-[…]` | valor arbitrário escapa da escala |
| cor ou raio em `style` inline | mesma fuga, por outra porta |
| `text-sm`, `text-xs`, `text-lg`… | escala crua do framework (§5) |
| `shadow-lg`, `z-50` | elevação e empilhamento de outro projeto |
| `dark:` | o tema escuro ainda não existe; antecipar cria pele morta não testada |

> A guarda do hex é **cega a comentários** — ela reprova `#B3372E` até dentro de um `/* */`. É
> deliberado: hex em comentário é exatamente como um hex acaba copiado para o código.

### 11.3 Classe com cara de token que não existe

A mais valiosa das guardas, e a menos óbvia. `border-borda` (o token é `borda-campo`),
`bg-neutro-50`, `text-legenda` — o framework **não gera nada** para elas. O componente renderiza
sem erro nenhum, só que **sem a pele da casa**: a borda não pinta, o fundo não pinta.

Foi assim que o produto passou a ter caixa com contorno ao lado de caixa sem contorno, e nenhum
teste reprovou. Entraram junto com módulos **portados de outro projeto**, que trouxeram os nomes
de lá — que é precisamente o risco de vocês ao copiar este documento.

O teste extrai os tokens realmente declarados no CSS e reprova qualquer `bg-*`/`text-*`/`border-*`
que não seja token existente nem utilitário estrutural conhecido.

### 11.4 O compositor de classes não pode descartar token

Se usarem `tailwind-merge`, **leiam isto**. A biblioteca precisa saber que `text-corpo` é
**tamanho** e `text-sobre-acento` é **cor**. Sem configurar, ela classifica os dois como cor, e o
último vence.

O estrago aqui foi silencioso e chegou a produção: o botão primário compõe `text-sobre-acento`
(cor) com `text-corpo` (tamanho) e **perdia a cor**, pintando o rótulo em `--texto` sobre o verde
do acento — **2,52:1** medido na tela, contra o piso de 4,5:1.

O contrato de contraste da §11.1 **não via nada**: ele confere os tokens, não o que a tela pinta.

```js
const twMerge = extendTailwindMerge({
  extend: {
    classGroups: {
      'font-size': [{ text: ['display','titulo','subtitulo','corpo-lg','corpo','rotulo','meta'] }],
    },
  },
})
```

---

## 12. Armadilhas que já custaram caro aqui

Quatro histórias curtas. Cada uma virou uma guarda da §11.

1. **A escala crua entrou por uma área inteira.** Um módulo chegou com 15 `text-sm`/`text-xs`
   enquanto o resto do produto tinha zero. O ritmo vertical daquela área divergiu do sistema
   **por construção**, e nenhum teste reprovou — a fiscalização olhava cor, raio, sombra e z, e a
   tipografia passava batido.

2. **Módulo portado trouxe nomes de token de outro projeto.** Classes que não pintam nada,
   componentes que renderizam "certo" e ficam sem pele. Ver §11.3 — é o risco direto de quem copia
   um design system de fora, ou seja, o de vocês.

3. **O merge de classes apagou uma cor em produção.** Ver §11.4. Contraste 2,52:1 num botão
   primário, com todos os testes de token verdes.

4. **Realce só na borda não é visto.** Marcamos "fim de pacote" com borda vermelha de 1px, com o
   argumento sólido de não diluir o vermelho de erro. Numa tela cheia, ninguém via. Virou
   preenchimento `perigo-suave` **+** borda — e aí a medição mostrou por que as duas: o
   preenchimento sozinho dá **1,08:1** contra o papel da grade, invisível pelo critério 1.4.11;
   quem separa o card do fundo é a borda, com 7,86:1.
   **A lição:** "chamar atenção" e "ser legível" são dois canais, e nenhum substitui o outro. E
   vermelho **sólido** não era opção — sobre `#B3372E` o texto do card cai para 2,74:1 / 1,14:1 /
   1,38:1, e a única cor que passaria (branco) tornaria ilegíveis os chips coloridos vindos do
   banco.

---

## 13. Checklist para começar

- [ ] Copiar o bloco de tokens da **§3** verbatim. Não ajuste tons "só um pouquinho" — os neutros
      quentes e o verde-petróleo são a identidade, e mexer num muda o caráter do conjunto.
- [ ] Colar a base da **§7** (foco visível, `scroll-margin`, movimento reduzido).
- [ ] Adotar os **sete papéis tipográficos** da §5 e banir a escala crua.
- [ ] Se usar `tailwind-merge`, configurar os papéis de tamanho (**§11.4**) — antes de escrever
      o primeiro botão.
- [ ] Portar a suíte de fiscalização da **§11**. É o que impede a deriva silenciosa.
- [ ] Tratar cor vinda do banco pelo clamp da **§9**, nunca direto na tela.
- [ ] Nenhum componente lendo token de **nível 1**.

---

*Documento preparado a partir do código do xiax-agenda em 26/08/2026. Em caso de divergência, a
fonte da verdade é `web/src/index.css` e `web/src/design-system.contrato.test.tsx`.*
