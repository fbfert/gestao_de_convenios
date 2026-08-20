import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { Botao } from '../../components/ui/Botao'
import { Select } from '../../components/ui/Select'
import { useProfissionais } from '../../lib/queries/useReferenceData'
import {
  getHttpErrorMessage,
  useAtualizarLancamento,
  useLancamento,
  type LancamentoEditForm,
} from './useLancamentos'

function fieldClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

const formVazio: LancamentoEditForm = {
  profissional_id: '',
  data_sessao: '',
  hora_inicio: '',
  hora_fim: '',
  acompanhante: '',
  resumo_atividades: '',
  observacoes: '',
}

export function LancamentoEditarPage() {
  const { id } = useParams()
  const lancamentoId = id && /^\d+$/.test(id) ? Number(id) : null
  const navigate = useNavigate()

  const lancamentoQuery = useLancamento(lancamentoId)
  const profissionaisQuery = useProfissionais()
  const atualizar = useAtualizarLancamento()

  const [form, setForm] = useState<LancamentoEditForm>(formVazio)
  const [erro, setErro] = useState<string | null>(null)

  useEffect(() => {
    const lancamento = lancamentoQuery.data
    if (!lancamento) {
      return
    }

    setForm({
      profissional_id: String(lancamento.profissional_id),
      data_sessao: lancamento.data_sessao.slice(0, 10),
      hora_inicio: lancamento.hora_inicio ?? '',
      hora_fim: lancamento.hora_fim ?? '',
      acompanhante: lancamento.acompanhante ?? '',
      resumo_atividades: lancamento.resumo_atividades ?? '',
      observacoes: lancamento.observacoes ?? '',
    })
  }, [lancamentoQuery.data])

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!lancamentoId) {
      return
    }

    setErro(null)

    try {
      await atualizar.mutateAsync({ id: lancamentoId, payload: form })
      navigate('/lancamentos')
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível salvar as alterações.'))
    }
  }

  const lancamento = lancamentoQuery.data

  return (
    <div className="space-y-6" data-testid="lancamento-editar-page">
      <Link to="/lancamentos" className="text-sm font-semibold text-cyan-200 hover:text-cyan-100">
        ← Voltar para sessões
      </Link>

      <div>
        <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Sessões</p>
        <h2 className="mt-2 text-3xl font-semibold text-white">
          Editar sessão {lancamentoId ? `#${lancamentoId}` : ''}
        </h2>
        <p className="mt-2 text-sm text-slate-400">
          Toda alteração fica registrada nos Logs de Auditoria, com o valor anterior e o novo.
        </p>
      </div>

      {lancamentoQuery.isLoading ? (
        <div className="rounded-3xl border border-white/10 bg-white/5 p-6 text-slate-300">Carregando...</div>
      ) : lancamentoQuery.isError || !lancamento ? (
        <div className="rounded-3xl border border-rose-400/20 bg-rose-500/10 p-6 text-rose-100">
          Sessão não encontrada.
        </div>
      ) : (
        <form onSubmit={handleSubmit} className="space-y-4 rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
          <div className="space-y-2">
            <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Antecipação</span>
            <p className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white">
              #{lancamento.antecipacao?.id ?? lancamento.antecipacao_id}
            </p>
            <p className="text-xs text-slate-400">
              Não é editável aqui: mudar de antecipação moveria a cota consumida para outro paciente/ciclo. Para
              corrigir isso, remova a sessão e lance de novo na antecipação certa.
            </p>
          </div>

          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Profissional executante</span>
            <Select
              value={form.profissional_id}
              onChange={(event) => setForm((current) => ({ ...current, profissional_id: event.target.value }))}
              className={fieldClasses()}
              data-testid="lancamento-editar-profissional"
            >
              <option value="" disabled>
                Selecione
              </option>
              {(profissionaisQuery.data ?? []).map((profissional) => (
                <option key={profissional.id} value={profissional.id}>
                  {profissional.nome}
                </option>
              ))}
            </Select>
          </label>

          <div className="grid gap-4 md:grid-cols-3">
            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Data da sessão</span>
              <input
                type="date"
                value={form.data_sessao}
                onChange={(event) => setForm((current) => ({ ...current, data_sessao: event.target.value }))}
                className={fieldClasses()}
                data-testid="lancamento-editar-data"
              />
            </label>
            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Hora início</span>
              <input
                type="time"
                value={form.hora_inicio}
                onChange={(event) => setForm((current) => ({ ...current, hora_inicio: event.target.value }))}
                className={fieldClasses()}
                data-testid="lancamento-editar-hora-inicio"
              />
            </label>
            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Hora fim</span>
              <input
                type="time"
                value={form.hora_fim}
                onChange={(event) => setForm((current) => ({ ...current, hora_fim: event.target.value }))}
                className={fieldClasses()}
                data-testid="lancamento-editar-hora-fim"
              />
            </label>
          </div>

          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Acompanhante</span>
            <input
              value={form.acompanhante}
              onChange={(event) => setForm((current) => ({ ...current, acompanhante: event.target.value }))}
              className={fieldClasses()}
              data-testid="lancamento-editar-acompanhante"
            />
          </label>

          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Resumo das atividades</span>
            <textarea
              value={form.resumo_atividades}
              onChange={(event) => setForm((current) => ({ ...current, resumo_atividades: event.target.value }))}
              className={fieldClasses()}
              rows={4}
              data-testid="lancamento-editar-resumo"
            />
          </label>

          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Observações</span>
            <textarea
              value={form.observacoes}
              onChange={(event) => setForm((current) => ({ ...current, observacoes: event.target.value }))}
              className={fieldClasses()}
              rows={3}
              data-testid="lancamento-editar-observacoes"
            />
          </label>

          {erro ? (
            <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">{erro}</p>
          ) : null}

          <div className="flex gap-2">
            <Botao type="submit" variante="primario" disabled={atualizar.isPending} data-testid="lancamento-editar-salvar">
              {atualizar.isPending ? 'Salvando...' : 'Salvar alterações'}
            </Botao>
            <Botao variante="secundario" onClick={() => navigate('/lancamentos')}>
              Cancelar
            </Botao>
          </div>
        </form>
      )}
    </div>
  )
}
