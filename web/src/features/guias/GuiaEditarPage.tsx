import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { Botao } from '../../components/ui/Botao'
import { Select } from '../../components/ui/Select'
import { useEspecialidades, useProfissionais } from '../../lib/queries/useReferenceData'
import { getHttpErrorMessage, useAtualizarGuia, useGuia, type GuiaEditForm } from './useGuias'

function fieldClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

const formVazio: GuiaEditForm = {
  profissional_id: '',
  especialidade_id: '',
  numero_guia: '',
  tipo_terapia: '',
  data_solicitacao: '',
  data_finalizacao: '',
  sessoes_solicitadas: '',
  sessoes_autorizadas: '',
  protocolo_operadora: '',
  senha: '',
  validade_senha: '',
  observacoes: '',
}

export function GuiaEditarPage() {
  const { id } = useParams()
  const guiaId = id && /^\d+$/.test(id) ? Number(id) : null
  const navigate = useNavigate()

  const guiaQuery = useGuia(guiaId)
  const especialidadesQuery = useEspecialidades()
  const profissionaisQuery = useProfissionais()
  const atualizar = useAtualizarGuia()

  const [form, setForm] = useState<GuiaEditForm>(formVazio)
  const [erro, setErro] = useState<string | null>(null)

  useEffect(() => {
    const guia = guiaQuery.data
    if (!guia) {
      return
    }

    setForm({
      profissional_id: String(guia.profissional_id),
      especialidade_id: String(guia.especialidade_id),
      numero_guia: guia.numero_guia ?? '',
      tipo_terapia: guia.tipo_terapia,
      data_solicitacao: guia.data_solicitacao.slice(0, 10),
      data_finalizacao: guia.data_finalizacao?.slice(0, 10) ?? '',
      sessoes_solicitadas: guia.sessoes_solicitadas != null ? String(guia.sessoes_solicitadas) : '',
      sessoes_autorizadas: guia.sessoes_autorizadas != null ? String(guia.sessoes_autorizadas) : '',
      protocolo_operadora: guia.protocolo_operadora ?? '',
      senha: guia.senha ?? '',
      validade_senha: guia.validade_senha?.slice(0, 10) ?? '',
      observacoes: guia.observacoes ?? '',
    })
  }, [guiaQuery.data])

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!guiaId) {
      return
    }

    setErro(null)

    try {
      await atualizar.mutateAsync({ id: guiaId, payload: form })
      navigate('/guias')
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível salvar as alterações.'))
    }
  }

  const guia = guiaQuery.data

  return (
    <div className="space-y-6" data-testid="guia-editar-page">
      <Link to="/guias" className="inline-flex min-h-6 items-center text-corpo font-semibold text-cyan-200 hover:text-cyan-100">
        ← Voltar para guias
      </Link>

      <div>
        <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Guias</p>
        <h2 className="mt-2 text-display font-semibold text-white">Editar guia {guiaId ? `#${guiaId}` : ''}</h2>
        <p className="mt-2 text-corpo text-slate-400">
          Toda alteração fica registrada nos Logs de Auditoria, com o valor anterior e o novo.
        </p>
      </div>

      {guiaQuery.isLoading ? (
        <div className="rounded-superficie border border-linha bg-fundo p-6 shadow-e1 text-slate-300">Carregando...</div>
      ) : guiaQuery.isError || !guia ? (
        <div className="rounded-3xl border border-rose-400/20 bg-rose-500/10 p-6 text-rose-100">
          Guia não encontrada.
        </div>
      ) : (
        <form onSubmit={handleSubmit} className="space-y-4 rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Paciente</span>
              <p className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white">
                {guia.paciente?.nome ?? guia.paciente_id}
              </p>
            </div>
            <div className="space-y-2">
              <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Convênio</span>
              <p className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white">
                {guia.convenio?.nome ?? guia.convenio_id}
              </p>
            </div>
          </div>
          <p className="text-meta text-slate-400">
            Paciente, convênio e status não são editáveis aqui: antecipações e conciliações já geradas usam esses
            dados, e o status muda pelos botões Finalizar/Negar.
          </p>

          <div className="grid gap-4 md:grid-cols-2">
            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Especialidade</span>
              <Select
                value={form.especialidade_id}
                onChange={(event) => setForm((current) => ({ ...current, especialidade_id: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-editar-especialidade"
              >
                <option value="" disabled>
                  Selecione
                </option>
                {(especialidadesQuery.data ?? []).map((especialidade) => (
                  <option key={especialidade.id} value={especialidade.id}>
                    {especialidade.nome}
                  </option>
                ))}
              </Select>
            </label>

            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Profissional executante</span>
              <Select
                value={form.profissional_id}
                onChange={(event) => setForm((current) => ({ ...current, profissional_id: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-editar-profissional"
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
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Número da guia</span>
              <input
                value={form.numero_guia}
                onChange={(event) => setForm((current) => ({ ...current, numero_guia: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-editar-numero"
              />
            </label>
            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Tipo de terapia</span>
              <Select
                value={form.tipo_terapia}
                onChange={(event) => setForm((current) => ({ ...current, tipo_terapia: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-editar-tipo-terapia"
              >
                <option value="especializada">Especializada</option>
                <option value="convencional">Convencional</option>
                <option value="outro">Outro</option>
              </Select>
            </label>
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Data da solicitação</span>
              <input
                type="date"
                value={form.data_solicitacao}
                onChange={(event) => setForm((current) => ({ ...current, data_solicitacao: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-editar-data-solicitacao"
              />
            </label>
            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Data de finalização</span>
              <input
                type="date"
                value={form.data_finalizacao}
                onChange={(event) => setForm((current) => ({ ...current, data_finalizacao: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-editar-data-finalizacao"
              />
            </label>
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Senha</span>
              <input
                value={form.senha}
                onChange={(event) => setForm((current) => ({ ...current, senha: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-editar-senha"
              />
            </label>
            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Validade da senha</span>
              <input
                type="date"
                value={form.validade_senha}
                onChange={(event) => setForm((current) => ({ ...current, validade_senha: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-editar-validade-senha"
              />
            </label>
          </div>

          <div className="grid gap-4 md:grid-cols-3">
            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Sessões solicitadas</span>
              <input
                type="number"
                min="1"
                value={form.sessoes_solicitadas}
                onChange={(event) => setForm((current) => ({ ...current, sessoes_solicitadas: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-editar-sessoes-solicitadas"
              />
            </label>
            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Sessões autorizadas</span>
              <input
                type="number"
                min="0"
                value={form.sessoes_autorizadas}
                onChange={(event) => setForm((current) => ({ ...current, sessoes_autorizadas: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-editar-sessoes-autorizadas"
              />
            </label>
            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Protocolo da operadora</span>
              <input
                value={form.protocolo_operadora}
                onChange={(event) => setForm((current) => ({ ...current, protocolo_operadora: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-editar-protocolo"
              />
            </label>
          </div>

          <label className="block space-y-2">
            <span className="text-corpo font-medium text-slate-200">Observações</span>
            <textarea
              value={form.observacoes}
              onChange={(event) => setForm((current) => ({ ...current, observacoes: event.target.value }))}
              className={fieldClasses()}
              rows={4}
              data-testid="guia-editar-observacoes"
            />
          </label>

          {erro ? (
            <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">{erro}</p>
          ) : null}

          <div className="flex gap-2">
            <Botao type="submit" variante="primario" disabled={atualizar.isPending} data-testid="guia-editar-salvar">
              {atualizar.isPending ? 'Salvando...' : 'Salvar alterações'}
            </Botao>
            <Botao variante="secundario" onClick={() => navigate('/guias')}>
              Cancelar
            </Botao>
          </div>
        </form>
      )}
    </div>
  )
}
