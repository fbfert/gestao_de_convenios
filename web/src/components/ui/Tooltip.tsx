import { useId, useLayoutEffect, useRef, useState, type ReactNode } from 'react'

/**
 * Dica de ajuda ao lado do rotulo de um campo.
 *
 * Abre no hover, no foco do teclado e no toque: so no hover, a explicacao
 * ficaria inacessivel para quem navega por tab e para telas de toque. O painel
 * fica dentro do mesmo wrapper do botao, entao continua aberto enquanto o mouse
 * passa por cima do texto — necessario para dicas de mais de uma linha.
 *
 * Duas decisoes de layout que existem por causa do mobile (25/08/2026):
 *
 * 1. O painel so entra no DOM quando aberto. Antes ele ficava sempre montado
 *    com `invisible`, que esconde da vista mas MANTEM a caixa no layout — um
 *    painel de 288px ancorado em `left-0` perto da margem direita esticava a
 *    pagina inteira para 634px num viewport de 390px. Era a causa do scroll
 *    horizontal em 12 das 14 telas de lista do sistema, e nao as tabelas.
 *    O texto continua disponivel para leitor de tela pelo `sr-only` abaixo,
 *    que e `position:absolute` de 1px e nao empurra nada.
 *
 * 2. Aberto, o painel se desloca para caber na viewport. Ancoragem fixa
 *    (`left-0` ou `right-0`) sempre estoura de um dos lados dependendo de onde
 *    o gatilho caiu na linha; medir e a unica forma de acertar nos dois.
 */

const MARGEM_VIEWPORT = 12

const iconeAjuda = (
  <svg aria-hidden="true" viewBox="0 0 16 16" className="h-3 w-3">
    <circle cx="7" cy="7" r="4.25" fill="none" stroke="currentColor" strokeWidth="1.6" />
    <path d="M10.2 10.2 14 14" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
  </svg>
)

/** Lupa: usada quando o tooltip revela um dado (ex.: resultado de uma consulta) em vez de explicar um campo. */
export const iconeLupa = (
  <svg aria-hidden="true" viewBox="0 0 16 16" className="h-3 w-3">
    <circle cx="6.5" cy="6.5" r="4" fill="none" stroke="currentColor" strokeWidth="1.6" />
    <path d="M9.5 9.5 14 14" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
  </svg>
)

export function Tooltip({
  children,
  rotulo = 'O que significa este campo?',
  icone = iconeAjuda,
}: {
  children: ReactNode
  rotulo?: string
  /** Ícone do gatilho — "?" por padrão (explica um campo); passe `iconeLupa` para revelar um dado. */
  icone?: ReactNode
}) {
  const id = useId()
  const [aberto, setAberto] = useState(false)
  const [deslocamentoX, setDeslocamentoX] = useState(0)
  const painelRef = useRef<HTMLSpanElement>(null)

  /*
   * UMA medicao por abertura, e nenhuma que dependa da anterior.
   *
   * Corre antes da pintura para o painel nunca aparecer na posicao errada e
   * pular. Zera o `transform` no proprio DOM antes de medir: assim a medicao
   * e a posicao natural do painel, independente de qualquer render anterior,
   * e o efeito grava o deslocamento uma vez e nao roda de novo ate fechar.
   *
   * ISTO JA FOI UM LACO. A versao anterior dependia de `[aberto, deslocamentoX]`
   * e descontava o deslocamento da medicao para achar a posicao natural. O
   * desconto resolve quando a segunda medicao e exata — e NAO resolve quando
   * ela nao e: zoom do navegador, Windows a 125%, barra de rolagem aparecendo
   * com o painel aberto. Ai `natural + novo` medido nao devolve `natural`, o
   * proximo `novo` difere por uma fracao, `novo !== deslocamentoX` e sempre
   * verdadeiro, e o React desiste na 50a renderizacao com o erro 185. Foi o
   * que derrubou a tela de Solicitacoes em 24 e 25/09/2026, seis vezes, sempre
   * na dica da coluna Info — que fica na borda direita.
   *
   * `Math.round` tira a fracao de pixel do `translateX`. Nao e o que impede o
   * laco (isso e a dependencia unica), mas e onde o subpixel comecava a se
   * acumular; um pixel num painel de 288px ninguem ve.
   */
  useLayoutEffect(() => {
    if (!aberto) {
      setDeslocamentoX(0)
      return
    }

    const painel = painelRef.current
    if (!painel) return

    painel.style.transform = 'none'
    const caixa = painel.getBoundingClientRect()
    const limite = document.documentElement.clientWidth

    let novo = 0
    if (caixa.right > limite - MARGEM_VIEWPORT) {
      novo = limite - MARGEM_VIEWPORT - caixa.right
    }
    if (caixa.left + novo < MARGEM_VIEWPORT) {
      novo = MARGEM_VIEWPORT - caixa.left
    }

    setDeslocamentoX(Math.round(novo))
  }, [aberto])

  return (
    <span
      className="group relative inline-flex align-middle"
      onPointerEnter={(evento) => {
        // Toque dispara pointerenter tambem, mas la quem manda e o clique do
        // botao: sem esta guarda o painel abriria e fecharia no mesmo gesto.
        if (evento.pointerType === 'mouse') setAberto(true)
      }}
      onPointerLeave={(evento) => {
        if (evento.pointerType === 'mouse') setAberto(false)
      }}
      onFocus={() => setAberto(true)}
      onBlur={() => setAberto(false)}
      onKeyDown={(evento) => {
        if (evento.key === 'Escape') setAberto(false)
      }}
    >
      <button
        type="button"
        aria-label={rotulo}
        aria-describedby={id}
        aria-expanded={aberto}
        onClick={() => setAberto((estava) => !estava)}
        className="flex h-6 w-6 items-center justify-center rounded-full border border-white/15 bg-white/5 text-slate-300 transition hover:border-cyan-300/50 hover:bg-cyan-400/10 hover:text-cyan-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/40"
      >
        {icone}
      </button>

      {/* Fonte do texto para leitor de tela — existe sempre, para o
          `aria-describedby` nunca apontar para um id ausente. */}
      <span id={id} className="sr-only">
        {children}
      </span>

      {aberto ? (
        <span
          ref={painelRef}
          role="tooltip"
          aria-hidden="true"
          style={{ transform: `translateX(${deslocamentoX}px)` }}
          className="absolute left-0 top-full z-(--z-tooltip) mt-2 w-72 max-w-[calc(100vw-1.5rem)] rounded-xl border border-white/10 bg-slate-900 p-3 text-left text-meta font-normal leading-5 text-slate-200 shadow-e3 shadow-slate-950/50"
        >
          {children}
        </span>
      ) : null}
    </span>
  )
}
