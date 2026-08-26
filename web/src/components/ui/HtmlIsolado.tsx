import { useEffect, useRef } from 'react'

/**
 * Renderiza HTML de modelo de impressão sem deixar o CSS dele vazar para o app.
 *
 * Por que existe (25/08/2026)
 * --------------------------
 * Os modelos de impressão são HTML editável pela clínica e trazem o próprio
 * bloco `<style>`. Com `dangerouslySetInnerHTML` esse `<style>` entra no
 * documento e vale para a PÁGINA INTEIRA — não só para a seção de impressão,
 * mesmo ela estando com `hidden`. O modelo padrão `registro_sessoes` declara
 * `.grid`, `.box`, `table`, `th`, `td`, `h1` e `body`: nomes genéricos que
 * colidem de frente com as classes utilitárias do Tailwind. O efeito visível
 * era `.grid { grid-template-columns: 1fr 1fr }` forçando duas colunas em toda
 * `grid` das telas de Guia, Antecipação e Sessões — inclusive no celular, onde
 * a página passava a rolar na horizontal — além de trocar a fonte do app por
 * Arial e reestilizar as tabelas de verdade dessas três telas.
 *
 * Shadow DOM resolve nos dois sentidos: o CSS do modelo não sai, e o CSS do app
 * não entra — o modelo imprime exatamente como foi escrito, sem herdar nada da
 * pele do produto. O conteúdo de shadow root é impresso normalmente pelo navegador.
 *
 * `body` do modelo vira `:host`: dentro de um shadow root o seletor `body` não
 * casa com nada, e é nele que os modelos colocam fonte e cor do impresso.
 */
export function HtmlIsolado({ html, className }: { html: string; className?: string }) {
  const hospedeiroRef = useRef<HTMLElement>(null)
  const raizRef = useRef<ShadowRoot | null>(null)

  useEffect(() => {
    const hospedeiro = hospedeiroRef.current
    if (!hospedeiro) return

    // attachShadow uma vez só: chamar duas vezes no mesmo elemento lança.
    if (!raizRef.current) {
      raizRef.current = hospedeiro.shadowRoot ?? hospedeiro.attachShadow({ mode: 'open' })
    }

    raizRef.current.innerHTML = escoparBody(html)
  }, [html])

  return <section ref={hospedeiroRef} className={className} />
}

/**
 * Troca o seletor `body` por `:host` dentro dos blocos `<style>` do modelo.
 *
 * Só pega `body` em início de seletor e seguido de `{`, `,` ou espaço — o que
 * cobre a forma que os modelos usam (`body { ... }`) sem tocar em palavras que
 * contenham "body" no meio de um nome de classe.
 */
function escoparBody(html: string): string {
  return html.replace(/<style\b[^>]*>([\s\S]*?)<\/style>/gi, (bloco, css: string) => {
    const ajustado = css.replace(/(^|[{}\s,])body(?=[\s,{])/g, '$1:host')
    return bloco.replace(css, ajustado)
  })
}
