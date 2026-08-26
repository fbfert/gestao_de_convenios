#!/usr/bin/env node
/**
 * Contrato do design system — as regras da §11 do documento do xiax-agenda.
 *
 * Cada guarda aqui nasceu de um estrago real, e o objetivo é que o build
 * reprove antes de a deriva virar produto. Roda com `npm run ds:check`.
 *
 * É um script Node puro, sem framework de teste, porque tudo que ele precisa é
 * ler arquivo como texto — o projeto não tem runner de teste no front (só
 * Playwright e2e) e adicionar um para quatro guardas não se paga.
 */

import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'

const RAIZ = join(fileURLToPath(new URL('.', import.meta.url)), '..')
const CSS = join(RAIZ, 'src/index.css')

const falhas = []
const reprovar = (guarda, detalhe) => falhas.push({ guarda, detalhe })

// ─────────────────────────────────────────────────────────────────────────────
// Utilidades

function arquivosFonte() {
  const saida = []
  const andar = (dir) => {
    for (const nome of readdirSync(dir)) {
      const caminho = join(dir, nome)
      if (statSync(caminho).isDirectory()) andar(caminho)
      else if (/\.tsx?$/.test(nome)) saida.push(caminho)
    }
  }
  andar(join(RAIZ, 'src'))
  return saida
}

/** Resolve `var(--x)` encadeado até chegar num literal de cor. */
function resolver(token, mapa, visitados = new Set()) {
  let valor = mapa.get(token)
  if (!valor || visitados.has(token)) return null
  visitados.add(token)
  const ref = valor.match(/^var\(\s*(--[\w-]+)\s*\)$/)
  return ref ? resolver(ref[1], mapa, visitados) : valor
}

function paraRgb(cor) {
  const hex = cor.trim().replace('#', '')
  if (!/^[0-9a-fA-F]{3}$|^[0-9a-fA-F]{6}$/.test(hex)) return null
  const cheio = hex.length === 3 ? hex.split('').map((c) => c + c).join('') : hex
  return [0, 2, 4].map((i) => parseInt(cheio.slice(i, i + 2), 16))
}

function luminancia([r, g, b]) {
  const canal = (c) => {
    const v = c / 255
    return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4
  }
  return 0.2126 * canal(r) + 0.7152 * canal(g) + 0.0722 * canal(b)
}

function contraste(a, b) {
  const [la, lb] = [luminancia(a), luminancia(b)]
  const [alto, baixo] = la > lb ? [la, lb] : [lb, la]
  return (alto + 0.05) / (baixo + 0.05)
}

/** Lê as declarações `--token: valor` de um bloco delimitado por um seletor. */
function tokensDoBloco(css, seletor) {
  const i = css.indexOf(seletor)
  if (i < 0) return new Map()
  const abre = css.indexOf('{', i)
  let nivel = 0
  let fim = abre
  for (let j = abre; j < css.length; j++) {
    if (css[j] === '{') nivel++
    else if (css[j] === '}' && --nivel === 0) { fim = j; break }
  }
  const mapa = new Map()
  for (const m of css.slice(abre, fim).matchAll(/(--[\w-]+)\s*:\s*([^;]+);/g)) {
    mapa.set(m[1], m[2].trim())
  }
  return mapa
}

// ─────────────────────────────────────────────────────────────────────────────
// §11.1 — contraste calculado a partir do próprio CSS

const css = readFileSync(CSS, 'utf8')

const PARES_45 = [
  ['texto', 'superficie'], ['texto', 'fundo'],
  ['texto-suave', 'superficie'], ['texto-suave', 'fundo'],
  ['acento', 'superficie'], ['acento', 'fundo'],
  ['sobre-acento', 'acento'], ['acento-intenso', 'acento-suave'],
  ['sobre-perigo', 'perigo'], ['perigo-texto', 'perigo-suave'],
  ['sucesso-texto', 'sucesso-suave'], ['alerta-texto', 'alerta-suave'],
  ['info-texto', 'info-suave'], ['sobre-tooltip', 'tooltip'],
]
const PARES_3 = [['foco', 'superficie'], ['borda-campo', 'fundo']]

function conferirContraste(nomeTema, mapa) {
  const checar = (pares, piso) => {
    for (const [frente, fundo] of pares) {
      const cf = resolver(`--${frente}`, mapa)
      const cg = resolver(`--${fundo}`, mapa)
      const rf = cf && paraRgb(cf)
      const rg = cg && paraRgb(cg)
      if (!rf || !rg) {
        reprovar('§11.1 contraste', `${nomeTema}: nao consegui resolver ${frente}/${fundo}`)
        continue
      }
      const razao = contraste(rf, rg)
      if (razao < piso) {
        reprovar('§11.1 contraste',
          `${nomeTema}: ${frente}/${fundo} = ${razao.toFixed(2)}:1 (piso ${piso}:1)`)
      }
    }
  }
  checar(PARES_45, 4.5)
  checar(PARES_3, 3)
}

const base = tokensDoBloco(css, ':root {')
const escuro = new Map([...base, ...tokensDoBloco(css, ":root[data-theme='escuro']")])
conferirContraste('claro', base)
conferirContraste('escuro', escuro)

// ─────────────────────────────────────────────────────────────────────────────
// §11.2 — nenhum valor mágico

const PROIBIDOS = [
  // A guarda do hex e CEGA A COMENTARIO de proposito: hex em comentario e
  // exatamente como um hex acaba copiado para o codigo.
  // `(?<!&)` deixa passar entidade HTML (`&#039;` e apostrofo, nao cor).
  { re: /(?<!&)#[0-9a-fA-F]{3,8}\b/g, motivo: 'hex literal — e um token que nao passou pelo contraste da §11.1' },
  { re: /\b(?:bg|text|border|ring|shadow|rounded|z)-\[[^\]]+\]/g, motivo: 'valor arbitrario escapa da escala' },
  { re: /\btext-(?:xs|sm|base|lg|xl|[2-9]xl)\b/g, motivo: 'escala crua do framework (§5) — use os sete papeis' },
  { re: /\bshadow-(?:sm|md|lg|xl|2xl|inner)\b/g, motivo: 'elevacao de outro projeto — use shadow-e1/e2/e3' },
  { re: /\bz-(?:10|20|30|40|50)\b/g, motivo: 'empilhamento de outro projeto — use z-(--z-*)' },
  { re: /\bdark:/g, motivo: 'o tema aqui e por data-theme, nao pela variante dark:' },
]

// Excecoes com motivo registrado. Sem esta lista a guarda vira ruido e alguem
// desliga a suite inteira — que e o modo real como um contrato morre.
const ISENTOS = new Map([
  // O CSS de tema PRECISA dos literais: e o unico lugar onde a cor nasce.
  ['src/index.css', 'e a origem dos tokens'],
  // Monta um documento HTML autonomo para impressao. Nao e superficie do app:
  // nao segue tema e nao herda o CSS da pagina (roda em shadow root).
  ['src/features/lancamentos/printTemplate.ts', 'documento de impressao, fora do tema'],
])

for (const caminho of arquivosFonte()) {
  const rel = relative(RAIZ, caminho)
  if (ISENTOS.has(rel)) continue // motivo registrado na lista acima
  const texto = readFileSync(caminho, 'utf8')
  for (const { re, motivo } of PROIBIDOS) {
    const achados = [...texto.matchAll(re)].map((m) => m[0])
    if (achados.length) {
      const amostra = [...new Set(achados)].slice(0, 3).join(', ')
      reprovar('§11.2 valor magico', `${rel}: ${achados.length}x ${amostra} — ${motivo}`)
    }
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// §11.3 — classe com cara de token que não existe
//
// A mais valiosa e a menos obvia: `border-borda` (o token e `borda-campo`) nao
// gera NADA. O componente renderiza sem erro, so que sem a pele da casa.

const tokensDeCor = new Set(
  [...css.matchAll(/--color-([\w-]+)\s*:/g)].map((m) => m[1]),
)
const PALETA_NATIVA = /^(?:slate|cyan|rose|emerald|amber|white|black|transparent|current|inherit)(?:-\d{2,3})?(?:\/\d{1,3})?$/
const PAPEIS_TIPOGRAFICOS = /^(?:display|titulo|subtitulo|corpo-lg|corpo|rotulo|meta)$/
// Valores estruturais que compartilham o prefixo mas nao sao cor.
const ESTRUTURAL = {
  text: /^(?:left|center|right|justify|start|end|wrap|nowrap|balance|pretty|ellipsis|clip)$/,
  bg: /^(?:none|cover|contain|center|fixed|local|scroll|repeat|no-repeat|top|bottom|left|right|origin-\w+|clip-\w+|gradient-to-\w+)$/,
  border: /^(?:[0-9]+|[xytrbles]|[xytrbles]-[0-9]+|solid|dashed|dotted|double|hidden|none|collapse|separate|spacing-\w+)$/,
}

for (const caminho of arquivosFonte()) {
  const rel = relative(RAIZ, caminho)
  const texto = readFileSync(caminho, 'utf8')
  const suspeitas = new Set()
  for (const m of texto.matchAll(/\b(bg|text|border)-([a-z][\w-]*(?:\/\d{1,3})?)\b/g)) {
    const [, prefixo, bruto] = m
    // `border-t-cyan-100` e valido: o lado vem antes da cor.
    const valor = prefixo === 'border' ? bruto.replace(/^[xytrbles]-/, '') : bruto
    const nu = valor.replace(/\/\d{1,3}$/, '')
    if (PALETA_NATIVA.test(valor)) continue
    if (tokensDeCor.has(nu)) continue
    if (prefixo === 'text' && PAPEIS_TIPOGRAFICOS.test(nu)) continue
    if (ESTRUTURAL[prefixo]?.test(nu)) continue
    suspeitas.add(`${prefixo}-${valor}`)
  }
  if (suspeitas.size) {
    reprovar('§11.3 token inexistente',
      `${rel}: ${[...suspeitas].slice(0, 5).join(', ')} — nao gera CSS nenhum`)
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// §11.4 — o compositor de classes não pode descartar token

const cn = readFileSync(join(RAIZ, 'src/lib/cn.ts'), 'utf8')
if (!cn.includes('extendTailwindMerge') || !cn.includes("'font-size'")) {
  reprovar('§11.4 merge de classes',
    'src/lib/cn.ts nao declara os papeis de tamanho no grupo font-size — ' +
    'text-corpo seria classificado como COR e apagaria text-sobre-acento')
}

// ─────────────────────────────────────────────────────────────────────────────

if (falhas.length === 0) {
  console.log('Contrato do design system: OK — as quatro guardas da §11 passam.')
  process.exit(0)
}

console.error(`Contrato do design system: ${falhas.length} violacao(oes)\n`)
for (const { guarda, detalhe } of falhas) {
  console.error(`  [${guarda}] ${detalhe}`)
}
process.exit(1)
