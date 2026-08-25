import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react'
import { useEffect, useState, type ReactNode } from 'react'
import { Select } from '../../components/ui/Select'
import { usePode } from '../../lib/permissoes'
import { useMedicos } from '../../lib/queries/useReferenceData'
import { GuiaDetalheResumo } from '../guias/GuiaDetalheResumo'
import { getHttpErrorMessage, useGuia } from '../guias/useGuias'
import { abrirPedidoMedico, useAtualizarSolicitacao, type SolicitacaoEditForm } from './useSolicitacoes'
import { SolicitacaoAnexos } from './SolicitacaoAnexos'
import type { Solicitacao } from './types'
import { Botao } from '../../components/ui/Botao'
import { CidsCampo } from '../cids/CidsCampo'

type SolicitacaoGuiaModalProps = {
  solicitacao: Solicitacao | null
  onClose: () => void
}

function fieldClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function DetailItem({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
      <p className="text-xs uppercase tracking-[0.25em] text-slate-400">{label}</p>
      <div className="mt-2 text-sm font-medium text-white">{children}</div>
    </div>
  )
}

const formVazio: SolicitacaoEditForm = {
  medico_id: '',
  cid_ids: [],
  solicitado_em: '',
  observacoes: '',
}

function SolicitacaoDados({ solicitacao }: { solicitacao: Solicitacao }) {
  const pode = usePode()
  const medicosQuery = useMedicos()
  const atualizar = useAtualizarSolicitacao()

  const [editando, setEditando] = useState(false)
  const [form, setForm] = useState<SolicitacaoEditForm>(formVazio)
  const [erro, setErro] = useState<string | null>(null)

  useEffect(() => {
    setEditando(false)
  }, [solicitacao.id])

  const iniciarEdicao = () => {
    setForm({
      medico_id: String(solicitacao.medico_id),
      cid_ids: (solicitacao.cids ?? []).map((cid) => String(cid.id)),
      solicitado_em: solicitacao.solicitado_em.slice(0, 10),
      observacoes: solicitacao.observacoes ?? '',
    })
    setErro(null)
    setEditando(true)
  }

  const salvar = async () => {
    setErro(null)

    try {
      await atualizar.mutateAsync({ id: solicitacao.id, payload: form })
      setEditando(false)
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível salvar as alterações.'))
    }
  }

  return (
    <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h3 className="text-lg font-semibold text-white">Dados da solicitação</h3>
        {pode('solicitacoes.manage') && !editando ? (
          <button
            type="button"
            onClick={iniciarEdicao}
            className="rounded-2xl border border-cyan-300/40 bg-cyan-400/15 px-4 py-2 text-sm font-medium text-cyan-50 transition hover:bg-cyan-400/25"
            data-testid="solicitacao-modal-ativar-edicao"
          >
            Ativar edição
          </button>
        ) : null}
      </div>

      <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <DetailItem label="Paciente">{solicitacao.paciente?.nome ?? solicitacao.paciente_id}</DetailItem>
        <DetailItem label="Convênio">{solicitacao.convenio?.nome ?? solicitacao.convenio_id}</DetailItem>
      </div>
      <p className="mt-2 text-xs text-slate-400">
        Paciente e convênio não são editáveis aqui: guia, antecipação e conciliação já geradas usam esses dados.
      </p>

      {!editando ? (
        <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <DetailItem label="Médico solicitante">{solicitacao.medico?.nome ?? solicitacao.medico_id}</DetailItem>
          <DetailItem label="CID">
            {solicitacao.cids && solicitacao.cids.length > 0
              ? solicitacao.cids.map((cid) => `${cid.codigo} — ${cid.descricao}`).join('; ')
              : '-'}
          </DetailItem>
          <DetailItem label="Data da solicitação">{solicitacao.solicitado_em}</DetailItem>
          <DetailItem label="Observações">{solicitacao.observacoes ?? '-'}</DetailItem>
        </div>
      ) : (
        <div className="mt-4 space-y-4">
          <div className="grid gap-4 md:grid-cols-2">
            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Médico solicitante</span>
              <Select
                value={form.medico_id}
                onChange={(event) => setForm((current) => ({ ...current, medico_id: event.target.value }))}
                className={fieldClasses()}
                data-testid="solicitacao-modal-medico"
              >
                <option value="" disabled>
                  Selecione
                </option>
                {(medicosQuery.data ?? []).map((medico) => (
                  <option key={medico.id} value={medico.id}>
                    {medico.nome}
                  </option>
                ))}
              </Select>
            </label>
            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">CID</span>
              <CidsCampo
                value={form.cid_ids}
                onChange={(cidIds) => setForm((current) => ({ ...current, cid_ids: cidIds }))}
                testIdPrefix="solicitacao-modal-cid"
              />
            </label>
          </div>

          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Data da solicitação</span>
            <input
              type="date"
              value={form.solicitado_em}
              onChange={(event) => setForm((current) => ({ ...current, solicitado_em: event.target.value }))}
              className={fieldClasses()}
              data-testid="solicitacao-modal-data"
            />
          </label>

          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Observações</span>
            <textarea
              value={form.observacoes}
              onChange={(event) => setForm((current) => ({ ...current, observacoes: event.target.value }))}
              className={fieldClasses()}
              rows={3}
              data-testid="solicitacao-modal-observacoes"
            />
          </label>

          {erro ? (
            <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">{erro}</p>
          ) : null}

          <div className="flex gap-2">
            <Botao
              type="button"
              variante="primario"
              onClick={() => void salvar()}
              disabled={atualizar.isPending || form.cid_ids.length === 0}
              data-testid="solicitacao-modal-salvar"
            >
              {atualizar.isPending ? 'Salvando...' : 'Salvar alterações'}
            </Botao>
            <Botao
              type="button"
              variante="secundario"
              onClick={() => setEditando(false)}
              data-testid="solicitacao-modal-cancelar"
            >
              Cancelar
            </Botao>
          </div>
        </div>
      )}
    </section>
  )
}

export function SolicitacaoGuiaModal({ solicitacao, onClose }: SolicitacaoGuiaModalProps) {
  const guiaId = solicitacao?.guia?.id ?? null
  const guiaQuery = useGuia(guiaId)
  const open = solicitacao !== null
  const temDocumentoPedidoMedico = (solicitacao?.documentos ?? []).some(
    (documento) => documento.tipo === 'pedido_medico',
  )

  return (
    <Dialog open={open} onClose={onClose} className="relative z-50">
      <DialogBackdrop className="fixed inset-0 bg-slate-950/75 backdrop-blur-sm" />
      <div className="fixed inset-0 overflow-y-auto p-4 sm:p-6">
        <div className="flex min-h-full items-center justify-center">
          <DialogPanel
            className="w-full max-w-6xl rounded-[2rem] border border-white/10 bg-slate-950 p-6 text-white shadow-2xl shadow-black/60"
            data-testid="solicitacao-guia-modal"
          >
            <div className="flex items-start justify-between gap-4">
              <div>
                <DialogTitle className="text-xl font-semibold">
                  Detalhes da solicitação{solicitacao ? ` #${solicitacao.id}` : ''}
                </DialogTitle>
                <p className="mt-1 text-sm text-slate-300">
                  Dados da solicitação, anexos do pedido e da guia vinculada, sem sair da lista.
                </p>
              </div>
              <button
                type="button"
                onClick={onClose}
                className="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-sm font-semibold text-white transition hover:bg-white/10"
              >
                Fechar
              </button>
            </div>

            <div className="mt-6 space-y-6">
              {solicitacao ? <SolicitacaoDados solicitacao={solicitacao} /> : null}

              {solicitacao ? <SolicitacaoAnexos solicitacao={solicitacao} /> : null}

              {/* Fallback para solicitações antigas, anteriores à tabela de documentos:
                  elas têm o arquivo no campo legado mas nenhuma linha em solicitacao_documentos. */}
              {solicitacao?.pedido_medico && !temDocumentoPedidoMedico ? (
                <div className="rounded-3xl border border-cyan-400/20 bg-cyan-500/10 p-5 text-sm text-cyan-50">
                  <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                      <p className="font-semibold">Pedido médico anexado</p>
                      <p className="mt-1 text-cyan-50/80">
                        {solicitacao.pedido_medico.nome_original ?? 'Arquivo do pedido médico'}
                      </p>
                    </div>
                    <button
                      type="button"
                      onClick={() =>
                        void abrirPedidoMedico(
                          solicitacao.id,
                          solicitacao.pedido_medico?.nome_original,
                        )
                      }
                      className="rounded-2xl border border-cyan-300/30 bg-cyan-400/10 px-4 py-2 text-sm font-semibold text-cyan-50 transition hover:bg-cyan-400/20"
                    >
                      Abrir pedido
                    </button>
                  </div>
                </div>
              ) : null}

              {!solicitacao?.guia ? (
                <div
                  className="rounded-3xl border border-amber-400/20 bg-amber-500/10 p-5 text-sm text-amber-50"
                  data-testid="solicitacao-guia-empty"
                >
                  Esta solicitação ainda não possui uma guia vinculada.
                </div>
              ) : guiaQuery.isLoading ? (
                <div
                  className="rounded-3xl border border-white/10 bg-white/5 p-5 text-sm text-slate-300"
                  data-testid="solicitacao-guia-loading"
                >
                  Carregando detalhes da guia...
                </div>
              ) : guiaQuery.isError || !guiaQuery.data ? (
                <div
                  className="space-y-2 rounded-3xl border border-rose-400/20 bg-rose-500/10 p-5 text-sm text-rose-100"
                  data-testid="solicitacao-guia-error"
                >
                  <p>Não foi possível carregar a guia vinculada.</p>
                  <p className="text-xs text-rose-100/80">
                    {getHttpErrorMessage(guiaQuery.error, 'Confira o vínculo da solicitação e tente novamente.')}
                  </p>
                </div>
              ) : (
                <div data-testid="solicitacao-guia-content">
                  <GuiaDetalheResumo guia={guiaQuery.data} />
                </div>
              )}
            </div>
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  )
}
