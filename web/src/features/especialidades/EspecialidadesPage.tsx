import { useMemo, useState, type FormEvent } from 'react'
import {
  getHttpErrorMessage,
  useAtualizarEspecialidade,
  useCriarEspecialidade,
  useEspecialidadesCrud,
} from './useEspecialidades'
import type { Especialidade, EspecialidadeForm, EspecialidadePayload } from './types'

const emptyForm: EspecialidadeForm = {
  nome: '',
  ativo: true,
}

type StatusFiltro = 'todas' | 'ativas' | 'inativas'

function fieldClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function statusTone(ativo: boolean) {
  return ativo
    ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-100'
    : 'border-rose-400/30 bg-rose-400/10 text-rose-100'
}

function toForm(especialidade: Especialidade): EspecialidadeForm {
  return {
    nome: especialidade.nome,
    ativo: especialidade.ativo,
  }
}

function toPayload(form: EspecialidadeForm): EspecialidadePayload {
  return {
    nome: form.nome.trim(),
    ativo: form.ativo,
  }
}

export function EspecialidadesPage() {
  const [busca, setBusca] = useState('')
  const [draftBusca, setDraftBusca] = useState('')
  const [statusFiltro, setStatusFiltro] = useState<StatusFiltro>('todas')
  const [draftStatusFiltro, setDraftStatusFiltro] = useState<StatusFiltro>('todas')
  const [editingId, setEditingId] = useState<number | null>(null)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [form, setForm] = useState<EspecialidadeForm>(emptyForm)
  const [formError, setFormError] = useState<string | null>(null)

  const especialidadesQuery = useEspecialidadesCrud(busca)
  const criarEspecialidade = useCriarEspecialidade()
  const atualizarEspecialidade = useAtualizarEspecialidade()

  const especialidades = useMemo(() => especialidadesQuery.data ?? [], [especialidadesQuery.data])
  const especialidadesFiltradas = useMemo(() => {
    if (statusFiltro === 'ativas') {
      return especialidades.filter((especialidade) => especialidade.ativo)
    }

    if (statusFiltro === 'inativas') {
      return especialidades.filter((especialidade) => !especialidade.ativo)
    }

    return especialidades
  }, [especialidades, statusFiltro])
  const totalAtivas = useMemo(
    () => especialidades.filter((especialidade) => especialidade.ativo).length,
    [especialidades],
  )
  const totalInativas = especialidades.length - totalAtivas

  const handleFilterSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setBusca(draftBusca.trim())
    setStatusFiltro(draftStatusFiltro)
  }

  const handleNew = () => {
    setEditingId(null)
    setForm(emptyForm)
    setFormError(null)
    setIsFormOpen(true)
  }

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFormError(null)

    try {
      const payload = toPayload(form)

      if (editingId) {
        await atualizarEspecialidade.mutateAsync({
          id: editingId,
          payload,
        })
      } else {
        await criarEspecialidade.mutateAsync(payload)
      }

      setEditingId(null)
      setForm(emptyForm)
      setIsFormOpen(false)
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível salvar a especialidade.'))
    }
  }

  const handleEdit = (especialidade: Especialidade) => {
    setEditingId(especialidade.id)
    setForm(toForm(especialidade))
    setFormError(null)
    setIsFormOpen(true)
  }

  const handleToggleAtivo = async (especialidade: Especialidade) => {
    setFormError(null)

    try {
      await atualizarEspecialidade.mutateAsync({
        id: especialidade.id,
        payload: { ativo: !especialidade.ativo },
      })
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível atualizar o status da especialidade.'))
    }
  }

  const handleCancel = () => {
    setEditingId(null)
    setForm(emptyForm)
    setFormError(null)
    setIsFormOpen(false)
  }

  return (
    <div className="space-y-8" data-testid="especialidades-page">
      <section className="space-y-4">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Especialidades</p>
            <h2 className="mt-2 text-3xl font-semibold text-white">
              Cadastro e gestão das especialidades da clínica
            </h2>
            <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
              Use esta tela para manter as especialidades usadas nos fluxos operacionais, com
              pesquisa, filtro de status e inativação lógica sem perder histórico.
            </p>
          </div>

          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={handleNew}
              className="rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300"
              data-testid="especialidade-novo"
            >
              Nova especialidade
            </button>
          </div>
        </div>

        <div className="grid gap-4 md:grid-cols-3">
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Total</p>
            <p className="mt-2 text-2xl font-semibold text-white">{especialidades.length}</p>
          </article>
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Ativas</p>
            <p className="mt-2 text-2xl font-semibold text-white">{totalAtivas}</p>
          </article>
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Inativas</p>
            <p className="mt-2 text-2xl font-semibold text-white">{totalInativas}</p>
          </article>
        </div>
      </section>

      {isFormOpen ? (
        <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <form onSubmit={handleSubmit} className="space-y-4" data-testid="especialidade-form">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="text-lg font-semibold text-white">
                  {editingId ? 'Editar especialidade' : 'Nova especialidade'}
                </h3>
                <p className="text-sm text-slate-300">
                  O nome precisa ser único por tenant. Inativar preserva vínculos antigos.
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
                data-testid="especialidade-nome"
              />
            </label>

            <label className="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
              <input
                type="checkbox"
                checked={form.ativo}
                onChange={(event) => setForm((current) => ({ ...current, ativo: event.target.checked }))}
                className="h-4 w-4 rounded border-white/20 bg-white/10 text-cyan-300 focus:ring-cyan-300/20"
                data-testid="especialidade-ativo"
              />
              <span className="text-sm font-medium text-slate-200">Ativa</span>
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
                disabled={criarEspecialidade.isPending || atualizarEspecialidade.isPending}
                data-testid="especialidade-submit"
              >
                {editingId
                  ? atualizarEspecialidade.isPending
                    ? 'Salvando...'
                    : 'Salvar alterações'
                  : criarEspecialidade.isPending
                    ? 'Salvando...'
                    : 'Criar especialidade'}
              </button>
              {editingId ? (
                <button
                  type="button"
                  onClick={handleCancel}
                  className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                  data-testid="especialidade-cancelar"
                >
                  Cancelar
                </button>
              ) : (
                <button
                  type="button"
                  onClick={handleCancel}
                  className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                  data-testid="especialidade-fechar"
                >
                  Fechar
                </button>
              )}
            </div>
          </form>
        </section>
      ) : null}

      <section className="space-y-4 rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h3 className="text-lg font-semibold text-white">Lista</h3>
            <p className="text-sm text-slate-300">
              Mostra especialidades do tenant com busca por nome e filtro de status.
            </p>
          </div>

          <form className="flex flex-wrap gap-3" onSubmit={handleFilterSubmit}>
            <label className="min-w-56 space-y-2">
              <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Busca</span>
              <input
                value={draftBusca}
                onChange={(event) => setDraftBusca(event.target.value)}
                className={fieldClasses()}
                placeholder="Nome da especialidade"
                data-testid="especialidade-busca"
              />
            </label>
            <label className="min-w-48 space-y-2">
              <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Status</span>
              <select
                value={draftStatusFiltro}
                onChange={(event) => setDraftStatusFiltro(event.target.value as StatusFiltro)}
                className={fieldClasses()}
                data-testid="especialidade-status-filtro"
              >
                <option value="todas">Todas</option>
                <option value="ativas">Ativas</option>
                <option value="inativas">Inativas</option>
              </select>
            </label>
            <button
              type="submit"
              className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
              data-testid="especialidade-busca-submit"
            >
              Filtrar
            </button>
          </form>
        </div>

        {especialidadesQuery.isLoading ? (
          <div className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
            Carregando especialidades...
          </div>
        ) : especialidadesQuery.isError ? (
          <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-sm text-rose-100">
            Não foi possível carregar as especialidades.
          </div>
        ) : (
          <div className="overflow-hidden rounded-3xl border border-white/10">
            <table className="w-full border-collapse text-left text-sm">
              <thead className="bg-white/5 text-xs uppercase tracking-[0.25em] text-slate-400">
                <tr>
                  <th className="px-4 py-3">Nome</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Ações</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/10 bg-slate-950/30">
                {especialidadesFiltradas.map((especialidade) => (
                  <tr key={especialidade.id} data-testid={`especialidade-row-${especialidade.id}`}>
                    <td className="px-4 py-4 text-slate-100">
                      <div className="font-medium">{especialidade.nome}</div>
                      <div className="text-xs text-slate-400">#{especialidade.id}</div>
                    </td>
                    <td className="px-4 py-4">
                      <span
                        className={`inline-flex rounded-full border px-3 py-1 text-xs font-semibold ${statusTone(
                          especialidade.ativo,
                        )}`}
                        data-testid={`especialidade-status-${especialidade.id}`}
                      >
                        {especialidade.ativo ? 'Ativa' : 'Inativa'}
                      </span>
                    </td>
                    <td className="px-4 py-4">
                      <div className="flex flex-wrap gap-2">
                        <button
                          type="button"
                          onClick={() => handleEdit(especialidade)}
                          className="rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1.5 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
                          data-testid={`especialidade-editar-${especialidade.id}`}
                        >
                          Editar
                        </button>
                        <button
                          type="button"
                          onClick={() => handleToggleAtivo(especialidade)}
                          className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/10 disabled:opacity-60"
                          disabled={atualizarEspecialidade.isPending}
                          data-testid={`especialidade-toggle-${especialidade.id}`}
                        >
                          {especialidade.ativo ? 'Inativar' : 'Reativar'}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
                {especialidadesFiltradas.length === 0 ? (
                  <tr>
                    <td colSpan={3} className="px-4 py-8 text-center text-slate-300">
                      Nenhuma especialidade encontrada.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  )
}
