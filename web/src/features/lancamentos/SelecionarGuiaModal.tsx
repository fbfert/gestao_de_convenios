import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react'
import { useFechamentoExplicito } from '../../lib/useFechamentoExplicito'
import { Search, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { useDebouncedValue } from '../../lib/useDebouncedValue'
import type { Guia } from '../guias/types'
import { useGuiasBusca } from './useLancamentos'

type SelecionarGuiaModalProps = {
  open: boolean
  onClose: () => void
  onSelecionar: (guia: Guia) => void
  /**
   * Termo já digitado ao abrir.
   *
   * Serve ao número de guia que veio da leitura da folha e não resolveu para
   * uma guia só: o operador escolhe em um clique, em vez de redigitar o que a
   * IA acabou de ler.
   */
  termoInicial?: string
}

function fieldClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

/**
 * Busca de guia por ID, número, nome do paciente ou do profissional
 * executante — substitui o `<select>` que só listava a primeira página de
 * guias disponíveis. Sem cadastro rápido: guia não se cria a partir daqui.
 */
export function SelecionarGuiaModal({
  open,
  onClose,
  onSelecionar,
  termoInicial = '',
}: SelecionarGuiaModalProps) {
  const [termo, setTermo] = useState(termoInicial)
  const [page, setPage] = useState(1)
  const [acumuladas, setAcumuladas] = useState<Guia[]>([])
  const inputRef = useRef<HTMLInputElement>(null)

  const debounced = useDebouncedValue(termo, 500)

  const buscaQuery = useGuiasBusca({ busca: debounced, page, enabled: open })

  useEffect(() => {
    if (!open) return
    setTermo(termoInicial)
    setPage(1)
    setAcumuladas([])
    const timer = setTimeout(() => inputRef.current?.focus(), 0)
    return () => clearTimeout(timer)
  }, [open, termoInicial])

  useEffect(() => {
    setPage(1)
    setAcumuladas([])
  }, [debounced])

  useEffect(() => {
    if (!buscaQuery.data) return
    setAcumuladas((atual) => (page === 1 ? buscaQuery.data.itens : [...atual, ...buscaQuery.data.itens]))
  }, [buscaQuery.data, page])

  const lista = acumuladas
  const carregando = buscaQuery.isLoading
  const semResultado = buscaQuery.isSuccess && lista.length === 0
  const temMais = Boolean(
    buscaQuery.data?.meta && buscaQuery.data.meta.current_page < buscaQuery.data.meta.last_page,
  )

  const selecionar = (guia: Guia) => {
    onSelecionar(guia)
    onClose()
  }

  return (
    <Dialog {...useFechamentoExplicito(open, onClose)} className="relative z-(--z-dialogo)">
      <DialogBackdrop className="fixed inset-0 bg-slate-950/75 backdrop-blur-sm" />
      <div className="fixed inset-0 overflow-y-auto p-4 sm:p-6">
        <div className="flex min-h-full items-center justify-center">
          <DialogPanel
            className="w-full max-w-xl rounded-janela border border-white/10 bg-slate-950 p-6 text-white shadow-e3 shadow-black/60"
            data-testid="selecionar-guia-modal"
          >
            <div className="flex items-start justify-between gap-4">
              <DialogTitle className="text-titulo font-semibold">Selecionar guia</DialogTitle>
              <button
                type="button"
                onClick={onClose}
                className="rounded-full border border-white/10 bg-white/5 p-2 text-white transition hover:bg-white/10"
                aria-label="Fechar"
              >
                <X className="size-4" aria-hidden="true" />
              </button>
            </div>

            <div className="relative mt-4">
              <Search
                className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-slate-400"
                aria-hidden="true"
              />
              <input
                ref={inputRef}
                value={termo}
                onChange={(event) => setTermo(event.target.value)}
                placeholder="Buscar por ID, número da guia, paciente ou profissional"
                className={`${fieldClasses()} pl-11`}
                data-testid="selecionar-guia-busca"
              />
            </div>

            <div className="mt-4 max-h-96 space-y-1 overflow-y-auto">
              {!carregando && termo.trim() === '' ? (
                <p className="px-1 pb-2 text-meta uppercase tracking-[0.2em] text-slate-400">
                  Guias com sessão disponível
                </p>
              ) : null}

              {carregando ? (
                <p className="rounded-2xl px-4 py-3 text-corpo text-slate-300">Carregando...</p>
              ) : null}

              {!carregando && semResultado ? (
                <p className="rounded-2xl px-4 py-3 text-corpo text-slate-400">
                  Nenhuma guia com sessão disponível encontrada.
                </p>
              ) : null}

              {lista.map((guia) => (
                <button
                  key={guia.id}
                  type="button"
                  onClick={() => selecionar(guia)}
                  className="w-full rounded-2xl px-4 py-3 text-left transition hover:bg-cyan-400/10"
                  data-testid="selecionar-guia-item"
                >
                  <p className="text-corpo font-medium text-white">
                    #{guia.id} · {guia.numero_guia ?? 'sem nº'} · {guia.paciente?.nome ?? `Paciente ${guia.paciente_id}`}
                  </p>
                  <p className="text-meta text-slate-400">
                    {guia.especialidade?.nome ?? 'Especialidade não definida'} · {guia.sessoes_disponiveis} sessão(ões)
                    disponível(is)
                  </p>
                </button>
              ))}

              {temMais ? (
                <button
                  type="button"
                  onClick={() => setPage((atual) => atual + 1)}
                  disabled={buscaQuery.isFetching}
                  className="w-full rounded-2xl px-4 py-2 text-center text-corpo font-medium text-cyan-200 transition hover:bg-cyan-400/10 disabled:opacity-60"
                >
                  {buscaQuery.isFetching ? 'Carregando...' : 'Carregar mais'}
                </button>
              ) : null}
            </div>
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  )
}
