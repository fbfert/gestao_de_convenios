import { useMemo, useState, type FormEvent } from 'react'
import { useMatch, useNavigate } from 'react-router-dom'
import { getHttpErrorMessage, useAtualizarMedico, useCriarMedico, useMedicos } from './useMedicos'
import type { Medico, MedicoForm } from './types'

const emptyForm: MedicoForm = {
  nome: '',
  crm: '',
  especialidade_medica: '',
  telefone: '',
  email: '',
  ativo: true,
}

function selectClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function statusTone(ativo: boolean) {
  return ativo
    ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-100'
    : 'border-rose-400/30 bg-rose-400/10 text-rose-100'
}

function toForm(medico: Medico): MedicoForm {
  return {
    nome: medico.nome,
    crm: medico.crm,
    especialidade_medica: medico.especialidade_medica,
    telefone: medico.telefone,
    email: medico.email ?? '',
    ativo: medico.ativo,
  }
}

export function MedicosPage() {
  const navigate = useNavigate()
  const isCreateRoute = useMatch('/medicos/novo') !== null
  const [busca, setBusca] = useState('')
  const [draftBusca, setDraftBusca] = useState('')
  const [editingId, setEditingId] = useState<number | null>(null)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [form, setForm] = useState<MedicoForm>(emptyForm)
  const [formError, setFormError] = useState<string | null>(null)

  const medicosQuery = useMedicos(busca)
  const criarMedico = useCriarMedico()
  const atualizarMedico = useAtualizarMedico()

  const medicos = useMemo(() => medicosQuery.data ?? [], [medicosQuery.data])
  const totalAtivos = useMemo(() => medicos.filter((medico) => medico.ativo).length, [medicos])
  const totalInativos = medicos.length - totalAtivos

  const handleFilterSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setBusca(draftBusca.trim())
  }

  const handleNew = () => {
    navigate('/medicos/novo')
    setEditingId(null)
    setForm(emptyForm)
    setFormError(null)
  }

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFormError(null)

    try {
      if (editingId) {
        await atualizarMedico.mutateAsync({
          id: editingId,
          payload: { ...form },
        })
      } else {
        await criarMedico.mutateAsync({ ...form })
      }

      setEditingId(null)
      setForm(emptyForm)
      if (isCreateRoute) {
        navigate('/medicos')
      } else {
        setIsFormOpen(false)
      }
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível salvar o médico.'))
    }
  }

  const handleEdit = (medico: Medico) => {
    setEditingId(medico.id)
    setForm(toForm(medico))
    setFormError(null)
    setIsFormOpen(true)
  }

  const handleToggleAtivo = async (medico: Medico) => {
    setFormError(null)

    try {
      await atualizarMedico.mutateAsync({
        id: medico.id,
        payload: { ativo: !medico.ativo },
      })
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível atualizar o status do médico.'))
    }
  }

  const handleCancel = () => {
    setEditingId(null)
    setForm(emptyForm)
    setFormError(null)
    if (isCreateRoute) {
      navigate('/medicos')
      return
    }

    setIsFormOpen(false)
  }

  return (
    <div className="space-y-8" data-testid="medicos-page">
      {!isCreateRoute ? (
      <section className="space-y-4">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Médicos</p>
            <h2 className="mt-2 text-3xl font-semibold text-white">
              Cadastro e referência de médicos
            </h2>
            <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
              Use esta tela para manter os médicos que solicitam o atendimento e alimentar o
              select das solicitações.
            </p>
          </div>

          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={handleNew}
              className="rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300"
              data-testid="medico-novo"
            >
              Novo
            </button>
          </div>
        </div>

        <div className="grid gap-4 md:grid-cols-3">
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Total</p>
            <p className="mt-2 text-2xl font-semibold text-white">{medicos.length}</p>
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

      {isFormOpen || isCreateRoute ? (
        <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <form onSubmit={handleSubmit} className="space-y-4" data-testid="medico-form">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="text-lg font-semibold text-white">
                  {editingId ? 'Editar médico solicitante' : 'Novo médico solicitante'}
                </h3>
                <p className="text-sm text-slate-300">
                  O CRM é livre por enquanto; o tenant vem do usuário autenticado.
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
                className={selectClasses()}
                data-testid="medico-nome"
              />
            </label>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">CRM</span>
              <input
                value={form.crm}
                onChange={(event) => setForm((current) => ({ ...current, crm: event.target.value }))}
                className={selectClasses()}
                data-testid="medico-crm"
              />
            </label>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Especialidade médica</span>
              <input
                value={form.especialidade_medica}
                onChange={(event) =>
                  setForm((current) => ({ ...current, especialidade_medica: event.target.value }))
                }
                className={selectClasses()}
                data-testid="medico-especialidade-medica"
              />
            </label>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Telefone</span>
              <input
                value={form.telefone}
                onChange={(event) =>
                  setForm((current) => ({ ...current, telefone: event.target.value }))
                }
                className={selectClasses()}
                data-testid="medico-telefone"
              />
            </label>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">E-mail</span>
              <input
                value={form.email}
                onChange={(event) =>
                  setForm((current) => ({ ...current, email: event.target.value }))
                }
                className={selectClasses()}
                data-testid="medico-email"
              />
            </label>

            <label className="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
              <input
                type="checkbox"
                checked={form.ativo}
                onChange={(event) =>
                  setForm((current) => ({ ...current, ativo: event.target.checked }))
                }
                className="h-4 w-4 rounded border-white/20 bg-white/10 text-cyan-300 focus:ring-cyan-300/20"
                data-testid="medico-ativo"
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
                disabled={criarMedico.isPending || atualizarMedico.isPending}
                data-testid="medico-submit"
              >
                {editingId
                  ? atualizarMedico.isPending
                    ? 'Salvando...'
                    : 'Salvar alterações'
                  : criarMedico.isPending
                    ? 'Salvando...'
                    : 'Criar médico'}
              </button>
              {editingId ? (
                <button
                  type="button"
                  onClick={handleCancel}
                  className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                  data-testid="medico-cancelar"
                >
                  Cancelar
                </button>
              ) : (
                <button
                  type="button"
                  onClick={handleCancel}
                  className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                  data-testid="medico-fechar"
                >
                  Fechar
                </button>
              )}
            </div>
          </form>
        </section>
      ) : null}

      {!isCreateRoute ? (
      <section className="space-y-4 rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h3 className="text-lg font-semibold text-white">Lista</h3>
            <p className="text-sm text-slate-300">
              Mostra todos os médicos solicitantes do tenant, com pesquisa por nome, CRM e
              especialidade.
            </p>
          </div>

          <form className="flex gap-3" onSubmit={handleFilterSubmit}>
            <label className="min-w-56 space-y-2">
              <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Busca</span>
              <input
                value={draftBusca}
                onChange={(event) => setDraftBusca(event.target.value)}
                className={selectClasses()}
                placeholder="Nome, CRM ou especialidade"
                data-testid="medico-busca"
              />
            </label>
            <button
              type="submit"
              className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
              data-testid="medico-busca-submit"
            >
              Filtrar
            </button>
          </form>
        </div>

        {medicosQuery.isLoading ? (
          <div className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
            Carregando médicos...
          </div>
        ) : medicosQuery.isError ? (
          <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-sm text-rose-100">
            Não foi possível carregar os médicos.
          </div>
        ) : (
          <div className="overflow-hidden rounded-3xl border border-white/10">
            <table className="w-full border-collapse text-left text-sm">
              <thead className="bg-white/5 text-xs uppercase tracking-[0.25em] text-slate-400">
                <tr>
                  <th className="px-4 py-3">Nome</th>
                  <th className="px-4 py-3">CRM</th>
                  <th className="px-4 py-3">Especialidade</th>
                  <th className="px-4 py-3">Contato</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Ações</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/10 bg-slate-950/30">
                {medicos.map((medico) => (
                  <tr key={medico.id} data-testid={`medico-row-${medico.id}`}>
                    <td className="px-4 py-4 text-slate-100">
                      <div className="font-medium">{medico.nome}</div>
                      <div className="text-xs text-slate-400">#{medico.id}</div>
                    </td>
                    <td className="px-4 py-4 text-slate-200">{medico.crm}</td>
                    <td className="px-4 py-4 text-slate-200">{medico.especialidade_medica}</td>
                    <td className="px-4 py-4 text-slate-200">
                      <div>{medico.telefone}</div>
                      <div className="text-xs text-slate-400">{medico.email ?? '-'}</div>
                    </td>
                    <td className="px-4 py-4">
                      <span
                        className={`inline-flex rounded-full border px-3 py-1 text-xs font-semibold ${statusTone(
                          medico.ativo,
                        )}`}
                        data-testid={`medico-status-${medico.id}`}
                      >
                        {medico.ativo ? 'Ativo' : 'Inativo'}
                      </span>
                    </td>
                    <td className="px-4 py-4">
                      <div className="flex flex-wrap gap-2">
                        <button
                          type="button"
                          onClick={() => handleEdit(medico)}
                          className="rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1.5 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
                          data-testid={`medico-editar-${medico.id}`}
                        >
                          Editar
                        </button>
                        <button
                          type="button"
                          onClick={() => handleToggleAtivo(medico)}
                          className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/10 disabled:opacity-60"
                          disabled={atualizarMedico.isPending}
                          data-testid={`medico-toggle-${medico.id}`}
                        >
                          {medico.ativo ? 'Desativar' : 'Ativar'}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
                {medicos.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-slate-300">
                      Nenhum médico encontrado.
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
