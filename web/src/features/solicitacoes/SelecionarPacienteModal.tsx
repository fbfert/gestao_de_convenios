import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react'
import { Search, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Botao } from '../../components/ui/Botao'
import { CarteirinhaBlocosInput } from '../../components/ui/CarteirinhaBlocosInput'
import { formatCarteirinha, isCarteirinhaCompleta } from '../../lib/carteirinha'
import { useDebouncedValue } from '../../lib/useDebouncedValue'
import {
  usePacientesBusca,
  usePacientesRecentes,
  type PacienteRef,
} from '../../lib/queries/useReferenceData'
import { getHttpErrorMessage, useCriarPacienteRapido } from './useSolicitacoes'

type SelecionarPacienteModalProps = {
  open: boolean
  onClose: () => void
  onSelecionar: (paciente: PacienteRef) => void
  convenioId: string
  carteirinhaBlocos?: number[] | null
}

function fieldClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

export function SelecionarPacienteModal({
  open,
  onClose,
  onSelecionar,
  convenioId,
  carteirinhaBlocos,
}: SelecionarPacienteModalProps) {
  const [termo, setTermo] = useState('')
  const [page, setPage] = useState(1)
  const [acumulados, setAcumulados] = useState<PacienteRef[]>([])
  const [novoNome, setNovoNome] = useState('')
  const [novoBlocos, setNovoBlocos] = useState<string[]>([])
  const [novoCarteirinha, setNovoCarteirinha] = useState('')
  const [novoErro, setNovoErro] = useState<string | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  const buscaAtiva = termo.trim().length >= 2
  const debounced = useDebouncedValue(termo, 500)

  const recentesQuery = usePacientesRecentes({ convenioId, enabled: open && !buscaAtiva })
  const buscaQuery = usePacientesBusca({ busca: debounced, page, convenioId, enabled: open })
  const criarPaciente = useCriarPacienteRapido()

  useEffect(() => {
    if (!open) return
    setTermo('')
    setPage(1)
    setAcumulados([])
    setNovoNome('')
    setNovoBlocos([])
    setNovoCarteirinha('')
    setNovoErro(null)
    // Foco no campo de busca ao abrir — é a primeira ação esperada.
    const timer = setTimeout(() => inputRef.current?.focus(), 0)
    return () => clearTimeout(timer)
  }, [open])

  useEffect(() => {
    setPage(1)
    setAcumulados([])
  }, [debounced])

  useEffect(() => {
    if (!buscaQuery.data) return
    setAcumulados((atual) => (page === 1 ? buscaQuery.data.itens : [...atual, ...buscaQuery.data.itens]))
  }, [buscaQuery.data, page])

  const listaRecentes = recentesQuery.data ?? []
  const listaBusca = acumulados
  const carregando = buscaAtiva ? buscaQuery.isLoading : recentesQuery.isLoading
  const semResultado = buscaAtiva && buscaQuery.isSuccess && listaBusca.length === 0
  const temMais = Boolean(
    buscaQuery.data?.meta && buscaQuery.data.meta.current_page < buscaQuery.data.meta.last_page,
  )

  const carteirinhaPreenchida = carteirinhaBlocos
    ? isCarteirinhaCompleta(novoBlocos, carteirinhaBlocos)
    : novoCarteirinha.trim() !== ''

  const selecionar = (paciente: PacienteRef) => {
    onSelecionar(paciente)
    onClose()
  }

  const handleCriarPaciente = async () => {
    setNovoErro(null)

    try {
      const paciente = await criarPaciente.mutateAsync({
        nome: novoNome.trim(),
        convenio_id: Number(convenioId),
        carteirinha: novoCarteirinha.trim(),
      })
      selecionar(paciente)
    } catch (error) {
      setNovoErro(getHttpErrorMessage(error, 'Não foi possível cadastrar o paciente.'))
    }
  }

  return (
    <Dialog open={open} onClose={onClose} className="relative z-(--z-dialogo)">
      <DialogBackdrop className="fixed inset-0 bg-slate-950/75 backdrop-blur-sm" />
      <div className="fixed inset-0 overflow-y-auto p-4 sm:p-6">
        <div className="flex min-h-full items-center justify-center">
          <DialogPanel
            className="w-full max-w-xl rounded-janela border border-white/10 bg-slate-950 p-6 text-white shadow-e3 shadow-black/60"
            data-testid="selecionar-paciente-modal"
          >
            <div className="flex items-start justify-between gap-4">
              <DialogTitle className="text-titulo font-semibold">Selecionar paciente</DialogTitle>
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
                placeholder="Buscar por nome, CPF ou carteirinha"
                className={`${fieldClasses()} pl-11`}
                data-testid="selecionar-paciente-busca"
              />
            </div>

            <div className="mt-4 max-h-96 space-y-1 overflow-y-auto">
              {!buscaAtiva ? (
                <p className="px-1 pb-2 text-meta uppercase tracking-[0.2em] text-slate-400">
                  Usados recentemente
                </p>
              ) : null}

              {carregando ? (
                <p className="rounded-2xl px-4 py-3 text-corpo text-slate-300">Carregando...</p>
              ) : null}

              {!carregando && !buscaAtiva && listaRecentes.length === 0 ? (
                <p className="rounded-2xl px-4 py-3 text-corpo text-slate-400">
                  Digite ao menos 2 caracteres para buscar.
                </p>
              ) : null}

              {(buscaAtiva ? listaBusca : listaRecentes).map((item) => (
                <button
                  key={item.id}
                  type="button"
                  onClick={() => selecionar(item)}
                  className="w-full rounded-2xl px-4 py-3 text-left transition hover:bg-cyan-400/10"
                  data-testid="selecionar-paciente-item"
                >
                  <p className="text-corpo font-medium text-white">{item.nome}</p>
                  <p className="text-meta text-slate-400">
                    {formatCarteirinha(item.carteirinha, item.convenio?.carteirinha_blocos ?? undefined)}
                  </p>
                </button>
              ))}

              {buscaAtiva && temMais ? (
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

            {semResultado ? (
              <div className="mt-4 space-y-3 rounded-superficie border border-linha bg-fundo p-4 shadow-e1">
                <p className="text-corpo font-medium text-slate-200">
                  Nenhum paciente encontrado. Cadastrar novo:
                </p>

                <label className="block space-y-2">
                  <span className="text-meta text-slate-400">Nome</span>
                  <input
                    value={novoNome || termo}
                    onChange={(event) => setNovoNome(event.target.value)}
                    className={fieldClasses()}
                    data-testid="selecionar-paciente-novo-nome"
                  />
                </label>

                <div className="space-y-2">
                  <span className="text-meta text-slate-400">Carteirinha</span>
                  {carteirinhaBlocos ? (
                    <CarteirinhaBlocosInput
                      blocos={carteirinhaBlocos}
                      blocks={novoBlocos}
                      onChange={(blocks, carteirinha) => {
                        setNovoBlocos(blocks)
                        setNovoCarteirinha(carteirinha)
                      }}
                      testIdPrefix="selecionar-paciente-novo-carteirinha-blocos"
                    />
                  ) : (
                    <input
                      value={novoCarteirinha}
                      onChange={(event) => setNovoCarteirinha(event.target.value)}
                      className={fieldClasses()}
                      data-testid="selecionar-paciente-novo-carteirinha"
                    />
                  )}
                </div>

                {novoErro ? (
                  <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
                    {novoErro}
                  </p>
                ) : null}

                <Botao
                  type="button"
                  variante="primario"
                  className="w-full"
                  disabled={criarPaciente.isPending || (novoNome || termo).trim() === '' || !carteirinhaPreenchida}
                  onClick={() => void handleCriarPaciente()}
                  data-testid="selecionar-paciente-novo-salvar"
                >
                  {criarPaciente.isPending ? 'Salvando...' : 'Cadastrar e selecionar'}
                </Botao>
              </div>
            ) : null}
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  )
}
