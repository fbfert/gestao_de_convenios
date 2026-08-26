import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { ColunaOrdenavel } from '../../components/ui/ColunaOrdenavel'
import { useOrdenacao } from '../../lib/useOrdenacao'
import { Link, useMatch, useNavigate } from 'react-router-dom'
import { Badge } from '../../components/ui/Badge'
import { Botao } from '../../components/ui/Botao'
import { Select } from '../../components/ui/Select'
import { translateStatus } from '../../lib/statusLabels'
import { formatCarteirinha } from '../../lib/carteirinha'
import { useConvenios, useEspecialidades, usePacientes, useProfissionais } from '../../lib/queries/useReferenceData'
import {
  getHttpErrorMessage,
  useConsultarGuiaUnimed,
  useGerarConciliacao,
  useCriarGuia,
  useGuias,
} from './useGuias'
import type { GuiaFilters, GuiaForm } from './types'
import { GuiaStatusActions } from './GuiaStatusActions'
import { statusTone } from './statusTone'
import { SENHA_VENCENDO_EM_DIAS } from './senhaValidade'
import { Indicadores } from '../../components/ui/Indicadores'
import { Tooltip, iconeLupa } from '../../components/ui/Tooltip'
import { usePode } from '../../lib/permissoes'
import { useConfirm } from '../../components/ui/ConfirmDialog'
import { DropdownMenu } from '../../components/ui/DropdownMenu'
import { AutomacaoProgressoModal } from '../automacoes/AutomacaoProgressoModal'

const defaultFilters: GuiaFilters = {
  status: '',
  convenio_id: '',
  paciente_id: '',
  validade_senha_vencendo_em_dias: '',
}

const emptyForm: GuiaForm = {
  solicitacao_id: '',
  convenio_id: '',
  paciente_id: '',
  profissional_id: '',
  especialidade_id: '',
  numero_guia: `GUIA-${Date.now()}`,
  tipo_terapia: 'especializada',
  data_solicitacao: new Date().toISOString().slice(0, 10),
}

function selectClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

export function GuiasPage() {
  const pode = usePode()
  const navigate = useNavigate()
  const isCreateRoute = useMatch('/guias/nova') !== null
  const [filters, setFilters] = useState(defaultFilters)
  const [draftFilters, setDraftFilters] = useState(defaultFilters)
  const [page, setPage] = useState(1)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [form, setForm] = useState<GuiaForm>(emptyForm)
  const [formError, setFormError] = useState<string | null>(null)
  const [conciliacaoError, setConciliacaoError] = useState<string | null>(null)
  const [progressoExecucaoId, setProgressoExecucaoId] = useState<number | null>(null)

  const { ordenacao, ordenarPor } = useOrdenacao({
    ordenar_por: 'numero_guia',
    direcao: 'desc',
  })

  const conveniosQuery = useConvenios()
  const especialidadesQuery = useEspecialidades()
  const pacientesQuery = usePacientes({ convenio_id: form.convenio_id })
  const profissionaisQuery = useProfissionais({ especialidade_id: form.especialidade_id })
  const guiasQuery = useGuias({ ...filters, ...ordenacao }, page)
  const criarGuia = useCriarGuia()
  const gerarConciliacao = useGerarConciliacao()
  const consultarGuiaUnimed = useConsultarGuiaUnimed()
  const confirmar = useConfirm()

  const convenios = useMemo(() => conveniosQuery.data ?? [], [conveniosQuery.data])
  const especialidades = useMemo(() => especialidadesQuery.data ?? [], [especialidadesQuery.data])
  const pacientes = useMemo(() => pacientesQuery.data ?? [], [pacientesQuery.data])
  const profissionais = useMemo(() => profissionaisQuery.data ?? [], [profissionaisQuery.data])
  const guias = guiasQuery.data?.data ?? []
  const totalPages = guiasQuery.data?.meta?.last_page ?? 1

  const formIsReady =
    convenios.length > 0 &&
    especialidades.length > 0 &&
    pacientes.length > 0 &&
    profissionais.length > 0 &&
    form.convenio_id !== '' &&
    form.paciente_id !== '' &&
    form.profissional_id !== '' &&
    form.especialidade_id !== ''

  useEffect(() => {
    if (convenios.length === 0 || especialidades.length === 0) {
      return
    }

    setForm((current) => ({
      ...current,
      convenio_id: current.convenio_id || String(convenios[0].id),
      especialidade_id: current.especialidade_id || String(especialidades[0].id),
    }))
  }, [convenios, especialidades])

  useEffect(() => {
    if (pacientes.length === 0) {
      return
    }

    setForm((current) => {
      const hasSelected = pacientes.some((paciente) => String(paciente.id) === current.paciente_id)
      return hasSelected ? current : { ...current, paciente_id: String(pacientes[0].id) }
    })
  }, [pacientes])

  useEffect(() => {
    if (profissionais.length === 0) {
      return
    }

    setForm((current) => {
      const hasSelected = profissionais.some(
        (profissional) => String(profissional.id) === current.profissional_id,
      )
      return hasSelected ? current : { ...current, profissional_id: String(profissionais[0].id) }
    })
  }, [profissionais])

  const handleFilterSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setPage(1)
    setFilters(draftFilters)
  }

  const handleNew = () => {
    navigate('/guias/nova')
    setForm(emptyForm)
    setFormError(null)
  }

  const handleCreateSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFormError(null)

    try {
      await criarGuia.mutateAsync(form)
      setForm((current) => ({
        ...emptyForm,
        convenio_id: current.convenio_id,
        especialidade_id: current.especialidade_id,
        paciente_id: current.paciente_id,
        profissional_id: current.profissional_id,
      }))
      if (isCreateRoute) {
        navigate('/guias')
      } else {
        setIsFormOpen(false)
      }
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível criar a guia.'))
    }
  }

  const toggleVencendoBadge = () => {
    const threshold = String(SENHA_VENCENDO_EM_DIAS)
    const nextValue = draftFilters.validade_senha_vencendo_em_dias === threshold ? '' : threshold
    setPage(1)
    setFilters((current) => ({
      ...current,
      validade_senha_vencendo_em_dias: nextValue,
    }))
    setDraftFilters((current) => ({
      ...current,
      validade_senha_vencendo_em_dias: nextValue,
    }))
  }

  const handleGerarConciliacao = async (guideId: number) => {
    setConciliacaoError(null)

    const ok = await confirmar({
      titulo: 'Gerar conciliação',
      descricao: 'Cria a linha de fechamento financeiro desta guia na tela de Conciliação. Confirma?',
      confirmarTexto: 'Gerar conciliação',
      variante: 'primario',
    })

    if (!ok) {
      return
    }

    try {
      await gerarConciliacao.mutateAsync(guideId)
    } catch (error) {
      setConciliacaoError(getHttpErrorMessage(error, 'Não foi possível gerar a conciliação.'))
    }
  }

  const handleVerificarStatus = async (guiaId: number) => {
    const ok = await confirmar({
      titulo: 'Verificar status na Unimed',
      descricao: 'Consulta o status atual desta guia no portal da Unimed agora, fora do agendamento automático. Confirma?',
      confirmarTexto: 'Verificar status',
      variante: 'primario',
    })

    if (!ok) {
      return
    }

    try {
      const execucao = await consultarGuiaUnimed.mutateAsync(guiaId)
      setProgressoExecucaoId(execucao.id)
    } catch (error) {
      window.alert(getHttpErrorMessage(error, 'Não foi possível consultar a Unimed.'))
    }
  }

  return (
    <div className="space-y-8" data-testid="guias-page">
      {!isCreateRoute ? (
      <section className="space-y-4">
        <div className="flex flex-col gap-4 sm:items-start lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Guias</p>
            <h2 className="mt-2 flex items-center gap-2 text-display font-semibold text-white">
              Controle da guia e do prazo de senha
              <Tooltip rotulo="O que é uma guia">
                <p className="font-semibold text-white">Documento de autorização</p>
                <p className="mt-1">
                  A guia é o documento oficial que o convênio devolve autorizando o tratamento, com
                  senha e prazo de validade. Ela nasce a partir de uma Solicitação aprovada; ao ser
                  &quot;Finalizada&quot; (senha preenchida), gera automaticamente uma Antecipação.
                  &quot;Senha&quot; aqui é o código de autorização do convênio — não é senha de login.
                </p>
              </Tooltip>
            </h2>
          </div>

          <Botao variante="primario" onClick={handleNew} data-testid="guia-novo">
            Novo
          </Botao>
        </div>

        {/* O contador vira linha compacta; o filtro de validade continua sendo
            um botao, porque ele age, nao so informa. */}
        <div className="flex flex-wrap items-center gap-2">
          <Indicadores
            itens={[
              { rotulo: 'Total na página', valor: guias.length },
              { rotulo: 'Status ativo', valor: filters.status || 'Todos' },
              {
                rotulo: 'Convênio',
                valor:
                  convenios.find((item) => String(item.id) === filters.convenio_id)?.nome ?? 'Todos',
              },
            ]}
          />

          <span className="inline-flex items-center gap-2">
            <button
              type="button"
              onClick={toggleVencendoBadge}
              className={[
                'inline-flex rounded-full border px-3 py-1.5 text-corpo font-semibold transition',
                filters.validade_senha_vencendo_em_dias === String(SENHA_VENCENDO_EM_DIAS)
                  ? 'border-cyan-200/50 bg-cyan-300/20 text-white'
                  : 'border-cyan-200/20 bg-white/5 text-cyan-50 hover:bg-white/10',
              ].join(' ')}
            >
              {filters.validade_senha_vencendo_em_dias === String(SENHA_VENCENDO_EM_DIAS)
                ? `Vencendo em ${SENHA_VENCENDO_EM_DIAS} dias`
                : `Mostrar vencendo em ${SENHA_VENCENDO_EM_DIAS} dias`}
            </button>
          </span>
        </div>
      </section>
      ) : null}

      {isFormOpen || isCreateRoute ? (
        <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
          <form onSubmit={handleCreateSubmit} className="space-y-4">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="text-subtitulo font-semibold text-white">Nova guia</h3>
              </div>
              <Botao
                variante="secundario"
                onClick={() => {
                  if (isCreateRoute) {
                    navigate('/guias')
                    return
                  }

                  setIsFormOpen(false)
                }}
                data-testid="guia-fechar"
              >
                Fechar
              </Botao>
            </div>

            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Convênio</span>
              <Select
                value={form.convenio_id}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    convenio_id: event.target.value,
                    paciente_id: '',
                  }))
                }
                className={selectClasses()}
                data-testid="guia-convenio"
              >
                {convenios.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.nome}
                  </option>
                ))}
              </Select>
            </label>

            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Paciente</span>
              <Select
                value={form.paciente_id}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    paciente_id: event.target.value,
                  }))
                }
                className={selectClasses()}
                data-testid="guia-paciente"
              >
                {pacientes.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.nome} · {formatCarteirinha(item.carteirinha, item.convenio?.carteirinha_blocos ?? undefined)}
                  </option>
                ))}
              </Select>
            </label>

            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Profissional executante</span>
              <Select
                value={form.profissional_id}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    profissional_id: event.target.value,
                  }))
                }
                className={selectClasses()}
                data-testid="guia-profissional"
              >
                {profissionais.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.nome}
                  </option>
                ))}
              </Select>
            </label>

            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Especialidade</span>
              <Select
                value={form.especialidade_id}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    especialidade_id: event.target.value,
                    profissional_id: '',
                  }))
                }
                className={selectClasses()}
                data-testid="guia-especialidade"
              >
                {especialidades.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.nome}
                  </option>
                ))}
              </Select>
            </label>

            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Número da guia</span>
              <input
                value={form.numero_guia}
                onChange={(event) => setForm((current) => ({ ...current, numero_guia: event.target.value }))}
                className={selectClasses()}
                data-testid="guia-numero"
              />
            </label>

            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Tipo de terapia</span>
              <Select
                value={form.tipo_terapia}
                onChange={(event) => setForm((current) => ({ ...current, tipo_terapia: event.target.value }))}
                className={selectClasses()}
                data-testid="guia-tipo-terapia"
              >
                <option value="especializada">Especializada</option>
                <option value="convencional">Convencional</option>
              </Select>
            </label>

            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Data da solicitação</span>
              <input
                type="date"
                value={form.data_solicitacao}
                onChange={(event) => setForm((current) => ({ ...current, data_solicitacao: event.target.value }))}
                className={selectClasses()}
                data-testid="guia-data-solicitacao"
              />
            </label>

            {formError ? (
              <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
                {formError}
              </p>
            ) : null}

            <Botao
              type="submit"
              variante="primario"
              className="w-full"
              disabled={criarGuia.isPending || !formIsReady}
              data-testid="guia-submit"
            >
              {criarGuia.isPending ? 'Salvando...' : 'Criar guia'}
            </Botao>
          </form>
        </section>
      ) : null}

      {!isCreateRoute ? (
      <section className="space-y-4 rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
        <form className="flex flex-wrap items-center gap-3" onSubmit={handleFilterSubmit}>
          <label className="flex flex-col gap-2 sm:flex-row sm:items-center">
            <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Status</span>
            <div className="w-full sm:w-44">
              <Select
                value={draftFilters.status}
                onChange={(event) =>
                  setDraftFilters((current) => ({ ...current, status: event.target.value }))
                }
                className={selectClasses()}
                data-testid="guia-filtro-status"
              >
                <option value="">Todos</option>
                <option value="registered">Cadastrado</option>
                <option value="under_review">Em análise</option>
                <option value="approved">Aprovado</option>
                <option value="finalized">Aprovado</option>
                <option value="needs_verification">Verificar Restrição</option>
                <option value="canceled">Cancelado</option>
                <option value="denied">Negado</option>
                <option value="expired">Vencido</option>
              </Select>
            </div>
          </label>

          <label className="flex flex-col gap-2 sm:flex-row sm:items-center">
            <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Convênio</span>
            <div className="w-full sm:w-44">
              <Select
                value={draftFilters.convenio_id}
                onChange={(event) =>
                  setDraftFilters((current) => ({
                    ...current,
                    convenio_id: event.target.value,
                  }))
                }
                className={selectClasses()}
                data-testid="guia-filtro-convenio"
              >
                <option value="">Todos</option>
                {convenios.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.nome}
                  </option>
                ))}
              </Select>
            </div>
          </label>

          <label className="flex flex-col gap-2 sm:flex-row sm:items-center">
            <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Paciente</span>
            <div className="w-full sm:w-52">
              <Select
                value={draftFilters.paciente_id}
                onChange={(event) =>
                  setDraftFilters((current) => ({ ...current, paciente_id: event.target.value }))
                }
                className={selectClasses()}
                data-testid="guia-filtro-paciente"
              >
                <option value="">Todos</option>
                {pacientes.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.nome}
                  </option>
                ))}
              </Select>
            </div>
          </label>

          <Botao type="submit" variante="secundario">
            Aplicar
          </Botao>
        </form>

        {guiasQuery.isLoading ? (
          <div className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1 text-corpo text-slate-300">
            Carregando guias...
          </div>
        ) : guiasQuery.isError ? (
          <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-corpo text-rose-100">
            Não foi possível carregar a lista.
          </div>
        ) : (
          <div className="space-y-4">
            {conciliacaoError ? (
              <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
                {conciliacaoError}
              </p>
            ) : null}
            {/* Com as colunas de sessões separadas a tabela não cabe mais em telas médias:
                rola na horizontal em vez de cortar Senha, Validade e Ações. */}
            <div className="overflow-x-auto rounded-superficie border border-linha">
              <table className="w-full border-collapse text-left text-corpo" data-cartoes="lg">
                <thead className="bg-fundo text-meta uppercase tracking-[0.25em] text-texto-suave">
                  <tr>
                    <ColunaOrdenavel
                    titulo="Nº Guia"
                    coluna="numero_guia"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                    <ColunaOrdenavel
                    titulo="Paciente"
                    coluna="paciente"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                    className="w-full px-4 py-3"
                  />
                    <ColunaOrdenavel titulo="Carteirinha" />
                    <ColunaOrdenavel
                    titulo="Especialidade"
                    coluna="especialidade"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                    <ColunaOrdenavel
                    titulo="Profissional"
                    coluna="profissional"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                    <ColunaOrdenavel
                    titulo="Status"
                    coluna="status"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                    <ColunaOrdenavel
                    titulo="Nº de Sessões"
                    coluna="sessoes_solicitadas"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                    <ColunaOrdenavel
                    titulo="Sessões Autorizadas"
                    coluna="sessoes_autorizadas"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                    <ColunaOrdenavel
                    titulo="Senha"
                    coluna="senha"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                    <ColunaOrdenavel
                    titulo="Validade"
                    coluna="validade"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                    <ColunaOrdenavel titulo="Última consulta" />
                    <ColunaOrdenavel titulo="Ações" className="w-px px-4 py-3" />
                  </tr>
                </thead>
                <tbody className="divide-y divide-linha bg-superficie">
                  {guias.map((guia) => (
                    <tr key={guia.id} data-testid={`guia-row-${guia.id}`}>
                      <td data-rotulo="Nº Guia" className="px-4 py-4 font-medium text-white">
                        <Link to={`/guias/${guia.id}`} className="inline-flex min-h-6 items-center font-semibold text-texto decoration-acento/60 underline-offset-4 transition hover:underline hover:text-acento-intenso">
                          {guia.numero_guia ?? 'Aguardando número'}
                        </Link>
                      </td>
                        <td data-rotulo="Paciente" className="px-4 py-4 text-slate-200">
                          {guia.paciente?.nome ??
                            pacientes.find((item) => item.id === guia.paciente_id)?.nome ??
                            guia.paciente_id}
                        </td>
                        <td data-rotulo="Carteirinha" className="px-4 py-4 tabular-nums text-slate-200">
                          {formatCarteirinha(guia.paciente?.carteirinha) || '-'}
                        </td>
                        <td data-rotulo="Especialidade" className="px-4 py-4 text-slate-200">
                          {guia.especialidade?.nome ?? guia.especialidade_id}
                        </td>
                        <td data-rotulo="Profissional" className="px-4 py-4 text-slate-200">
                          {guia.profissional?.nome ?? guia.profissional_id}
                        </td>
                        <td data-rotulo="Status" data-rotulo-bloco className="px-4 py-4">
                          <div className="flex flex-col gap-2">
                            <Badge tone={statusTone(guia.status)} data-testid={`guia-status-${guia.id}`}>
                              {translateStatus('guias', guia.status)}
                            </Badge>
                            {guia.status === 'under_review' &&
                            guia.convenio?.connector_driver === 'unimed_rda' &&
                            guia.numero_guia ? (
                              <button
                                type="button"
                                onClick={() => handleVerificarStatus(guia.id)}
                                disabled={consultarGuiaUnimed.isPending}
                                className="inline-flex w-fit whitespace-nowrap rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1 text-meta font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:opacity-50"
                                data-testid={`guia-verificar-status-${guia.id}`}
                              >
                                Verificar status
                              </button>
                            ) : guia.automacao_execucao ? (
                              <span className="inline-flex w-fit whitespace-nowrap rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1 text-meta font-semibold text-cyan-100">
                                Unimed · {guia.automacao_execucao.status}
                              </span>
                            ) : guia.solicitacao_item_id ? (
                              <span className="inline-flex w-fit whitespace-nowrap rounded-full border border-white/10 bg-white/5 px-3 py-1 text-meta font-semibold text-slate-200">
                                Item #{guia.solicitacao_item_id}
                              </span>
                            ) : null}
                          </div>
                        </td>
                        <td data-rotulo="Nº de Sessões"
                          className="px-4 py-4 tabular-nums text-slate-200"
                          data-testid={`guia-sessoes-solicitadas-${guia.id}`}
                        >
                          {guia.sessoes_solicitadas ?? '-'}
                        </td>
                        <td data-rotulo="Sessões Autorizadas"
                          className="px-4 py-4 tabular-nums text-slate-200"
                          data-testid={`guia-sessoes-autorizadas-${guia.id}`}
                        >
                          {guia.sessoes_autorizadas ?? '-'}
                        </td>
                        <td data-rotulo="Senha" className="px-4 py-4 text-slate-200">{guia.senha ?? '-'}</td>
                        <td data-rotulo="Validade" className="px-4 py-4 text-slate-200">{guia.validade_senha ?? '-'}</td>
                        <td data-rotulo="Última consulta" className="px-4 py-4 text-center text-slate-200">
                          {guia.unimed_last_checked_at || guia.ultima_automacao_unimed ? (
                            <Tooltip rotulo="Ver última consulta Unimed" icone={iconeLupa}>
                              <p className="font-semibold text-white">
                                {guia.unimed_last_checked_at ?? 'Sem data de consulta'}
                              </p>
                              {guia.ultima_automacao_unimed ? (
                                <p className="mt-1">
                                  {guia.ultima_automacao_unimed.operacao} ·{' '}
                                  {guia.ultima_automacao_unimed.status}
                                </p>
                              ) : null}
                              {guia.ultima_automacao_unimed?.erro_codigo &&
                              guia.ultima_automacao_unimed.status !== 'succeeded' ? (
                                <p className="mt-1 text-rose-200">
                                  {guia.ultima_automacao_unimed.erro_codigo}
                                </p>
                              ) : null}
                            </Tooltip>
                          ) : (
                            <span className="text-texto-suave">-</span>
                          )}
                        </td>
                        <td data-rotulo="Ações" data-rotulo-bloco className="w-px whitespace-nowrap px-4 py-4">
                          <DropdownMenu rotulo="Ações da guia" testId={`guia-acoes-${guia.id}`}>
                            <GuiaStatusActions guia={guia} />
                            <div className="flex w-full items-center gap-2">
                              <button
                                type="button"
                                className="flex-1 rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1.5 text-meta font-semibold text-emerald-100 transition hover:bg-emerald-400/20 disabled:opacity-50"
                                onClick={() => handleGerarConciliacao(guia.id)}
                                disabled={gerarConciliacao.isPending || (guia.status !== 'finalized' && guia.status !== 'approved')}
                                data-testid={`guia-gerar-conciliacao-${guia.id}`}
                              >
                                Gerar conciliação
                              </button>
                              <Tooltip rotulo="O que este botão faz">
                                Cria a linha de fechamento financeiro desta guia na tela de Conciliação.
                                Só fica disponível quando a guia está aprovada/finalizada.
                              </Tooltip>
                            </div>
                            {pode('guias.manage') ? (
                              <Link
                                to={`/guias/${guia.id}/editar`}
                                className="block w-full rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-center text-meta font-semibold text-white transition hover:bg-white/10"
                                data-testid={`guia-editar-${guia.id}`}
                              >
                                Editar
                              </Link>
                            ) : null}
                          </DropdownMenu>
                        </td>
                    </tr>
                  ))}
                  {guias.length === 0 ? (
                    <tr>
                      <td colSpan={12} className="px-4 py-8 text-center text-slate-300">
                        Nenhuma guia encontrada.
                      </td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </div>
          </div>
        )}

        <div className="flex items-center justify-between gap-3">
          <button
            type="button"
            className="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-corpo font-medium text-white transition hover:bg-white/10 disabled:opacity-50"
            onClick={() => setPage((current) => Math.max(1, current - 1))}
            disabled={page <= 1 || guiasQuery.isFetching}
          >
            Anterior
          </button>

          <p className="inline-flex min-h-6 items-center text-corpo text-slate-300">
            Página {page} de {totalPages}
          </p>

          <button
            type="button"
            className="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-corpo font-medium text-white transition hover:bg-white/10 disabled:opacity-50"
            onClick={() => setPage((current) => Math.min(totalPages, current + 1))}
            disabled={page >= totalPages || guiasQuery.isFetching}
          >
            Próxima
          </button>
        </div>
      </section>
      ) : null}

      <AutomacaoProgressoModal
        execucaoId={progressoExecucaoId}
        onClose={() => setProgressoExecucaoId(null)}
        titulo="Verificando status na Unimed"
        descricao="Acompanhe a consulta de status desta guia no portal da Unimed."
        mensagemExecutando="O robô está consultando o status desta guia no portal da Unimed..."
        queryKeysInvalidar={[['guias']]}
      />
    </div>
  )
}
