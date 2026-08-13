import { useRef, useState, type ChangeEvent } from 'react'
import { ConfirmarExclusao } from '../../components/ui/ConfirmarExclusao'
import {
  abrirDocumento,
  getHttpErrorMessage,
  useAnexarDocumento,
  useRemoverDocumento,
} from './useSolicitacoes'
import {
  DOCUMENTOS_DA_SOLICITACAO,
  DOCUMENTOS_POR_ITEM,
  DOCUMENTO_LABELS,
  type Solicitacao,
  type SolicitacaoDocumento,
  type SolicitacaoDocumentoTipo,
} from './types'

const ACCEPT = 'application/pdf,image/jpeg,image/png,image/gif'

type SlotProps = {
  solicitacaoId: number
  tipo: SolicitacaoDocumentoTipo
  obrigatorio?: boolean
  itemId?: number | null
  documentos: SolicitacaoDocumento[]
  /** Guia já gerada: o anexo vira evidência do envio e deixa de ser removível. */
  travado?: boolean
  onError: (mensagem: string | null) => void
}

function DocumentoSlot({
  solicitacaoId,
  tipo,
  obrigatorio = false,
  itemId = null,
  documentos,
  travado = false,
  onError,
}: SlotProps) {
  const inputRef = useRef<HTMLInputElement>(null)
  const anexar = useAnexarDocumento()
  const remover = useRemoverDocumento()
  const [aExcluir, setAExcluir] = useState<SolicitacaoDocumento | null>(null)
  const doTipo = documentos.filter((documento) => documento.tipo === tipo)
  const ocupado = anexar.isPending || remover.isPending

  const handleFile = async (event: ChangeEvent<HTMLInputElement>) => {
    const arquivo = event.target.files?.[0]
    // Limpa já: sem isso, escolher o mesmo arquivo de novo não dispara change.
    event.target.value = ''

    if (!arquivo) {
      return
    }

    onError(null)

    try {
      await anexar.mutateAsync({ solicitacaoId, tipo, arquivo, solicitacaoItemId: itemId })
    } catch (error) {
      onError(getHttpErrorMessage(error, 'Não foi possível anexar o arquivo.'))
    }
  }

  /**
   * Confirmação em duas etapas: o diálogo, e depois a palavra digitada.
   *
   * Anexo apagado não volta — é documento do paciente, e em parte dos casos a
   * evidência do que foi enviado à operadora. O `window.confirm` que havia
   * aqui era um clique reflexo.
   */
  const handleRemove = async () => {
    const documento = aExcluir

    if (!documento) {
      return
    }

    onError(null)

    try {
      await remover.mutateAsync({ solicitacaoId, documentoId: documento.id })
      setAExcluir(null)
    } catch (error) {
      onError(getHttpErrorMessage(error, 'Não foi possível remover o anexo.'))
      setAExcluir(null)
    }
  }

  return (
    <div
      className="rounded-2xl border border-white/10 bg-white/5 p-4"
      data-testid={`anexo-slot-${tipo}${itemId ? `-item-${itemId}` : ''}`}
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm font-semibold text-white">
          {DOCUMENTO_LABELS[tipo]}
          {obrigatorio ? (
            <span className="ml-2 rounded-full border border-rose-400/30 bg-rose-400/10 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wider text-rose-100">
              Obrigatório
            </span>
          ) : (
            <span className="ml-2 text-xs font-normal text-slate-400">opcional</span>
          )}
        </p>
        <button
          type="button"
          onClick={() => inputRef.current?.click()}
          disabled={ocupado}
          className="rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1.5 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:opacity-50"
          data-testid={`anexo-upload-${tipo}${itemId ? `-item-${itemId}` : ''}`}
        >
          {anexar.isPending ? 'Enviando...' : 'Anexar arquivo'}
        </button>
        <input
          ref={inputRef}
          type="file"
          accept={ACCEPT}
          onChange={(event) => void handleFile(event)}
          className="hidden"
        />
      </div>

      {doTipo.length === 0 ? (
        <p className="mt-2 text-xs text-slate-400">
          Nenhum arquivo anexado. Imagem (JPG, PNG, GIF) ou PDF, até 5 MB.
        </p>
      ) : (
        <ul className="mt-3 space-y-2">
          {doTipo.map((documento) => (
            <li
              key={documento.id}
              className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-white/10 bg-slate-950/40 px-3 py-2 text-sm text-slate-200"
            >
              <span className="truncate">{documento.nome_original}</span>
              <span className="flex gap-2">
                <button
                  type="button"
                  onClick={() =>
                    void abrirDocumento(solicitacaoId, documento.id, documento.nome_original)
                  }
                  className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-white transition hover:bg-white/10"
                >
                  Abrir
                </button>
                {travado ? (
                  <span
                    className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-slate-400"
                    title="Guia já gerada: o anexo é evidência do envio."
                  >
                    Guia gerada
                  </span>
                ) : (
                  <button
                    type="button"
                    onClick={() => setAExcluir(documento)}
                    disabled={ocupado}
                    className="rounded-full border border-rose-400/30 bg-rose-400/10 px-3 py-1 text-xs font-semibold text-rose-100 transition hover:bg-rose-400/20 disabled:opacity-50"
                  >
                    Remover
                  </button>
                )}
              </span>
            </li>
          ))}
        </ul>
      )}

      {aExcluir ? (
        <ConfirmarExclusao
          titulo="Excluir anexo"
          descricao="O arquivo será apagado do servidor e não poderá ser recuperado. Se a guia já tiver sido enviada à operadora, este anexo é a evidência do envio."
          alvo={aExcluir.nome_original}
          confirmando={remover.isPending}
          onConfirmar={() => void handleRemove()}
          onCancelar={() => setAExcluir(null)}
        />
      ) : null}
    </div>
  )
}

export function SolicitacaoAnexos({ solicitacao }: { solicitacao: Solicitacao }) {
  const [erro, setErro] = useState<string | null>(null)
  const documentosDaSolicitacao = (solicitacao.documentos ?? []).filter(
    (documento) => !documento.solicitacao_item_id,
  )
  const algumItemComGuia =
    Boolean(solicitacao.guia) || (solicitacao.itens ?? []).some((item) => Boolean(item.guia_id))

  return (
    <section className="space-y-4 rounded-3xl border border-white/10 bg-slate-950/40 p-5" data-testid="solicitacao-anexos">
      <div>
        <h3 className="text-lg font-semibold text-white">Anexos</h3>
        <p className="mt-1 text-sm text-slate-300">
          O Pedido Médico vale para o pedido inteiro e é exigido no envio à Unimed. Plano
          Individualizado e Relatório de Evolução são anexados por especialidade.
        </p>
      </div>

      {erro ? (
        <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
          {erro}
        </p>
      ) : null}

      <div className="grid gap-3 md:grid-cols-2">
        {DOCUMENTOS_DA_SOLICITACAO.map((tipo) => (
          <DocumentoSlot
            key={tipo}
            solicitacaoId={solicitacao.id}
            tipo={tipo}
            obrigatorio={tipo === 'pedido_medico'}
            documentos={documentosDaSolicitacao}
            travado={algumItemComGuia}
            onError={setErro}
          />
        ))}
      </div>

      {(solicitacao.itens ?? []).map((item) => (
        <div key={item.id} className="space-y-3 rounded-2xl border border-white/10 bg-white/5 p-4">
          <p className="text-sm font-semibold text-white">
            {item.especialidade?.nome ?? `Especialidade #${item.especialidade_id}`}
            {item.especialidade?.mapeamento_convenio?.codigo_procedimento
              ? ` · ${item.especialidade.mapeamento_convenio.codigo_procedimento}`
              : ''}
            <span className="ml-2 text-xs font-normal text-slate-400">
              {item.profissional?.nome ?? `Profissional #${item.profissional_id}`}
            </span>
          </p>

          <div className="grid gap-3 md:grid-cols-2">
            {DOCUMENTOS_POR_ITEM.map((tipo) => (
              <DocumentoSlot
                key={tipo}
                solicitacaoId={solicitacao.id}
                tipo={tipo}
                itemId={item.id}
                documentos={item.documentos ?? []}
                travado={Boolean(item.guia_id)}
                onError={setErro}
              />
            ))}
          </div>
        </div>
      ))}
    </section>
  )
}
