import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import { ColunaOrdenavel } from '../../components/ui/ColunaOrdenavel'
import { useOrdenacao } from '../../lib/useOrdenacao'
import { useMatch, useNavigate } from 'react-router-dom'
import {
  getHttpErrorMessage,
  useAtualizarEspecialidade,
  useCriarEspecialidade,
  useEspecialidadesCrud,
} from './useEspecialidades'
import { useConvenios } from '../../lib/queries/useReferenceData'
import type { Especialidade, EspecialidadeForm, EspecialidadePayload } from './types'
import { Indicadores } from '../../components/ui/Indicadores'

const emptyForm: EspecialidadeForm = {
  nome: '',
  ativo: true,
  codigos: {},
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
    codigos: Object.fromEntries(
      (especialidade.codigos ?? []).map((item) => [item.convenio_id, item.codigo]),
    ),
  }
}

/**
 * A lista vai inteira, inclusive os convênios deixados em branco: é assim que a
 * API sabe que o código foi apagado, e não apenas omitido.
 */
function toPayload(form: EspecialidadeForm, convenioIds: number[]): EspecialidadePayload {
  return {
    nome: form.nome.trim(),
    ativo: form.ativo,
    codigos: convenioIds.map((convenio_id) => ({
      convenio_id,
      codigo: (form.codigos[convenio_id] ?? '').trim(),
    })),
  }
}

export function EspecialidadesPage() {
  const navigate = useNavigate()
  const isCreateRoute = useMatch('/especialidades/nova') !== null
  const editRouteMatch = useMatch('/especialidades/:id/editar')
  const editingId = editRouteMatch ? Number(editRouteMatch.params.id) : null
  const isEditRoute = editingId !== null && Number.isInteger(editingId)
  const isFormRoute = isCreateRoute || isEditRoute
  const [busca, setBusca] = useState('')
  const [draftBusca, setDraftBusca] = useState('')
  const [statusFiltro, setStatusFiltro] = useState<StatusFiltro>('todas')
  const [draftStatusFiltro, setDraftStatusFiltro] = useState<StatusFiltro>('todas')
  const [form, setForm] = useState<EspecialidadeForm>(emptyForm)
  const [formError, setFormError] = useState<string | null>(null)
  const carregadoRef = useRef<number | null>(null)

  const { ordenacao, ordenarPor } = useOrdenacao({
    ordenar_por: 'nome',
    direcao: 'asc',
  })

  const especialidadesQuery = useEspecialidadesCrud(busca, ordenacao)
  const conveniosQuery = useConvenios()
  const convenios = useMemo(() => conveniosQuery.data ?? [], [conveniosQuery.data])
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
  const especialidadeEmEdicao = useMemo(
    () => (isEditRoute ? especialidades.find((especialidade) => especialidade.id === editingId) ?? null : null),
    [isEditRoute, editingId, especialidades],
  )

  // Carrega o registro no formulário uma única vez por id: evita que um refetch
  // em background sobrescreva o que o usuário já digitou.
  useEffect(() => {
    if (!isEditRoute) {
      carregadoRef.current = null
      return
    }

    if (!especialidadeEmEdicao || carregadoRef.current === especialidadeEmEdicao.id) {
      return
    }

    carregadoRef.current = especialidadeEmEdicao.id
    setForm(toForm(especialidadeEmEdicao))
    setFormError(null)
  }, [isEditRoute, especialidadeEmEdicao])

  const handleFilterSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setBusca(draftBusca.trim())
    setStatusFiltro(draftStatusFiltro)
  }

  const handleNew = () => {
    setForm(emptyForm)
    setFormError(null)
    carregadoRef.current = null
    navigate('/especialidades/nova')
  }

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFormError(null)

    try {
      const payload = toPayload(form, convenios.map((convenio) => convenio.id))

      if (isEditRoute && editingId) {
        await atualizarEspecialidade.mutateAsync({
          id: editingId,
          payload,
        })
      } else {
        await criarEspecialidade.mutateAsync(payload)
      }

      setForm(emptyForm)
      carregadoRef.current = null
      navigate('/especialidades')
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível salvar a especialidade.'))
    }
  }

  const handleEdit = (especialidade: Especialidade) => {
    setFormError(null)
    navigate(`/especialidades/${especialidade.id}/editar`)
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
    setForm(emptyForm)
    setFormError(null)
    carregadoRef.current = null
    navigate('/especialidades')
  }

  return (
    <div className="space-y-8" data-testid="especialidades-page">
      {!isFormRoute ? (
      <section className="space-y-4">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Especialidades</p>
            <h2 className="mt-2 text-3xl font-semibold text-white">
              Cadastro e gestão das especialidades da clínica
            </h2>
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

        <Indicadores
          itens={[
            { rotulo: 'Total', valor: especialidades.length },
            { rotulo: 'Ativas', valor: totalAtivas },
            { rotulo: 'Inativas', valor: totalInativas },
          ]}
        />
      </section>
      ) : null}

      {isFormRoute && (!isEditRoute || especialidadeEmEdicao) ? (
        <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <form onSubmit={handleSubmit} className="space-y-4" data-testid="especialidade-form">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="text-lg font-semibold text-white">
                  {editingId ? 'Editar especialidade' : 'Nova especialidade'}
                </h3>
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

            {/*
              Um campo por convênio cadastrado. Convênio novo aparece aqui
              sozinho, em todas as especialidades, porque a lista sai do próprio
              cadastro de convênios — não há nada a migrar quando surge um.
            */}
            <div className="space-y-2" data-testid="especialidade-codigos">
              <span className="text-sm font-medium text-slate-200">Código por convênio</span>

              {conveniosQuery.isLoading ? (
                <p className="text-sm text-slate-300">Carregando convênios...</p>
              ) : convenios.length === 0 ? (
                <p className="rounded-2xl border border-white/10 bg-slate-950/40 px-4 py-3 text-sm text-slate-400">
                  Nenhum convênio cadastrado ainda.
                </p>
              ) : (
                <div className="grid gap-3 md:grid-cols-2">
                  {convenios.map((convenio) => (
                    <label key={convenio.id} className="block space-y-1">
                      <span className="text-xs uppercase tracking-[0.25em] text-slate-400">
                        {convenio.nome}
                      </span>
                      <input
                        value={form.codigos[convenio.id] ?? ''}
                        onChange={(event) =>
                          setForm((current) => ({
                            ...current,
                            codigos: { ...current.codigos, [convenio.id]: event.target.value },
                          }))
                        }
                        placeholder="Sem código neste convênio"
                        className={fieldClasses()}
                        data-testid={`especialidade-codigo-${convenio.id}`}
                      />
                    </label>
                  ))}
                </div>
              )}

              <span className="block text-xs text-slate-400">
                Em branco significa que a especialidade não é atendida por aquele convênio.
              </span>
            </div>

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

      {isEditRoute && !especialidadeEmEdicao ? (
        <section
          className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6"
          data-testid="especialidade-edicao-indisponivel"
        >
          {especialidadesQuery.isLoading ? (
            <p className="text-sm text-slate-300">Carregando especialidade...</p>
          ) : (
            <div className="space-y-4">
              <p className="text-sm text-rose-100">
                Especialidade não encontrada. Ela pode ter sido removida ou o endereço está incorreto.
              </p>
              <button
                type="button"
                onClick={handleCancel}
                className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                data-testid="especialidade-voltar"
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
                  <ColunaOrdenavel
                    titulo="Nome"
                    coluna="nome"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                  <ColunaOrdenavel
                    titulo="Status"
                    coluna="status"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                  <ColunaOrdenavel titulo="Ações" />
                </tr>
              </thead>
              <tbody className="divide-y divide-white/10 bg-slate-950/30">
                {especialidadesFiltradas.map((especialidade) => (
                  <tr key={especialidade.id} data-testid={`especialidade-row-${especialidade.id}`}>
                    <td className="px-4 py-4 text-slate-100">
                      <div className="font-medium">{especialidade.nome}</div>
                      {/* Os codigos aparecem na listagem para conferir de
                          relance qual convenio ainda esta sem. */}
                      <div className="text-xs text-slate-400">
                        {(especialidade.codigos ?? []).length === 0
                          ? 'sem código de convênio'
                          : (especialidade.codigos ?? [])
                              .map(
                                (codigo) =>
                                  `${
                                    convenios.find((convenio) => convenio.id === codigo.convenio_id)
                                      ?.nome ?? `#${codigo.convenio_id}`
                                  } ${codigo.codigo}`,
                              )
                              .join(' · ')}
                      </div>
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
      ) : null}
    </div>
  )
}
