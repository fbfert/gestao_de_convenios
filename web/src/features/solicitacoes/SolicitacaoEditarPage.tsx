import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { Select } from '../../components/ui/Select'
import { useMedicos } from '../../lib/queries/useReferenceData'
import {
  getHttpErrorMessage,
  useAtualizarSolicitacao,
  useSolicitacao,
  type SolicitacaoEditForm,
} from './useSolicitacoes'
import { Botao } from '../../components/ui/Botao'
import { CidsCampo } from '../cids/CidsCampo'

function fieldClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

const formVazio: SolicitacaoEditForm = {
  medico_id: '',
  cid_ids: [],
  solicitado_em: '',
  observacoes: '',
}

export function SolicitacaoEditarPage() {
  const { id } = useParams()
  const solicitacaoId = id && /^\d+$/.test(id) ? Number(id) : null
  const navigate = useNavigate()

  const solicitacaoQuery = useSolicitacao(solicitacaoId)
  const medicosQuery = useMedicos()
  const atualizar = useAtualizarSolicitacao()

  const [form, setForm] = useState<SolicitacaoEditForm>(formVazio)
  const [erro, setErro] = useState<string | null>(null)

  useEffect(() => {
    const solicitacao = solicitacaoQuery.data
    if (!solicitacao) {
      return
    }

    setForm({
      medico_id: String(solicitacao.medico_id),
      cid_ids: (solicitacao.cids ?? []).map((cid) => String(cid.id)),
      solicitado_em: solicitacao.solicitado_em.slice(0, 10),
      observacoes: solicitacao.observacoes ?? '',
    })
  }, [solicitacaoQuery.data])

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!solicitacaoId) {
      return
    }

    setErro(null)

    try {
      await atualizar.mutateAsync({ id: solicitacaoId, payload: form })
      navigate('/solicitacoes')
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível salvar as alterações.'))
    }
  }

  const solicitacao = solicitacaoQuery.data

  return (
    <div className="space-y-6" data-testid="solicitacao-editar-page">
      <Link to="/solicitacoes" className="inline-flex min-h-6 items-center text-corpo font-semibold text-cyan-200 hover:text-cyan-100">
        ← Voltar para solicitações
      </Link>

      <div>
        <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Solicitações</p>
        <h2 className="mt-2 text-display font-semibold text-white">
          Editar solicitação {solicitacaoId ? `#${solicitacaoId}` : ''}
        </h2>
        <p className="mt-2 text-corpo text-slate-400">
          Toda alteração fica registrada nos Logs de Auditoria, com o valor anterior e o novo.
        </p>
      </div>

      {solicitacaoQuery.isLoading ? (
        <div className="rounded-superficie border border-linha bg-fundo p-6 shadow-e1 text-slate-300">Carregando...</div>
      ) : solicitacaoQuery.isError || !solicitacao ? (
        <div className="rounded-3xl border border-rose-400/20 bg-rose-500/10 p-6 text-rose-100">
          Solicitação não encontrada.
        </div>
      ) : (
        <form onSubmit={handleSubmit} className="space-y-4 rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Paciente</span>
              <p className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white">
                {solicitacao.paciente?.nome ?? solicitacao.paciente_id}
              </p>
            </div>
            <div className="space-y-2">
              <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Convênio</span>
              <p className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white">
                {solicitacao.convenio?.nome ?? solicitacao.convenio_id}
              </p>
            </div>
          </div>
          <p className="text-meta text-slate-400">
            Paciente e convênio não são editáveis aqui: guia, antecipação e conciliação já geradas usam esses dados.
          </p>

          <label className="block space-y-2">
            <span className="text-corpo font-medium text-slate-200">Médico solicitante</span>
            <Select
              value={form.medico_id}
              onChange={(event) => setForm((current) => ({ ...current, medico_id: event.target.value }))}
              className={fieldClasses()}
              data-testid="solicitacao-editar-medico"
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
            <span className="text-corpo font-medium text-slate-200">CID</span>
            <CidsCampo
              value={form.cid_ids}
              onChange={(cidIds) => setForm((current) => ({ ...current, cid_ids: cidIds }))}
              testIdPrefix="solicitacao-editar-cid"
            />
          </label>

          <label className="block space-y-2">
            <span className="text-corpo font-medium text-slate-200">Data da solicitação</span>
            <input
              type="date"
              value={form.solicitado_em}
              onChange={(event) => setForm((current) => ({ ...current, solicitado_em: event.target.value }))}
              className={fieldClasses()}
              data-testid="solicitacao-editar-data"
            />
          </label>

          <label className="block space-y-2">
            <span className="text-corpo font-medium text-slate-200">Observações</span>
            <textarea
              value={form.observacoes}
              onChange={(event) => setForm((current) => ({ ...current, observacoes: event.target.value }))}
              className={fieldClasses()}
              rows={4}
              data-testid="solicitacao-editar-observacoes"
            />
          </label>

          {erro ? (
            <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">{erro}</p>
          ) : null}

          <div className="flex gap-2">
            <Botao
              type="submit"
              variante="primario"
              disabled={atualizar.isPending || form.cid_ids.length === 0}
              data-testid="solicitacao-editar-salvar"
            >
              {atualizar.isPending ? 'Salvando...' : 'Salvar alterações'}
            </Botao>
            <Botao type="button" variante="secundario" onClick={() => navigate('/solicitacoes')}>
              Cancelar
            </Botao>
          </div>
        </form>
      )}
    </div>
  )
}
