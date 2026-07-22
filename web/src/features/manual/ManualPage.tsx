import { useEffect, useRef, useState } from 'react'
import { useAuthStore } from '../../stores/authStore'
import { useManual, useUpdateManual, type ManualTipo } from './useManual'

const abas: { tipo: ManualTipo; label: string }[] = [
  { tipo: 'manual', label: 'Manual' },
  { tipo: 'mapa-mental', label: 'Mapa Mental' },
]

export function ManualPage() {
  const user = useAuthStore((state) => state.user)
  const podeEditar = user?.role === 'admin'

  const [aba, setAba] = useState<ManualTipo>('manual')
  const { data: manual, isLoading, isError } = useManual(aba)
  const updateManual = useUpdateManual(aba)

  const [editando, setEditando] = useState(false)
  const [rascunho, setRascunho] = useState('')
  const iframeRef = useRef<HTMLIFrameElement>(null)
  const [altura, setAltura] = useState(600)

  const trocarAba = (tipo: ManualTipo) => {
    setAba(tipo)
    setEditando(false)
    setAltura(600)
    updateManual.reset()
  }

  const iniciarEdicao = () => {
    setRascunho(manual?.conteudo_html ?? '')
    setEditando(true)
  }

  const cancelarEdicao = () => {
    setEditando(false)
    updateManual.reset()
  }

  const salvar = () => {
    updateManual.mutate(rascunho, {
      onSuccess: () => setEditando(false),
    })
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
    if (!editando) {
      prepararIframe()
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [manual?.conteudo_html, editando])

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
              'rounded-full border px-4 py-2 text-sm font-medium transition',
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
          <p className="text-xs uppercase tracking-[.3em] text-cyan-300">Manual</p>
          <h2 className="mt-2 text-3xl font-semibold">{tituloAba}</h2>
          {manual?.atualizado_em && (
            <p className="mt-2 text-sm text-slate-400">
              Última atualização em {new Date(manual.atualizado_em).toLocaleString('pt-BR')}
              {manual.atualizado_por ? ` por ${manual.atualizado_por}` : ''}
            </p>
          )}
        </div>

        {podeEditar && !editando && (
          <button
            type="button"
            onClick={iniciarEdicao}
            className="rounded-full border border-cyan-300/40 bg-cyan-400/15 px-5 py-2 text-sm font-medium text-cyan-50 transition hover:bg-cyan-400/25"
            data-testid="manual-editar"
          >
            Editar {aba === 'mapa-mental' ? 'mapa mental' : 'manual'}
          </button>
        )}

        {podeEditar && editando && (
          <div className="flex gap-2">
            <button
              type="button"
              onClick={cancelarEdicao}
              className="rounded-full border border-white/15 bg-white/5 px-5 py-2 text-sm font-medium text-white transition hover:bg-white/10"
              disabled={updateManual.isPending}
            >
              Cancelar
            </button>
            <button
              type="button"
              onClick={salvar}
              className="rounded-full border border-emerald-300/40 bg-emerald-400/15 px-5 py-2 text-sm font-medium text-emerald-50 transition hover:bg-emerald-400/25 disabled:opacity-60"
              disabled={updateManual.isPending}
              data-testid="manual-salvar"
            >
              {updateManual.isPending ? 'Salvando...' : 'Salvar alterações'}
            </button>
          </div>
        )}
      </div>

      {updateManual.isError && (
        <p className="rounded-2xl border border-rose-400/30 bg-rose-500/10 p-4 text-sm text-rose-100">
          Não foi possível salvar. Tente novamente.
        </p>
      )}

      {isLoading && <p className="text-slate-300">Carregando...</p>}
      {isError && <p className="text-rose-200">Não foi possível carregar o conteúdo.</p>}

      {!isLoading && !isError && editando && (
        <div className="space-y-2">
          <p className="text-sm text-slate-300">
            Edite o HTML diretamente. As alterações ficam visíveis para todos os usuários após salvar.
          </p>
          <textarea
            value={rascunho}
            onChange={(event) => setRascunho(event.target.value)}
            spellCheck={false}
            className="h-[65vh] w-full rounded-2xl border border-white/10 bg-slate-950/80 p-4 font-mono text-xs text-slate-100 outline-none focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20"
            data-testid="manual-textarea"
          />
        </div>
      )}

      {!isLoading && !isError && !editando && manual && (
        <iframe
          key={aba}
          ref={iframeRef}
          title={tituloAba}
          srcDoc={manual.conteudo_html}
          sandbox="allow-same-origin"
          onLoad={prepararIframe}
          style={{ height: altura }}
          className="w-full rounded-2xl border border-white/10 bg-white"
          data-testid="manual-iframe"
        />
      )}
    </div>
  )
}
