import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import { useMatch, useNavigate } from 'react-router-dom'
import { useEspecialidades } from '../../lib/queries/useReferenceData'
import { getHttpErrorMessage, useAtualizarProfissional, useCriarProfissional, useProfissionaisCrud } from './useProfissionais'
import type { Profissional, ProfissionalForm, ProfissionalPayload } from './types'

const emptyForm: ProfissionalForm = {
  nome: '',
  especialidade_id: '',
  conselho_registro: '',
  percentual_repasse: '',
  ativo: true,
}

function fieldClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function statusTone(ativo: boolean) {
  return ativo
    ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-100'
    : 'border-rose-400/30 bg-rose-400/10 text-rose-100'
}

function toForm(profissional: Profissional): ProfissionalForm {
  return {
    nome: profissional.nome,
    especialidade_id: String(profissional.especialidade_id),
    conselho_registro: profissional.conselho_registro ?? '',
    percentual_repasse: profissional.percentual_repasse ?? '',
    ativo: profissional.ativo,
  }
}

function toPayload(form: ProfissionalForm): ProfissionalPayload {
  return {
    nome: form.nome.trim(),
    especialidade_id: Number(form.especialidade_id),
    conselho_registro: form.conselho_registro.trim() === '' ? null : form.conselho_registro.trim(),
    percentual_repasse: form.percentual_repasse.trim() === '' ? null : Number(form.percentual_repasse),
    ativo: form.ativo,
  }
}

export function ProfissionaisPage() {
  const navigate = useNavigate()
  const isCreateRoute = useMatch('/profissionais/novo') !== null
  const editRouteMatch = useMatch('/profissionais/:id/editar')
  const editingId = editRouteMatch ? Number(editRouteMatch.params.id) : null
  const isEditRoute = editingId !== null && Number.isInteger(editingId)
  const isFormRoute = isCreateRoute || isEditRoute
  const [busca, setBusca] = useState('')
  const [draftBusca, setDraftBusca] = useState('')
  const [especialidadeFiltro, setEspecialidadeFiltro] = useState('')
  const [draftEspecialidadeFiltro, setDraftEspecialidadeFiltro] = useState('')
  const [form, setForm] = useState<ProfissionalForm>(emptyForm)
  const [formError, setFormError] = useState<string | null>(null)
  const carregadoRef = useRef<number | null>(null)

  const especialidadesQuery = useEspecialidades()
  const profissionaisQuery = useProfissionaisCrud({
    busca,
    especialidade_id: especialidadeFiltro,
    incluir_inativos: true,
  })
  const criarProfissional = useCriarProfissional()
  const atualizarProfissional = useAtualizarProfissional()

  const especialidades = useMemo(() => especialidadesQuery.data ?? [], [especialidadesQuery.data])
  const profissionais = useMemo(() => profissionaisQuery.data ?? [], [profissionaisQuery.data])
  const totalAtivos = useMemo(() => profissionais.filter((profissional) => profissional.ativo).length, [profissionais])
  const totalInativos = profissionais.length - totalAtivos
  const profissionalEmEdicao = useMemo(
    () => (isEditRoute ? profissionais.find((profissional) => profissional.id === editingId) ?? null : null),
    [isEditRoute, editingId, profissionais],
  )

  // Carrega o registro no formulário uma única vez por id: evita que um refetch
  // em background sobrescreva o que o usuário já digitou.
  useEffect(() => {
    if (!isEditRoute) {
      carregadoRef.current = null
      return
    }

    if (!profissionalEmEdicao || carregadoRef.current === profissionalEmEdicao.id) {
      return
    }

    carregadoRef.current = profissionalEmEdicao.id
    setForm(toForm(profissionalEmEdicao))
    setFormError(null)
  }, [isEditRoute, profissionalEmEdicao])

  const handleFilterSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setBusca(draftBusca.trim())
    setEspecialidadeFiltro(draftEspecialidadeFiltro)
  }

  const handleNew = () => {
    setForm({
      ...emptyForm,
      especialidade_id: draftEspecialidadeFiltro,
    })
    setFormError(null)
    carregadoRef.current = null
    navigate('/profissionais/novo')
  }

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFormError(null)

    try {
      const payload = toPayload(form)

      if (isEditRoute && editingId) {
        await atualizarProfissional.mutateAsync({
          id: editingId,
          payload,
        })
      } else {
        await criarProfissional.mutateAsync(payload)
      }

      setForm(emptyForm)
      carregadoRef.current = null
      navigate('/profissionais')
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível salvar o profissional.'))
    }
  }

  const handleEdit = (profissional: Profissional) => {
    setFormError(null)
    navigate(`/profissionais/${profissional.id}/editar`)
  }

  const handleToggleAtivo = async (profissional: Profissional) => {
    setFormError(null)

    try {
      await atualizarProfissional.mutateAsync({
        id: profissional.id,
        payload: { ativo: !profissional.ativo },
      })
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível atualizar o status do profissional.'))
    }
  }

  const handleCancel = () => {
    setForm(emptyForm)
    setFormError(null)
    carregadoRef.current = null
    navigate('/profissionais')
  }

  return (
    <div className="space-y-8" data-testid="profissionais-page">
      {!isFormRoute ? (
      <section className="space-y-4">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Profissionais</p>
            <h2 className="mt-2 text-3xl font-semibold text-white">
              Cadastro e referência de profissionais executantes
            </h2>
            <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
              Use esta tela para manter os profissionais da clínica que executam os atendimentos e
              alimentam os fluxos de guias, sessões, lançamentos e conciliação.
            </p>
          </div>

          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={handleNew}
              className="rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300"
              data-testid="profissional-novo"
            >
              Novo
            </button>
          </div>
        </div>

        <div className="grid gap-4 md:grid-cols-3">
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Total</p>
            <p className="mt-2 text-2xl font-semibold text-white">{profissionais.length}</p>
          </article>
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Ativos</p>
            <p className="mt-2 text-2xl font-semibold text-white">{totalAtivos}</p>
          </article>
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Inativos</p>
            <p className="mt-2 text-2xl font-semibold text-white">{totalInativos}</p>
          </article>
        </div>
      </section>
      ) : null}

      {isFormRoute && (!isEditRoute || profissionalEmEdicao) ? (
        <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <form onSubmit={handleSubmit} className="space-y-4" data-testid="profissional-form">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="text-lg font-semibold text-white">
                  {editingId ? 'Editar profissional' : 'Novo profissional'}
                </h3>
                <p className="text-sm text-slate-300">
                  O profissional é o executante do atendimento e pode ter percentual de repasse
                  próprio.
                </p>
              </div>
              {editingId ? (
                <span className="rounded-full border border-cyan-300/20 bg-cyan-400/10 px-3 py-1 text-xs font-semibold text-cyan-100">
                  Editando #{editingId}
                </span>
              ) : null}
            </div>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Nome</span>
              <input
                value={form.nome}
                onChange={(event) => setForm((current) => ({ ...current, nome: event.target.value }))}
                className={fieldClasses()}
                required
                data-testid="profissional-nome"
              />
            </label>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Especialidade</span>
              <select
                value={form.especialidade_id}
                onChange={(event) =>
                  setForm((current) => ({ ...current, especialidade_id: event.target.value }))
                }
                className={fieldClasses()}
                required
                data-testid="profissional-especialidade"
              >
                <option value="">Selecione</option>
                {especialidades.map((especialidade) => (
                  <option key={especialidade.id} value={especialidade.id}>
                    {especialidade.nome}
                  </option>
                ))}
              </select>
            </label>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Conselho / registro</span>
              <input
                value={form.conselho_registro}
                onChange={(event) =>
                  setForm((current) => ({ ...current, conselho_registro: event.target.value }))
                }
                className={fieldClasses()}
                data-testid="profissional-conselho"
              />
            </label>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Percentual de repasse</span>
              <input
                value={form.percentual_repasse}
                onChange={(event) =>
                  setForm((current) => ({ ...current, percentual_repasse: event.target.value }))
                }
                type="number"
                step="0.01"
                min="0"
                max="100"
                className={fieldClasses()}
                data-testid="profissional-percentual"
              />
            </label>

            <label className="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
              <input
                type="checkbox"
                checked={form.ativo}
                onChange={(event) => setForm((current) => ({ ...current, ativo: event.target.checked }))}
                className="h-4 w-4 rounded border-white/20 bg-white/10 text-cyan-300 focus:ring-cyan-300/20"
                data-testid="profissional-ativo"
              />
              <span className="text-sm font-medium text-slate-200">Ativo</span>
            </label>

            {formError ? (
              <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                {formError}
              </p>
            ) : null}

            <div className="flex gap-3">
              <button
                type="submit"
                className="inline-flex flex-1 items-center justify-center rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60"
                disabled={criarProfissional.isPending || atualizarProfissional.isPending}
                data-testid="profissional-submit"
              >
                {editingId
                  ? atualizarProfissional.isPending
                    ? 'Salvando...'
                    : 'Salvar alterações'
                  : criarProfissional.isPending
                    ? 'Salvando...'
                    : 'Criar profissional'}
              </button>
              {editingId ? (
                <button
                  type="button"
                  onClick={handleCancel}
                  className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                  data-testid="profissional-cancelar"
                >
                  Cancelar
                </button>
              ) : (
                <button
                  type="button"
                  onClick={handleCancel}
                  className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                  data-testid="profissional-fechar"
                >
                  Fechar
                </button>
              )}
            </div>
          </form>
        </section>
      ) : null}

      {isEditRoute && !profissionalEmEdicao ? (
        <section
          className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6"
          data-testid="profissional-edicao-indisponivel"
        >
          {profissionaisQuery.isLoading ? (
            <p className="text-sm text-slate-300">Carregando profissional...</p>
          ) : (
            <div className="space-y-4">
              <p className="text-sm text-rose-100">
                Profissional não encontrado. Ele pode ter sido removido ou o endereço está incorreto.
              </p>
              <button
                type="button"
                onClick={handleCancel}
                className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                data-testid="profissional-voltar"
              >
                Voltar para a lista
              </button>
            </div>
          )}
        </section>
      ) : null}

      {!isFormRoute ? (
      <section className="space-y-4 rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h3 className="text-lg font-semibold text-white">Lista</h3>
            <p className="text-sm text-slate-300">
              Mostra todos os profissionais da clínica, com pesquisa por nome, conselho e
              especialidade.
            </p>
          </div>

          <form className="flex flex-wrap gap-3" onSubmit={handleFilterSubmit}>
            <label className="min-w-56 space-y-2">
              <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Busca</span>
              <input
                value={draftBusca}
                onChange={(event) => setDraftBusca(event.target.value)}
                className={fieldClasses()}
                placeholder="Nome, conselho ou especialidade"
                data-testid="profissional-busca"
              />
            </label>
            <label className="min-w-56 space-y-2">
              <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Especialidade</span>
              <select
                value={draftEspecialidadeFiltro}
                onChange={(event) => setDraftEspecialidadeFiltro(event.target.value)}
                className={fieldClasses()}
                data-testid="profissional-busca-especialidade"
              >
                <option value="">Todas</option>
                {especialidades.map((especialidade) => (
                  <option key={especialidade.id} value={especialidade.id}>
                    {especialidade.nome}
                  </option>
                ))}
              </select>
            </label>
            <button
              type="submit"
              className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
              data-testid="profissional-busca-submit"
            >
              Filtrar
            </button>
          </form>
        </div>

        {profissionaisQuery.isLoading ? (
          <div className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
            Carregando profissionais...
          </div>
        ) : profissionaisQuery.isError ? (
          <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-sm text-rose-100">
            Não foi possível carregar os profissionais.
          </div>
        ) : (
          <div className="overflow-hidden rounded-3xl border border-white/10">
            <table className="w-full border-collapse text-left text-sm">
              <thead className="bg-white/5 text-xs uppercase tracking-[0.25em] text-slate-400">
                <tr>
                  <th className="px-4 py-3">Nome</th>
                  <th className="px-4 py-3">Especialidade</th>
                  <th className="px-4 py-3">Conselho</th>
                  <th className="px-4 py-3">Repasse</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Ações</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/10 bg-slate-950/30">
                {profissionais.map((profissional) => (
                  <tr key={profissional.id} data-testid={`profissional-row-${profissional.id}`}>
                    <td className="px-4 py-4 text-slate-100">
                      <div className="font-medium">{profissional.nome}</div>
                      <div className="text-xs text-slate-400">#{profissional.id}</div>
                    </td>
                    <td className="px-4 py-4 text-slate-200">
                      {profissional.especialidade?.nome ?? '—'}
                    </td>
                    <td className="px-4 py-4 text-slate-200">{profissional.conselho_registro ?? '—'}</td>
                    <td className="px-4 py-4 text-slate-200">
                      {profissional.percentual_repasse ? `${profissional.percentual_repasse}%` : '—'}
                    </td>
                    <td className="px-4 py-4">
                      <span
                        className={`inline-flex rounded-full border px-3 py-1 text-xs font-semibold ${statusTone(
                          profissional.ativo,
                        )}`}
                        data-testid={`profissional-status-${profissional.id}`}
                      >
                        {profissional.ativo ? 'Ativo' : 'Inativo'}
                      </span>
                    </td>
                    <td className="px-4 py-4">
                      <div className="flex flex-wrap gap-2">
                        <button
                          type="button"
                          onClick={() => handleEdit(profissional)}
                          className="rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1.5 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
                          data-testid={`profissional-editar-${profissional.id}`}
                        >
                          Editar
                        </button>
                        <button
                          type="button"
                          onClick={() => handleToggleAtivo(profissional)}
                          className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/10 disabled:opacity-60"
                          disabled={atualizarProfissional.isPending}
                          data-testid={`profissional-toggle-${profissional.id}`}
                        >
                          {profissional.ativo ? 'Desativar' : 'Ativar'}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
                {profissionais.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-slate-300">
                      Nenhum profissional encontrado.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        )}
      </section>
      ) : null}
    </div>
  )
}
