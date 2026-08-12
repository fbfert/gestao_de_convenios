import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useMatch, useNavigate } from 'react-router-dom'
import { useConvenios } from '../../lib/queries/useReferenceData'
import { Select } from '../../components/ui/Select'
import { getHttpErrorMessage, useAtualizarPaciente, useCriarPaciente, usePacientesCrud } from './usePacientes'
import {
  formatCarteirinha,
  isCarteirinhaCompleta,
  joinCarteirinha,
  splitCarteirinha,
} from '../../lib/carteirinha'
import { CarteirinhaBlocosInput } from '../../components/ui/CarteirinhaBlocosInput'
import type { Paciente, PacienteForm } from './types'

const emptyForm: PacienteForm = {
  nome: '',
  cpf: '',
  carteirinha: '',
  convenio_id: '',
  telefone: '',
  clinica_agil_id: '',
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

function toForm(paciente: Paciente): PacienteForm {
  return {
    nome: paciente.nome,
    cpf: paciente.cpf ?? '',
    carteirinha: paciente.carteirinha,
    convenio_id: String(paciente.convenio_id),
    telefone: paciente.telefone ?? '',
    clinica_agil_id: paciente.clinica_agil_id ?? '',
    ativo: paciente.ativo,
  }
}

export function PacientesPage() {
  const navigate = useNavigate()
  const isCreateRoute = useMatch('/pacientes/novo') !== null
  const [busca, setBusca] = useState('')
  const [draftBusca, setDraftBusca] = useState('')
  const [editingId, setEditingId] = useState<number | null>(null)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [form, setForm] = useState<PacienteForm>(emptyForm)
  // Os blocos vivem em estado próprio: derivá-los de form.carteirinha fazia os dígitos
  // migrarem de bloco assim que um bloco anterior ficava incompleto.
  const [blocosDigitados, setBlocosDigitados] = useState<string[]>([])
  const [formError, setFormError] = useState<string | null>(null)

  const pacientesQuery = usePacientesCrud(busca)
  const conveniosQuery = useConvenios()
  const criarPaciente = useCriarPaciente()
  const atualizarPaciente = useAtualizarPaciente()

  const pacientes = useMemo(() => pacientesQuery.data ?? [], [pacientesQuery.data])
  const convenios = useMemo(() => conveniosQuery.data ?? [], [conveniosQuery.data])
  const selectedConvenio = useMemo(
    () => convenios.find((convenio) => String(convenio.id) === form.convenio_id),
    [convenios, form.convenio_id],
  )
  // O formato vem do convenio, nao do driver de automacao: ver a migration
  // 2026_08_12_200000. `null` significa carteirinha em texto livre.
  const blocos = selectedConvenio?.carteirinha_blocos ?? null
  const totalAtivos = pacientes.filter((paciente) => paciente.ativo).length
  const totalInativos = pacientes.length - totalAtivos

  useEffect(() => {
    if (convenios.length === 0) {
      return
    }

    setForm((current) =>
      current.convenio_id ? current : { ...current, convenio_id: String(convenios[0].id) },
    )
  }, [convenios])

  // Trocar para um convenio com formato reaproveita o que ja estava digitado
  // no campo unico.
  useEffect(() => {
    if (!blocos) {
      return
    }

    setBlocosDigitados((current) =>
      joinCarteirinha(current) === form.carteirinha.replace(/\D/g, '')
        ? current
        : splitCarteirinha(form.carteirinha, blocos),
    )
  }, [blocos, form.carteirinha])

  const formIsComplete =
    form.nome.trim() !== '' &&
    (blocos
      ? isCarteirinhaCompleta(blocosDigitados, blocos)
      : form.carteirinha.trim() !== '') &&
    form.convenio_id !== '' &&
    conveniosQuery.isSuccess

  const handleFilterSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setBusca(draftBusca.trim())
  }

  const handleNew = () => {
    navigate('/pacientes/novo')
    setEditingId(null)
    setForm((current) => ({
      ...emptyForm,
      convenio_id: current.convenio_id,
    }))
    setBlocosDigitados([])
    setFormError(null)
  }

  const handleEdit = (paciente: Paciente) => {
    setEditingId(paciente.id)
    setForm(toForm(paciente))
    setBlocosDigitados(
      paciente.convenio?.carteirinha_blocos
        ? splitCarteirinha(paciente.carteirinha, paciente.convenio.carteirinha_blocos)
        : [],
    )
    setFormError(null)
    setIsFormOpen(true)
  }

  const handleToggleAtivo = async (paciente: Paciente) => {
    setFormError(null)

    try {
      await atualizarPaciente.mutateAsync({
        id: paciente.id,
        payload: { ativo: !paciente.ativo },
      })
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível atualizar o status do paciente.'))
    }
  }

  const handleCancel = () => {
    setEditingId(null)
    setForm(emptyForm)
    setBlocosDigitados([])
    setFormError(null)
    if (isCreateRoute) {
      navigate('/pacientes')
      return
    }

    setIsFormOpen(false)
  }

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFormError(null)

    try {
      if (editingId) {
        const payload: Partial<PacienteForm> = {
          nome: form.nome,
          cpf: form.cpf,
          carteirinha: form.carteirinha,
          convenio_id: form.convenio_id,
          telefone: form.telefone,
          clinica_agil_id: form.clinica_agil_id,
          ativo: form.ativo,
        }

        await atualizarPaciente.mutateAsync({
          id: editingId,
          payload,
        })
      } else {
        await criarPaciente.mutateAsync(form)
      }

      setEditingId(null)
      setForm((current) => ({
        ...emptyForm,
        convenio_id: current.convenio_id,
      }))
      if (isCreateRoute) {
        navigate('/pacientes')
      } else {
        setIsFormOpen(false)
      }
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível salvar o paciente.'))
    }
  }

  return (
    <div className="space-y-8" data-testid="pacientes-page">
      {!isCreateRoute ? (
      <section className="space-y-4">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Pacientes</p>
            <h2 className="mt-2 text-3xl font-semibold text-white">Cadastro e referência de pacientes</h2>
            <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
              Mantenha pacientes do tenant e use a mesma base que alimenta Solicitações, Guias e Antecipações.
            </p>
          </div>

          <button
            type="button"
            onClick={handleNew}
            className="rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300"
            data-testid="paciente-novo"
          >
            Novo
          </button>
        </div>

        <div className="grid gap-4 md:grid-cols-3">
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Total</p>
            <p className="mt-2 text-2xl font-semibold text-white">{pacientes.length}</p>
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
          <form onSubmit={handleSubmit} className="space-y-4" data-testid="paciente-form">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="text-lg font-semibold text-white">
                  {editingId ? 'Editar paciente' : 'Novo paciente'}
                </h3>
                <p className="text-sm text-slate-300">O convênio vem da API de referência do tenant.</p>
              </div>
              {editingId ? (
                <span className="rounded-full border border-cyan-300/20 bg-cyan-400/10 px-3 py-1 text-xs font-semibold text-cyan-100">
                  Editando #{editingId}
                </span>
              ) : null}
            </div>

            {conveniosQuery.isLoading ? (
              <div className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
                Carregando convênios...
              </div>
            ) : null}

            {conveniosQuery.isError ? (
              <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-sm text-rose-100">
                Não foi possível carregar os convênios.
              </div>
            ) : null}

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Nome</span>
              <input
                value={form.nome}
                onChange={(event) => setForm((current) => ({ ...current, nome: event.target.value }))}
                className={selectClasses()}
                data-testid="paciente-nome"
              />
            </label>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">CPF</span>
              <input
                value={form.cpf}
                onChange={(event) => setForm((current) => ({ ...current, cpf: event.target.value }))}
                className={selectClasses()}
                data-testid="paciente-cpf"
              />
            </label>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Convênio</span>
              <Select
                value={form.convenio_id}
                onChange={(event) =>
                  setForm((current) => ({ ...current, convenio_id: event.target.value }))
                }
                className={selectClasses()}
                data-testid="paciente-convenio"
                disabled={conveniosQuery.isLoading || convenios.length === 0}
              >
                <option value="" disabled>
                  Selecione
                </option>
                {convenios.map((convenio) => (
                  <option key={convenio.id} value={convenio.id}>
                    {convenio.nome}
                  </option>
                ))}
              </Select>
            </label>

            {blocos ? (
              <div className="space-y-2">
                <span className="text-sm font-medium text-slate-200">Carteirinha</span>
                <CarteirinhaBlocosInput
                  blocos={blocos}
                  blocks={blocosDigitados}
                  onChange={(blocks, carteirinha) => {
                    setBlocosDigitados(blocks)
                    setForm((current) => ({ ...current, carteirinha }))
                  }}
                  testIdPrefix="paciente-carteirinha-blocos"
                />
              </div>
            ) : (
              <label className="block space-y-2">
                <span className="text-sm font-medium text-slate-200">Carteirinha</span>
                <input
                  value={form.carteirinha}
                  onChange={(event) =>
                    setForm((current) => ({ ...current, carteirinha: event.target.value }))
                  }
                  className={selectClasses()}
                  data-testid="paciente-carteirinha"
                />
              </label>
            )}

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Telefone</span>
              <input
                value={form.telefone}
                onChange={(event) =>
                  setForm((current) => ({ ...current, telefone: event.target.value }))
                }
                className={selectClasses()}
                data-testid="paciente-telefone"
              />
            </label>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">ID Clínica Ágil</span>
              <input
                value={form.clinica_agil_id}
                onChange={(event) =>
                  setForm((current) => ({ ...current, clinica_agil_id: event.target.value }))
                }
                className={selectClasses()}
                data-testid="paciente-clinica-agil-id"
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
                data-testid="paciente-ativo"
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
                disabled={
                  criarPaciente.isPending ||
                  atualizarPaciente.isPending ||
                  !formIsComplete ||
                  conveniosQuery.isLoading
                }
                data-testid="paciente-submit"
              >
                {editingId
                  ? atualizarPaciente.isPending
                    ? 'Salvando...'
                    : 'Salvar alterações'
                  : criarPaciente.isPending
                    ? 'Salvando...'
                    : 'Criar paciente'}
              </button>
              <button
                type="button"
                onClick={handleCancel}
                className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                data-testid={editingId ? 'paciente-cancelar' : 'paciente-fechar'}
              >
                {editingId ? 'Cancelar' : 'Fechar'}
              </button>
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
              Pesquisa por nome ou carteirinha, com convênio e status visíveis na mesma tela.
            </p>
          </div>

          <form className="flex gap-3" onSubmit={handleFilterSubmit}>
            <label className="min-w-56 space-y-2">
              <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Busca</span>
              <input
                value={draftBusca}
                onChange={(event) => setDraftBusca(event.target.value)}
                className={selectClasses()}
                placeholder="Nome ou carteirinha"
                data-testid="paciente-busca"
              />
            </label>
            <button
              type="submit"
              className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
              data-testid="paciente-busca-submit"
            >
              Filtrar
            </button>
          </form>
        </div>

        {pacientesQuery.isLoading ? (
          <div className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
            Carregando pacientes...
          </div>
        ) : pacientesQuery.isError ? (
          <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-sm text-rose-100">
            Não foi possível carregar os pacientes.
          </div>
        ) : (
          <div className="overflow-hidden rounded-3xl border border-white/10">
            <table className="w-full border-collapse text-left text-sm">
              <thead className="bg-white/5 text-xs uppercase tracking-[0.25em] text-slate-400">
                <tr>
                  <th className="px-4 py-3">Nome</th>
                  <th className="px-4 py-3">Carteirinha</th>
                  <th className="px-4 py-3">Convênio</th>
                  <th className="px-4 py-3">Contato</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Ações</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/10 bg-slate-950/30">
                {pacientes.map((paciente) => (
                  <tr key={paciente.id} data-testid={`paciente-row-${paciente.id}`}>
                    <td className="px-4 py-4 text-slate-100">
                      <div className="font-medium">{paciente.nome}</div>
                      <div className="text-xs text-slate-400">#{paciente.id}</div>
                    </td>
                    <td className="px-4 py-4 tabular-nums text-slate-200">
                      {formatCarteirinha(paciente.carteirinha, paciente.convenio?.carteirinha_blocos ?? undefined)}
                    </td>
                    <td className="px-4 py-4 text-slate-200">
                      {paciente.convenio?.nome ?? paciente.convenio_id}
                    </td>
                    <td className="px-4 py-4 text-slate-200">
                      <div>{paciente.telefone ?? '-'}</div>
                      <div className="text-xs text-slate-400">{paciente.cpf ?? '-'}</div>
                    </td>
                    <td className="px-4 py-4">
                      <span
                        className={`inline-flex rounded-full border px-3 py-1 text-xs font-semibold ${statusTone(
                          paciente.ativo,
                        )}`}
                        data-testid={`paciente-status-${paciente.id}`}
                      >
                        {paciente.ativo ? 'Ativo' : 'Inativo'}
                      </span>
                    </td>
                    <td className="px-4 py-4">
                      <div className="flex flex-wrap gap-2">
                        <button
                          type="button"
                          onClick={() => handleEdit(paciente)}
                          className="rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1.5 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
                          data-testid={`paciente-editar-${paciente.id}`}
                        >
                          Editar
                        </button>
                        <button
                          type="button"
                          onClick={() => handleToggleAtivo(paciente)}
                          className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/10 disabled:opacity-60"
                          disabled={atualizarPaciente.isPending}
                          data-testid={`paciente-toggle-${paciente.id}`}
                        >
                          {paciente.ativo ? 'Desativar' : 'Ativar'}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
                {pacientes.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-slate-300">
                      Nenhum paciente encontrado.
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
