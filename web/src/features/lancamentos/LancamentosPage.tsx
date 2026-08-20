import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { ColunaOrdenavel } from '../../components/ui/ColunaOrdenavel'
import { useOrdenacao } from '../../lib/useOrdenacao'
import { Botao } from '../../components/ui/Botao'
import { Indicadores } from '../../components/ui/Indicadores'
import { Link, useMatch, useNavigate, useSearchParams } from 'react-router-dom'
import { translateStatus } from '../../lib/statusLabels'
import { Select } from '../../components/ui/Select'
import { useProfissionais } from '../../lib/queries/useReferenceData'
import { useAntecipacoes } from '../antecipacoes/useAntecipacoes'
import {
  useCriarLancamento,
  useLancamentoPrintTemplate,
  useLancamentos,
} from './useLancamentos'
import type {
  LancamentoFilters,
  LancamentoForm,
} from './types'
import { getHttpErrorMessage } from './useLancamentos'
import {
  defaultBlankTemplateData,
  renderLancamentoPrintTemplate,
} from './printTemplate'
import { Tooltip } from '../../components/ui/Tooltip'
import { usePode } from '../../lib/permissoes'

const defaultFilters: LancamentoFilters = {
  profissional_id: '',
  data_sessao: '',
}

const emptyForm: LancamentoForm = {
  antecipacao_id: '',
  profissional_id: '',
  data_sessao: new Date().toISOString().slice(0, 10),
  hora_inicio: '',
  hora_fim: '',
  acompanhante: '',
  resumo_atividades: '',
  observacoes: '',
}

function selectClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function formatEmpty(value: string | null | undefined) {
  return value && value.trim() !== '' ? value : '—'
}

export function LancamentosPage() {
  const pode = usePode()
  const navigate = useNavigate()
  const isCreateRoute = useMatch('/lancamentos/novo') !== null
  const [searchParams] = useSearchParams()
  const initialAntecipacaoId = searchParams.get('antecipacao_id') ?? ''

  const [filters, setFilters] = useState(defaultFilters)
  const [draftFilters, setDraftFilters] = useState(defaultFilters)
  const [page, setPage] = useState(1)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [form, setForm] = useState<LancamentoForm>({
    ...emptyForm,
    antecipacao_id: initialAntecipacaoId,
  })
  const [formError, setFormError] = useState<string | null>(null)

  const { ordenacao, ordenarPor } = useOrdenacao({
    ordenar_por: 'id',
    direcao: 'desc',
  })

  const profissionaisQuery = useProfissionais()
  const antecipacoesQuery = useAntecipacoes({ status: '', paciente_id: '', convenio_id: '' }, 1)
  const lancamentosQuery = useLancamentos({ ...filters, ...ordenacao }, page)
  const printTemplateQuery = useLancamentoPrintTemplate()
  const criarLancamento = useCriarLancamento()

  const profissionais = useMemo(() => profissionaisQuery.data ?? [], [profissionaisQuery.data])
  const antecipacoes = useMemo(() => antecipacoesQuery.data?.data ?? [], [antecipacoesQuery.data])
  const lancamentos = lancamentosQuery.data?.data ?? []
  const totalPages = lancamentosQuery.data?.meta?.last_page ?? 1

  useEffect(() => {
    if (antecipacoes.length === 0) {
      return
    }

    setForm((current) => ({
      ...current,
      antecipacao_id: current.antecipacao_id || initialAntecipacaoId || String(antecipacoes[0].id),
    }))
  }, [antecipacoes, initialAntecipacaoId])

  useEffect(() => {
    if (profissionais.length === 0) {
      return
    }

    setForm((current) => ({
      ...current,
      profissional_id: current.profissional_id || String(profissionais[0].id),
    }))
  }, [profissionais])

  useEffect(() => {
    setIsFormOpen(isCreateRoute)
  }, [isCreateRoute])

  const handleFilterSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setPage(1)
    setFilters(draftFilters)
  }

  const handleNew = () => {
    navigate('/lancamentos/novo')
    setForm((current) => ({
      ...emptyForm,
      antecipacao_id: current.antecipacao_id || initialAntecipacaoId || current.antecipacao_id,
      profissional_id: current.profissional_id,
    }))
    setFormError(null)
  }

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFormError(null)

    try {
      await criarLancamento.mutateAsync(form)
      if (isCreateRoute) {
        navigate('/lancamentos')
      } else {
        setIsFormOpen(false)
      }
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível registrar a sessão.'))
    }
  }

  const antecipaSelecionada = antecipacoes.find(
    (antecipacao) => String(antecipacao.id) === form.antecipacao_id,
  )
  const printHtml = useMemo(
    () =>
      renderLancamentoPrintTemplate(
        printTemplateQuery.data?.html ?? '',
        defaultBlankTemplateData,
      ),
    [printTemplateQuery.data?.html],
  )

  return (
    <>
      <div className="space-y-8 print:hidden" data-testid="lancamentos-page">
        {!isCreateRoute ? (
        <section className="space-y-4">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Sessões</p>
              <h2 className="mt-2 flex items-center gap-2 text-3xl font-semibold text-white">
                Registro de sessões
                <Tooltip rotulo="O que se registra aqui">
                  <p className="font-semibold text-white">O atendimento realizado</p>
                  <p className="mt-1">
                    Cada sessão lançada aqui consome uma unidade da cota da Antecipação escolhida.
                    Dá para digitar manualmente (botão Novo) ou importar a transcrição de um
                    formulário em papel lido por IA (Importar transcrição).
                  </p>
                </Tooltip>
              </h2>
            </div>

            <div className="flex flex-wrap gap-2">
              <Link
                to="/lancamentos/templates"
                className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                data-testid="lancamento-templates"
              >
                Templates
              </Link>
              <Link
                to="/lancamentos/importar"
                className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                data-testid="lancamento-importar-transcricao"
              >
                Importar transcrição
              </Link>
              <Botao
                variante="secundario"
                onClick={() => window.print()}
                data-testid="lancamento-imprimir-modelo"
                disabled={printTemplateQuery.isLoading || printHtml.trim() === ''}
              >
                {printTemplateQuery.isLoading ? 'Carregando modelo...' : 'Imprimir modelo em branco'}
              </Botao>
              <Botao variante="primario" onClick={handleNew} data-testid="lancamento-novo">
                Novo
              </Botao>
              <Tooltip rotulo="Diferença entre os botões">
                <p><strong>Templates:</strong> textos padrão reaproveitados no resumo da sessão.</p>
                <p className="mt-1">
                  <strong>Importar transcrição:</strong> lê por IA um formulário em papel já
                  preenchido, com várias sessões de uma vez.
                </p>
                <p className="mt-1">
                  <strong>Imprimir modelo em branco:</strong> gera a tabela em papel para o
                  profissional preencher à mão durante o atendimento.
                </p>
                <p className="mt-1"><strong>Novo:</strong> digita uma sessão manualmente, uma de cada vez.</p>
              </Tooltip>
            </div>
          </div>

          <Indicadores
            itens={[
              { rotulo: 'Total na página', valor: lancamentos.length },
              { rotulo: 'Página', valor: `${page} de ${totalPages}` },
            ]}
          />
        </section>
        ) : null}

        {!isCreateRoute ? (
        <>

        </>
        ) : null}

        {isFormOpen ? (
          <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <h3 className="text-lg font-semibold text-white">Novo lançamento manual</h3>
                </div>
              <Botao
                variante="secundario"
                onClick={() => {
                  if (isCreateRoute) {
                    navigate('/lancamentos')
                    return
                  }

                  setIsFormOpen(false)
                }}
                data-testid="lancamento-fechar"
              >
                Fechar
              </Botao>
              </div>

              <label className="block space-y-2">
                <span className="flex items-center gap-1 text-sm font-medium text-slate-200">
                  Antecipação
                  <Tooltip rotulo="O que escolher aqui">
                    A cota de sessões do paciente para esta especialidade/ciclo. Escolha a que
                    corresponde ao atendimento — lançar contra a antecipação errada consome a cota
                    de outro paciente ou especialidade.
                  </Tooltip>
                </span>
                <Select
                  value={form.antecipacao_id}
                  onChange={(event) =>
                    setForm((current) => ({ ...current, antecipacao_id: event.target.value }))
                  }
                  className={selectClasses()}
                  data-testid="lancamento-antecipacao"
                >
                  {antecipacoes.map((antecipacao) => (
                    <option key={antecipacao.id} value={antecipacao.id}>
                      #{antecipacao.id} · Paciente {antecipacao.paciente_id} · {antecipacao.status}
                    </option>
                  ))}
                </Select>
              </label>

              <label className="block space-y-2">
                <span className="text-sm font-medium text-slate-200">Profissional executante</span>
                <Select
                  value={form.profissional_id}
                  onChange={(event) =>
                    setForm((current) => ({ ...current, profissional_id: event.target.value }))
                  }
                  className={selectClasses()}
                  data-testid="lancamento-profissional"
                >
                  {profissionais.map((profissional) => (
                    <option key={profissional.id} value={profissional.id}>
                      {profissional.nome}
                    </option>
                  ))}
                </Select>
              </label>

              <label className="block space-y-2">
                <span className="text-sm font-medium text-slate-200">Data da sessão</span>
                <input
                  type="date"
                  value={form.data_sessao}
                  onChange={(event) =>
                    setForm((current) => ({ ...current, data_sessao: event.target.value }))
                  }
                  className={selectClasses()}
                  data-testid="lancamento-data-sessao"
                />
              </label>

              <div className="grid gap-4 md:grid-cols-2">
                <label className="block space-y-2">
                  <span className="text-sm font-medium text-slate-200">Hora início</span>
                  <input
                    type="time"
                    value={form.hora_inicio}
                    onChange={(event) =>
                      setForm((current) => ({ ...current, hora_inicio: event.target.value }))
                    }
                    className={selectClasses()}
                    data-testid="lancamento-hora-inicio"
                  />
                </label>

                <label className="block space-y-2">
                  <span className="text-sm font-medium text-slate-200">Hora fim</span>
                  <input
                    type="time"
                    value={form.hora_fim}
                    onChange={(event) =>
                      setForm((current) => ({ ...current, hora_fim: event.target.value }))
                    }
                    className={selectClasses()}
                    data-testid="lancamento-hora-fim"
                  />
                </label>
              </div>

              <label className="block space-y-2">
                <span className="text-sm font-medium text-slate-200">Acompanhante</span>
                <input
                  value={form.acompanhante}
                  onChange={(event) =>
                    setForm((current) => ({ ...current, acompanhante: event.target.value }))
                  }
                  className={selectClasses()}
                  data-testid="lancamento-acompanhante"
                />
              </label>

              <label className="block space-y-2">
                <span className="text-sm font-medium text-slate-200">Resumo das atividades</span>
                <textarea
                  value={form.resumo_atividades}
                  onChange={(event) =>
                    setForm((current) => ({ ...current, resumo_atividades: event.target.value }))
                  }
                  className={`${selectClasses()} min-h-28`}
                  data-testid="lancamento-resumo"
                />
              </label>

              <label className="block space-y-2">
                <span className="text-sm font-medium text-slate-200">Observações</span>
                <textarea
                  value={form.observacoes}
                  onChange={(event) =>
                    setForm((current) => ({ ...current, observacoes: event.target.value }))
                  }
                  className={`${selectClasses()} min-h-24`}
                  placeholder="Opcional"
                  data-testid="lancamento-observacoes"
                />
              </label>

              {formError ? (
                <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                  {formError}
                </p>
              ) : null}

              <div className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-300">
                {antecipaSelecionada
                  ? `Antecipação selecionada: #${antecipaSelecionada.id} · ${antecipaSelecionada.status}`
                  : 'Selecione uma antecipação para registrar a sessão.'}
              </div>

              <Botao
                type="submit"
                variante="primario"
                className="w-full"
                disabled={criarLancamento.isPending}
                data-testid="lancamento-submit"
              >
                {criarLancamento.isPending ? 'Salvando...' : 'Registrar sessão'}
              </Botao>
            </form>
          </section>
        ) : null}

        {!isCreateRoute ? (
        <section className="space-y-4 rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">

            <form className="grid gap-3 md:grid-cols-3 xl:grid-cols-3" onSubmit={handleFilterSubmit}>
              <label className="space-y-2">
                <span className="text-xs uppercase tracking-[0.25em] text-slate-400">
                  Profissional executante
                </span>
                <Select
                  value={draftFilters.profissional_id}
                  onChange={(event) =>
                    setDraftFilters((current) => ({ ...current, profissional_id: event.target.value }))
                  }
                  className={selectClasses()}
                  data-testid="lancamento-filtro-profissional"
                >
                  <option value="">Todos</option>
                  {profissionais.map((profissional) => (
                    <option key={profissional.id} value={profissional.id}>
                      {profissional.nome}
                    </option>
                  ))}
                </Select>
              </label>

              <label className="space-y-2">
                <span className="text-xs uppercase tracking-[0.25em] text-slate-400">
                  Data sessão
                </span>
                <input
                  type="date"
                  value={draftFilters.data_sessao}
                  onChange={(event) =>
                    setDraftFilters((current) => ({ ...current, data_sessao: event.target.value }))
                  }
                  className={selectClasses()}
                  data-testid="lancamento-filtro-data-sessao"
                />
              </label>

              <Botao type="submit" variante="secundario">
                Aplicar
              </Botao>
            </form>
          </div>

          {lancamentosQuery.isLoading ? (
            <div className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
              Carregando sessões...
            </div>
          ) : lancamentosQuery.isError ? (
            <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-sm text-rose-100">
              Não foi possível carregar a lista.
            </div>
          ) : (
            <div className="overflow-hidden rounded-3xl border border-white/10">
              <table className="w-full border-collapse text-left text-sm">
                <thead className="bg-fundo text-xs uppercase tracking-[0.25em] text-texto-suave">
                  <tr>
                    <ColunaOrdenavel
                    titulo="ID"
                    coluna="id"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                    <ColunaOrdenavel
                    titulo="Antecipação"
                    coluna="antecipacao"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                    <ColunaOrdenavel
                    titulo="Profissional executante"
                    coluna="profissional"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                    <ColunaOrdenavel
                    titulo="Data / Hora"
                    coluna="data"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                    <ColunaOrdenavel
                    titulo="Acompanhante"
                    coluna="acompanhante"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                    <ColunaOrdenavel titulo="Resumo" />
                    <ColunaOrdenavel
                    titulo="Status"
                    coluna="status"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                    <ColunaOrdenavel titulo="Ações" />
                  </tr>
                </thead>
                <tbody className="divide-y divide-linha bg-superficie">
                  {lancamentos.map((lancamento) => (
                    <tr key={lancamento.id} data-testid={`lancamento-row-${lancamento.id}`}>
                      <td className="px-4 py-4 font-medium text-white">#{lancamento.id}</td>
                      <td className="px-4 py-4 text-slate-200">#{lancamento.antecipacao_id}</td>
                      <td className="px-4 py-4 text-slate-200">
                        {lancamento.profissional?.nome ??
                          profissionais.find((item) => item.id === lancamento.profissional_id)?.nome ??
                          lancamento.profissional_id}
                      </td>
                      <td className="px-4 py-4 text-slate-200">
                        <div>{lancamento.data_sessao}</div>
                        <div className="text-xs text-slate-400">
                          {formatEmpty(lancamento.hora_inicio)} - {formatEmpty(lancamento.hora_fim)}
                        </div>
                      </td>
                      <td className="px-4 py-4 text-slate-200">
                        {formatEmpty(lancamento.acompanhante)}
                      </td>
                      <td className="px-4 py-4 text-slate-200">
                        <span className="block max-w-xl">{formatEmpty(lancamento.resumo_atividades)}</span>
                      </td>
                      <td className="px-4 py-4 text-slate-200">
                        {translateStatus('lancamentos', lancamento.status)}
                      </td>
                      <td className="px-4 py-4">
                        {pode('lancamentos.manage') ? (
                          <Link
                            to={`/lancamentos/${lancamento.id}/editar`}
                            className="inline-flex rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/10"
                            data-testid={`lancamento-editar-${lancamento.id}`}
                          >
                            Editar
                          </Link>
                        ) : null}
                      </td>
                    </tr>
                  ))}
                  {lancamentos.length === 0 ? (
                    <tr>
                      <td colSpan={8} className="px-4 py-8 text-center text-slate-300">
                        Nenhuma sessão encontrada.
                      </td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </div>
          )}

          <div className="flex items-center justify-between gap-3">
            <button
              type="button"
              className="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-white transition hover:bg-white/10 disabled:opacity-50"
              onClick={() => setPage((current) => Math.max(1, current - 1))}
              disabled={page <= 1 || lancamentosQuery.isFetching}
            >
              Anterior
            </button>

            <p className="text-sm text-slate-300">
              Página {page} de {totalPages}
            </p>

            <button
              type="button"
              className="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-white transition hover:bg-white/10 disabled:opacity-50"
              onClick={() => setPage((current) => Math.min(totalPages, current + 1))}
              disabled={page >= totalPages || lancamentosQuery.isFetching}
            >
              Próxima
            </button>
          </div>
        </section>
        ) : null}
      </div>

      <section
        className="hidden print:block bg-white p-8 text-slate-950"
        dangerouslySetInnerHTML={{ __html: printHtml }}
      />
    </>
  )
}
