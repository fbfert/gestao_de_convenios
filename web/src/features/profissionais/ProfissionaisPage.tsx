import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import { ColunaOrdenavel } from '../../components/ui/ColunaOrdenavel'
import { useOrdenacao } from '../../lib/useOrdenacao'
import { useMatch, useNavigate, useSearchParams } from 'react-router-dom'
import { useEspecialidades } from '../../lib/queries/useReferenceData'
import { getHttpErrorMessage, useAtualizarProfissional, useCriarProfissional, useProfissionaisCrud } from './useProfissionais'
import type { Profissional, ProfissionalForm, ProfissionalPayload } from './types'
import { Indicadores } from '../../components/ui/Indicadores'
import { Botao } from '../../components/ui/Botao'
import { Badge } from '../../components/ui/Badge'

const emptyForm: ProfissionalForm = {
  nome: '',
  especialidade_id: '',
  especialidade_ids: [],
  conselho_registro: '',
  percentual_repasse: '',
  ativo: true,
}

function fieldClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function toForm(profissional: Profissional): ProfissionalForm {
  return {
    nome: profissional.nome,
    especialidade_id: String(profissional.especialidade_id),
    especialidade_ids: profissional.especialidade_ids ?? [profissional.especialidade_id],
    conselho_registro: profissional.conselho_registro ?? '',
    percentual_repasse: profissional.percentual_repasse ?? '',
    ativo: profissional.ativo,
  }
}

function toPayload(form: ProfissionalForm): ProfissionalPayload {
  return {
    nome: form.nome.trim(),
    especialidade_id: Number(form.especialidade_id),
    especialidade_ids: form.especialidade_ids,
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
  const [searchParams] = useSearchParams()
  const especialidadeSugerida = searchParams.get('especialidade_id') ?? ''
  const sugestaoAplicadaRef = useRef(false)

  /*
    Chega assim quando o cadastro e aberto pelo atalho de "Adicionar
    profissional" da solicitacao, que ja sabe qual especialidade ficou sem
    executante. Aplica uma vez so, para nao desfazer a escolha de quem trocar a
    especialidade na tela.
  */
  useEffect(() => {
    if (!isCreateRoute || !especialidadeSugerida || sugestaoAplicadaRef.current) {
      return
    }

    sugestaoAplicadaRef.current = true
    setForm((atual) => ({
      ...atual,
      especialidade_id: especialidadeSugerida,
      especialidade_ids: [Number(especialidadeSugerida)],
    }))
  }, [isCreateRoute, especialidadeSugerida])

  const especialidadesQuery = useEspecialidades()
  const { ordenacao, ordenarPor } = useOrdenacao({ ordenar_por: 'nome', direcao: 'asc' })
  const profissionaisQuery = useProfissionaisCrud({
    busca,
    especialidade_id: especialidadeFiltro,
    incluir_inativos: true,
    ...ordenacao,
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
          </div>

          <div className="flex items-center gap-3">
            <Botao variante="primario" onClick={handleNew} data-testid="profissional-novo">
              Novo
            </Botao>
          </div>
        </div>

        <Indicadores
          itens={[
            { rotulo: 'Total', valor: profissionais.length },
            { rotulo: 'Ativos', valor: totalAtivos },
            { rotulo: 'Inativos', valor: totalInativos },
          ]}
        />
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
              <span className="block text-xs text-slate-400">
                A de registro no conselho. Ela entra automaticamente na lista abaixo.
              </span>
            </label>

            <div className="space-y-2">
              <span className="text-sm font-medium text-slate-200">Atende também em</span>
              <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                {especialidades.map((especialidade) => {
                  const ehPrincipal = String(especialidade.id) === form.especialidade_id
                  const marcada = ehPrincipal || form.especialidade_ids.includes(especialidade.id)

                  return (
                    <label
                      key={especialidade.id}
                      className={`flex items-center gap-2 rounded-2xl border px-3 py-2 text-sm transition ${
                        marcada
                          ? 'border-cyan-300/40 bg-cyan-400/10 text-cyan-50'
                          : 'border-white/10 bg-white/5 text-slate-200'
                      }`}
                    >
                      <input
                        type="checkbox"
                        checked={marcada}
                        /* A principal fica travada marcada: desmarca-la criaria
                           um profissional que nao atende na propria
                           especialidade de registro. O backend reforca isso. */
                        disabled={ehPrincipal}
                        onChange={(event) =>
                          setForm((current) => ({
                            ...current,
                            especialidade_ids: event.target.checked
                              ? [...current.especialidade_ids, especialidade.id]
                              : current.especialidade_ids.filter((id) => id !== especialidade.id),
                          }))
                        }
                        className="size-4 rounded border-white/20 bg-white/10 disabled:opacity-60"
                        data-testid={`profissional-especialidade-${especialidade.id}`}
                      />
                      {especialidade.nome}
                      {ehPrincipal ? <span className="text-xs text-cyan-200">principal</span> : null}
                    </label>
                  )
                })}
              </div>
            </div>

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
              <Botao
                type="submit"
                variante="primario"
                className="flex-1"
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
              </Botao>
              {editingId ? (
                <Botao
                  type="button"
                  variante="secundario"
                  onClick={handleCancel}
                  data-testid="profissional-cancelar"
                >
                  Cancelar
                </Botao>
              ) : (
                <Botao
                  type="button"
                  variante="secundario"
                  onClick={handleCancel}
                  data-testid="profissional-fechar"
                >
                  Fechar
                </Botao>
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
              <Botao type="button" variante="secundario" onClick={handleCancel} data-testid="profissional-voltar">
                Voltar para a lista
              </Botao>
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
            <Botao type="submit" variante="secundario" data-testid="profissional-busca-submit">
              Filtrar
            </Botao>
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
          <div className="overflow-x-auto rounded-3xl border border-white/10">
            <table className="w-full border-collapse text-left text-sm">
              <thead className="bg-white/5 text-xs uppercase tracking-[0.25em] text-slate-400">
                <tr>
                  <ColunaOrdenavel
                    titulo="Nome"
                    coluna="nome"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                    className="w-full px-4 py-3"
                  />
                  <ColunaOrdenavel titulo="Especialidade" />
                  <ColunaOrdenavel
                    titulo="Conselho"
                    coluna="conselho"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                  <ColunaOrdenavel
                    titulo="Repasse"
                    coluna="repasse"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                  <ColunaOrdenavel
                    titulo="Status"
                    coluna="status"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                  <ColunaOrdenavel titulo="Ações" className="w-px px-4 py-3" />
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
                      <Badge
                        tone={profissional.ativo ? 'sucesso' : 'perigo'}
                        data-testid={`profissional-status-${profissional.id}`}
                      >
                        {profissional.ativo ? 'Ativo' : 'Inativo'}
                      </Badge>
                    </td>
                    <td className="w-px whitespace-nowrap px-4 py-4">
                      <div className="flex flex-nowrap gap-2">
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
