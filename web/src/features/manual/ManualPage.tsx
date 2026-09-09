import { useEffect, useRef, useState } from 'react'
import { useManual, type ManualTipo } from './useManual'

const abas: { tipo: ManualTipo; label: string }[] = [
  { tipo: 'manual', label: 'Manual' },
  { tipo: 'mapa-mental', label: 'Mapa Mental' },
]

/**
 * Manual e mapa mental, somente leitura.
 *
 * A edição saiu: o conteúdo virou do PRODUTO, versionado no repositório e igual
 * para todas as clínicas. Enquanto era editável por tenant, cada uma tinha um
 * manual diferente e não havia como anunciar uma atualização — que é o que a
 * tela de Novidades passa a fazer.
 */
export function ManualPage() {
  const [aba, setAba] = useState<ManualTipo>('manual')
  const { data: manual, isLoading, isError } = useManual(aba)

  const iframeRef = useRef<HTMLIFrameElement>(null)
  const [altura, setAltura] = useState(600)

  const trocarAba = (tipo: ManualTipo) => {
    setAba(tipo)
    setAltura(600)
  }

  const prepararIframe = () => {
    const win = iframeRef.current?.contentWindow
    const doc = win?.document
    if (!doc?.documentElement) {
      return
    }

    // Medir documentElement.scrollHeight aqui seria não confiável: quando o
    // conteúdo é mais baixo que a altura atual do iframe (ex.: ao trocar para
    // uma aba com conteúdo menor), ele "cai" para a altura do viewport do
    // iframe em vez do conteúdo real, criando um laço que nunca encolhe.
    // body.scrollHeight reflete o conteúdo real, independente da altura atual.
    setAltura(doc.body.scrollHeight + 32)

    // Links "#id" numa srcDoc iframe resolvem contra a URL da página pai (não
    // "about:srcdoc"), então o navegador tenta uma navegação de página real em
    // vez de rolar dentro do iframe — e como o sandbox não libera scripts, essa
    // navegação some em branco. Interceptamos o clique e rolamos manualmente.
    // Como o iframe tem altura igual à do conteúdo (sem scroll próprio), quem
    // precisa rolar é a página externa, não a janela do iframe.
    doc.querySelectorAll('a[href^="#"]').forEach((link) => {
      link.addEventListener('click', (event) => {
        event.preventDefault()
        const iframeEl = iframeRef.current
        if (!iframeEl) {
          return
        }
        const id = link.getAttribute('href')?.slice(1) ?? ''
        const alvo = id ? doc.getElementById(id) : null
        const destinoY = alvo
          ? window.scrollY + iframeEl.getBoundingClientRect().top + alvo.getBoundingClientRect().top - 24
          : window.scrollY + iframeEl.getBoundingClientRect().top
        window.scrollTo({ top: destinoY, behavior: 'smooth' })
      })
    })
  }

  useEffect(() => {
    prepararIframe()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [manual?.conteudo_html])

  const tituloAba = aba === 'mapa-mental' ? 'Mapa Mental do Sistema' : 'Manual do Sistema'

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap gap-2">
        {abas.map((item) => (
          <button
            key={item.tipo}
            type="button"
            onClick={() => trocarAba(item.tipo)}
            className={[
              'rounded-full border px-4 py-2 text-corpo font-medium transition',
              aba === item.tipo
                ? 'border-cyan-300/40 bg-cyan-400/15 text-cyan-50'
                : 'border-white/10 bg-white/5 text-slate-200 hover:bg-white/10',
            ].join(' ')}
            data-testid={`manual-aba-${item.tipo}`}
          >
            {item.label}
          </button>
        ))}
      </div>

      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-meta uppercase tracking-[.3em] text-cyan-300">Manual</p>
          <h2 className="mt-2 text-display font-semibold">{tituloAba}</h2>
          {manual?.atualizado_em && (
            <p className="mt-2 text-corpo text-slate-400">
              Última atualização em {new Date(manual.atualizado_em).toLocaleString('pt-BR')}
            </p>
          )}
        </div>
      </div>

      {isLoading && <p className="text-slate-300">Carregando...</p>}
      {isError && <p className="text-rose-200">Não foi possível carregar o conteúdo.</p>}

      {!isLoading && !isError && manual && (
        <iframe
          key={aba}
          ref={iframeRef}
          title={tituloAba}
          srcDoc={manual.conteudo_html}
          sandbox="allow-same-origin"
          onLoad={prepararIframe}
          style={{ height: altura }}
          /* Branco literal. `bg-white` resolve para --color-white, que no tema
             claro vira tinta escura — o papel do manual ficaria preto. */
          className="w-full rounded-2xl border border-white/10 bg-papel"
          data-testid="manual-iframe"
        />
      )}
    </div>
  )
}
