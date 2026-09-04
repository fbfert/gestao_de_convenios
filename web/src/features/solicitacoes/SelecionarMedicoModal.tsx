import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react'
import { Search, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Botao } from '../../components/ui/Botao'
import { useDebouncedValue } from '../../lib/useDebouncedValue'
import { useMedicosBusca, useMedicosRecentes, type MedicoRef } from '../../lib/queries/useReferenceData'
import { getHttpErrorMessage, useCriarMedicoRapido } from './useSolicitacoes'

type SelecionarMedicoModalProps = {
  open: boolean
  onClose: () => void
  onSelecionar: (medico: MedicoRef) => void
}

function fieldClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

export function SelecionarMedicoModal({ open, onClose, onSelecionar }: SelecionarMedicoModalProps) {
  const [termo, setTermo] = useState('')
  const [page, setPage] = useState(1)
  const [acumulados, setAcumulados] = useState<MedicoRef[]>([])
  const [novoNome, setNovoNome] = useState('')
  const [novoCrm, setNovoCrm] = useState('')
  const [novoCrmUf, setNovoCrmUf] = useState('')
  const [novoEspecialidade, setNovoEspecialidade] = useState('')
  const [novoErro, setNovoErro] = useState<string | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  const buscaAtiva = termo.trim().length >= 2
  const debounced = useDebouncedValue(termo, 500)

  const recentesQuery = useMedicosRecentes({ enabled: open && !buscaAtiva })
  const buscaQuery = useMedicosBusca({ busca: debounced, page, enabled: open })
  const criarMedico = useCriarMedicoRapido()

  useEffect(() => {
    if (!open) return
    setTermo('')
    setPage(1)
    setAcumulados([])
    setNovoNome('')
    setNovoCrm('')
    setNovoCrmUf('')
    setNovoEspecialidade('')
    setNovoErro(null)
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

  const selecionar = (medico: MedicoRef) => {
    onSelecionar(medico)
    onClose()
  }

  const handleCriarMedico = async () => {
    setNovoErro(null)

    try {
      const medico = await criarMedico.mutateAsync({
        nome: novoNome.trim(),
        crm: novoCrm.trim() || undefined,
        crm_uf: novoCrmUf.trim() || undefined,
        especialidade_medica: novoEspecialidade.trim() || undefined,
      })
      selecionar(medico)
    } catch (error) {
      setNovoErro(getHttpErrorMessage(error, 'Não foi possível cadastrar o médico.'))
    }
  }

  return (
    <Dialog open={open} onClose={onClose} className="relative z-(--z-dialogo)">
      <DialogBackdrop className="fixed inset-0 bg-slate-950/75 backdrop-blur-sm" />
      <div className="fixed inset-0 overflow-y-auto p-4 sm:p-6">
        <div className="flex min-h-full items-center justify-center">
          <DialogPanel
            className="w-full max-w-xl rounded-janela border border-white/10 bg-slate-950 p-6 text-white shadow-e3 shadow-black/60"
            data-testid="selecionar-medico-modal"
          >
            <div className="flex items-start justify-between gap-4">
              <DialogTitle className="text-titulo font-semibold">Selecionar médico</DialogTitle>
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
                placeholder="Buscar por nome, CRM ou especialidade"
                className={`${fieldClasses()} pl-11`}
                data-testid="selecionar-medico-busca"
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
                  data-testid="selecionar-medico-item"
                >
                  <p className="text-corpo font-medium text-white">{item.nome}</p>
                  <p className="text-meta text-slate-400">
                    {item.especialidade_medica}
                    {item.crm ? ` · CRM ${item.crm}${item.crm_uf ? `/${item.crm_uf}` : ''}` : ''}
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
                  Nenhum médico encontrado. Cadastrar novo:
                </p>

                <label className="block space-y-2">
                  <span className="text-meta text-slate-400">Nome</span>
                  <input
                    value={novoNome || termo}
                    onChange={(event) => setNovoNome(event.target.value)}
                    className={fieldClasses()}
                    data-testid="selecionar-medico-novo-nome"
                  />
                </label>

                <div className="grid grid-cols-3 gap-3">
                  <label className="col-span-2 block space-y-2">
                    <span className="text-meta text-slate-400">CRM</span>
                    <input
                      value={novoCrm}
                      onChange={(event) => setNovoCrm(event.target.value)}
                      className={fieldClasses()}
                      data-testid="selecionar-medico-novo-crm"
                    />
                  </label>
                  <label className="block space-y-2">
                    <span className="text-meta text-slate-400">UF</span>
                    <input
                      value={novoCrmUf}
                      onChange={(event) => setNovoCrmUf(event.target.value.toUpperCase())}
                      maxLength={2}
                      className={fieldClasses()}
                      data-testid="selecionar-medico-novo-crm-uf"
                    />
                  </label>
                </div>

                <label className="block space-y-2">
                  <span className="text-meta text-slate-400">Especialidade</span>
                  <input
                    value={novoEspecialidade}
                    onChange={(event) => setNovoEspecialidade(event.target.value)}
                    className={fieldClasses()}
                    data-testid="selecionar-medico-novo-especialidade"
                  />
                </label>

                {novoErro ? (
                  <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
                    {novoErro}
                  </p>
                ) : null}

                <Botao
                  type="button"
                  variante="primario"
                  className="w-full"
                  disabled={criarMedico.isPending || (novoNome || termo).trim() === ''}
                  onClick={() => void handleCriarMedico()}
                  data-testid="selecionar-medico-novo-salvar"
                >
                  {criarMedico.isPending ? 'Salvando...' : 'Cadastrar e selecionar'}
                </Botao>
              </div>
            ) : null}
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  )
}
