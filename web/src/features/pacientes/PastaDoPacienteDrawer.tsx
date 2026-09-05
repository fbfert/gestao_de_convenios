import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react'
import { useRef, useState, type ChangeEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { Botao } from '../../components/ui/Botao'
import { ConfirmarExclusao } from '../../components/ui/ConfirmarExclusao'
import { DOCUMENTO_LABELS, TODOS_DOCUMENTOS, type DocumentoTipo } from '../../lib/documentoTipos'
import { formatCarteirinha } from '../../lib/carteirinha'
import {
  abrirPacienteArquivo,
  getHttpErrorMessage,
  usePacienteArquivos,
  useRemoverPacienteArquivo,
  useUploadPacienteArquivo,
  type PacienteArquivo,
} from './usePacienteArquivos'
import type { Paciente } from './types'

const ACCEPT = 'application/pdf,image/jpeg,image/png,image/gif'

function GrupoDocumento({
  paciente,
  tipo,
  arquivos,
  onError,
}: {
  paciente: Paciente
  tipo: DocumentoTipo
  arquivos: PacienteArquivo[]
  onError: (mensagem: string | null) => void
}) {
  const inputRef = useRef<HTMLInputElement>(null)
  const upload = useUploadPacienteArquivo()
  const remover = useRemoverPacienteArquivo()
  const [aExcluir, setAExcluir] = useState<PacienteArquivo | null>(null)
  const ocupado = upload.isPending || remover.isPending

  const handleFile = async (event: ChangeEvent<HTMLInputElement>) => {
    const arquivo = event.target.files?.[0]
    event.target.value = ''

    if (!arquivo) {
      return
    }

    onError(null)

    try {
      await upload.mutateAsync({ pacienteId: paciente.id, tipo, arquivo })
    } catch (error) {
      onError(getHttpErrorMessage(error, 'Não foi possível anexar o arquivo.'))
    }
  }

  const handleRemove = async () => {
    const arquivo = aExcluir

    if (!arquivo) {
      return
    }

    onError(null)

    try {
      await remover.mutateAsync({ pacienteId: paciente.id, arquivoId: arquivo.id })
      setAExcluir(null)
    } catch (error) {
      onError(getHttpErrorMessage(error, 'Não foi possível excluir o arquivo.'))
      setAExcluir(null)
    }
  }

  return (
    <div className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1" data-testid={`pasta-grupo-${tipo}`}>
      <div className="flex items-center justify-between gap-3">
        <p className="text-corpo font-semibold text-white">{DOCUMENTO_LABELS[tipo]}</p>
        <button
          type="button"
          onClick={() => inputRef.current?.click()}
          disabled={ocupado}
          className="rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1.5 text-meta font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:opacity-50"
          data-testid={`pasta-upload-${tipo}`}
        >
          {upload.isPending ? 'Enviando...' : 'Anexar arquivo'}
        </button>
        <input
          ref={inputRef}
          type="file"
          accept={ACCEPT}
          onChange={(event) => void handleFile(event)}
          className="hidden"
        />
      </div>

      {arquivos.length === 0 ? (
        <p className="mt-2 text-meta text-slate-400">Nenhum arquivo nesta pasta.</p>
      ) : (
        <ul className="mt-3 space-y-2">
          {arquivos.map((arquivo) => {
            const travado = arquivo.vinculos.some((vinculo) => vinculo.travado)
            const semVinculo = arquivo.vinculos.length === 0

            return (
              <li
                key={arquivo.id}
                className="rounded-xl border border-white/10 bg-slate-950/40 px-3 py-2 text-corpo text-slate-200"
              >
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span className="truncate">{arquivo.nome_original}</span>
                  <span className="flex gap-2">
                    <button
                      type="button"
                      onClick={() => void abrirPacienteArquivo(paciente.id, arquivo.id, arquivo.nome_original)}
                      className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-meta font-semibold text-white transition hover:bg-white/10"
                    >
                      Abrir
                    </button>
                    {semVinculo ? (
                      <button
                        type="button"
                        onClick={() => setAExcluir(arquivo)}
                        disabled={ocupado}
                        className="rounded-full border border-rose-400/30 bg-rose-400/10 px-3 py-1 text-meta font-semibold text-rose-100 transition hover:bg-rose-400/20 disabled:opacity-50"
                      >
                        Remover
                      </button>
                    ) : (
                      <span
                        className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-meta font-semibold text-slate-400"
                        title={
                          travado
                            ? 'Vinculado a uma solicitação com Guia gerada — não pode ser excluído.'
                            : 'Vinculado a uma ou mais solicitações — remova o vínculo lá antes de excluir aqui.'
                        }
                      >
                        Vinculado
                      </span>
                    )}
                  </span>
                </div>
                {arquivo.vinculos.length > 0 ? (
                  <p className="mt-1 text-meta text-slate-400">
                    {arquivo.vinculos.map((vinculo) => `Solicitação #${vinculo.solicitacao_id}`).join(' · ')}
                  </p>
                ) : null}
              </li>
            )
          })}
        </ul>
      )}

      {aExcluir ? (
        <ConfirmarExclusao
          titulo="Excluir arquivo da pasta"
          descricao="O arquivo será apagado do servidor e não poderá ser recuperado."
          alvo={aExcluir.nome_original}
          confirmando={remover.isPending}
          onConfirmar={() => void handleRemove()}
          onCancelar={() => setAExcluir(null)}
        />
      ) : null}
    </div>
  )
}

export function PastaDoPacienteDrawer({
  paciente,
  onClose,
}: {
  paciente: Paciente | null
  onClose: () => void
}) {
  const navigate = useNavigate()
  const [erro, setErro] = useState<string | null>(null)
  const arquivosQuery = usePacienteArquivos(paciente?.id ?? null)
  const arquivos = arquivosQuery.data ?? []

  const irParaSolicitacao = (destino: 'ler-pedido-medico' | 'nova') => {
    if (!paciente) {
      return
    }

    const params = new URLSearchParams({
      paciente_id: String(paciente.id),
      convenio_id: String(paciente.convenio_id),
    })
    onClose()
    navigate(`/solicitacoes/${destino}?${params.toString()}`)
  }

  return (
    <Dialog open={paciente !== null} onClose={onClose} className="relative z-(--z-dialogo)">
      <DialogBackdrop className="fixed inset-0 bg-slate-950/75 backdrop-blur-sm" />
      <div className="fixed inset-0 overflow-hidden">
        <div className="flex h-full justify-end">
          <DialogPanel
            className="flex h-full w-full max-w-xl flex-col gap-6 border-l border-white/10 bg-slate-950 p-6 text-white shadow-e3 shadow-black/60"
            data-testid="pasta-do-paciente-drawer"
          >
            <div className="flex items-start justify-between gap-4">
              <div>
                <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Pasta do paciente</p>
                <DialogTitle className="mt-1 text-titulo font-semibold">{paciente?.nome}</DialogTitle>
                {paciente ? (
                  <p className="mt-1 text-meta text-slate-400">
                    {formatCarteirinha(paciente.carteirinha, paciente.convenio?.carteirinha_blocos ?? undefined)}
                    {paciente.convenio?.nome ? ` · ${paciente.convenio.nome}` : ''}
                  </p>
                ) : null}
              </div>
              <button
                type="button"
                onClick={onClose}
                className="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-corpo font-semibold text-white transition hover:bg-white/10"
              >
                Fechar
              </button>
            </div>

            <div className="flex flex-wrap gap-2">
              <Botao
                variante="primario"
                onClick={() => irParaSolicitacao('ler-pedido-medico')}
                data-testid="pasta-gerar-solicitacao-ler"
              >
                Gerar Solicitação · Ler novo pedido
              </Botao>
              <Botao
                variante="secundario"
                onClick={() => irParaSolicitacao('nova')}
                data-testid="pasta-gerar-solicitacao-anexar"
              >
                Gerar Solicitação · Anexar documento existente
              </Botao>
            </div>

            {erro ? (
              <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
                {erro}
              </p>
            ) : null}

            <div className="flex-1 space-y-4 overflow-y-auto pr-1">
              {arquivosQuery.isLoading ? (
                <p className="text-corpo text-slate-300">Carregando documentos...</p>
              ) : arquivosQuery.isError ? (
                <p className="text-corpo text-rose-200">Não foi possível carregar os documentos.</p>
              ) : (
                paciente &&
                TODOS_DOCUMENTOS.map((tipo) => (
                  <GrupoDocumento
                    key={tipo}
                    paciente={paciente}
                    tipo={tipo}
                    arquivos={arquivos.filter((arquivo) => arquivo.tipo === tipo)}
                    onError={setErro}
                  />
                ))
              )}
            </div>
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  )
}
