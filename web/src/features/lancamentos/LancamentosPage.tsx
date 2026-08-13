import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { Indicadores } from '../../components/ui/Indicadores'
import { Link, useMatch, useNavigate, useSearchParams } from 'react-router-dom'
import { translateStatus } from '../../lib/statusLabels'
import { Select } from '../../components/ui/Select'
import { useProfissionais } from '../../lib/queries/useReferenceData'
import { useAntecipacoes } from '../antecipacoes/useAntecipacoes'
import {
  useConfirmarLancamentosTranscritos,
  useCriarLancamento,
  useLancamentoPrintTemplate,
  useImportarLancamentosTranscritos,
  useLancamentos,
} from './useLancamentos'
import type {
  LancamentoConfirmImportForm,
  LancamentoFilters,
  LancamentoForm,
  LancamentoImportForm,
  LancamentoTranscricaoPreview,
  LancamentoTranscricaoSessao,
} from './types'
import { getHttpErrorMessage } from './useLancamentos'
import {
  defaultBlankTemplateData,
  renderLancamentoPrintTemplate,
} from './printTemplate'

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

const emptyImportForm: LancamentoImportForm = {
  antecipacao_id: '',
  profissional_id: '',
  transcricao: '',
}

function selectClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function formatEmpty(value: string | null | undefined) {
  return value && value.trim() !== '' ? value : '—'
}

export function LancamentosPage() {
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
  const [importForm, setImportForm] = useState<LancamentoImportForm>({
    ...emptyImportForm,
    antecipacao_id: initialAntecipacaoId,
  })
  const [importPreview, setImportPreview] = useState<LancamentoTranscricaoPreview | null>(null)
  const [importDraftSessoes, setImportDraftSessoes] = useState<LancamentoTranscricaoSessao[]>([])
  const [importPdfFile, setImportPdfFile] = useState<File | null>(null)
  const [formError, setFormError] = useState<string | null>(null)
  const [importError, setImportError] = useState<string | null>(null)

  const profissionaisQuery = useProfissionais()
  const antecipacoesQuery = useAntecipacoes({ status: '', paciente_id: '', convenio_id: '' }, 1)
  const lancamentosQuery = useLancamentos(filters, page)
  const printTemplateQuery = useLancamentoPrintTemplate()
  const criarLancamento = useCriarLancamento()
  const importarLancamentos = useImportarLancamentosTranscritos()
  const confirmarLancamentos = useConfirmarLancamentosTranscritos()

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
    setImportForm((current) => ({
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
    setImportForm((current) => ({
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

  const handleImportSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setImportError(null)

    try {
      const result = await importarLancamentos.mutateAsync(importForm)
      setImportPreview({
        confirmacao_pendente: result.confirmacao_pendente,
        cabecalho: result.cabecalho,
        sessoes: result.sessoes,
      })
      setImportDraftSessoes(result.sessoes)
      setImportPdfFile(null)
    } catch (error) {
      setImportError(
        getHttpErrorMessage(error, 'Não foi possível importar a transcrição do registro.'),
      )
    }
  }

  const handleConfirmImport = async () => {
    if (!importPreview) {
      return
    }

    setImportError(null)

    try {
      const payload: LancamentoConfirmImportForm = {
        ...importForm,
        sessoes: importDraftSessoes,
        pdf_registro_sessoes: importPdfFile,
      }

      if (exigePdf && !importPdfFile) {
        setImportError('O PDF do registro de sessões é obrigatório para a regional 0220.')
        return
      }

      await confirmarLancamentos.mutateAsync(payload)
      setImportPreview(null)
      setImportDraftSessoes([])
      setImportPdfFile(null)
      setImportForm((current) => ({ ...current, transcricao: '' }))
    } catch (error) {
      setImportError(getHttpErrorMessage(error, 'Não foi possível confirmar o envio do registro.'))
    }
  }

  const atualizarSessaoImportada = (
    index: number,
    campo: keyof LancamentoTranscricaoSessao,
    valor: string,
  ) => {
    setImportDraftSessoes((current) =>
      current.map((sessao, sessaoIndex) =>
        sessaoIndex === index ? { ...sessao, [campo]: valor } : sessao,
      ),
    )
  }

  const antecipaSelecionada = antecipacoes.find(
    (antecipacao) => String(antecipacao.id) === form.antecipacao_id,
  )
  const importAntecipacaoSelecionada = antecipacoes.find(
    (antecipacao) => String(antecipacao.id) === importForm.antecipacao_id,
  )
  const importProfissionalSelecionado = profissionais.find(
    (profissional) => String(profissional.id) === importForm.profissional_id,
  )
  const exigePdf =
    importPreview?.cabecalho.numero_cartao?.replace(/\D+/g, '').startsWith('0220') ?? false
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
              <h2 className="mt-2 text-3xl font-semibold text-white">Registro de sessões</h2>
              <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
                O sistema registra sessões manualmente ou por importação da transcrição do
                formulário da Unimed, mantendo a antecipação como base de consumo.
              </p>
            </div>

            <div className="flex flex-wrap gap-2">
              <Link
                to="/lancamentos/templates"
                className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                data-testid="lancamento-templates"
              >
                Templates
              </Link>
              <button
                type="button"
                onClick={() => window.print()}
                className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                data-testid="lancamento-imprimir-modelo"
                disabled={printTemplateQuery.isLoading || printHtml.trim() === ''}
              >
                {printTemplateQuery.isLoading ? 'Carregando modelo...' : 'Imprimir modelo em branco'}
              </button>
              <button
                type="button"
                onClick={handleNew}
                className="rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300"
                data-testid="lancamento-novo"
              >
                Novo
              </button>
            </div>
          </div>

          <Indicadores
            itens={[
              { rotulo: 'Total na página', valor: lancamentos.length },
              { rotulo: 'Antecipação', valor: importAntecipacaoSelecionada?.id ?? antecipaSelecionada?.id ?? '—' },
              { rotulo: 'Importação', valor: importProfissionalSelecionado?.nome ?? 'Pendente' },
            ]}
          />
        </section>
        ) : null}

        {!isCreateRoute ? (
        <>
        <section className="grid gap-6 xl:grid-cols-2">
          <article className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="text-lg font-semibold text-white">Importar transcrição</h3>
                <p className="text-sm text-slate-300">
                  Primeiro o sistema analisa o texto. Depois o operador revisa datas e horários e
                  confirma o envio.
                </p>
              </div>
            </div>

            <form onSubmit={handleImportSubmit} className="mt-4 space-y-4">
              <label className="block space-y-2">
                <span className="text-sm font-medium text-slate-200">Antecipação</span>
                <Select
                  value={importForm.antecipacao_id}
                  onChange={(event) =>
                    setImportForm((current) => ({
                      ...current,
                      antecipacao_id: event.target.value,
                    }))
                  }
                  className={selectClasses()}
                  data-testid="lancamento-import-antecipacao"
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
                  value={importForm.profissional_id}
                  onChange={(event) =>
                    setImportForm((current) => ({
                      ...current,
                      profissional_id: event.target.value,
                    }))
                  }
                  className={selectClasses()}
                  data-testid="lancamento-import-profissional"
                >
                  {profissionais.map((profissional) => (
                    <option key={profissional.id} value={profissional.id}>
                      {profissional.nome}
                    </option>
                  ))}
                </Select>
              </label>

              <label className="block space-y-2">
                <span className="text-sm font-medium text-slate-200">Transcrição OCR</span>
                <textarea
                  value={importForm.transcricao}
                  onChange={(event) =>
                    setImportForm((current) => ({ ...current, transcricao: event.target.value }))
                  }
                  className={`${selectClasses()} min-h-72 font-mono text-sm leading-6`}
                  placeholder={`GUIA Nº: 521381566206
Clínica: Centro Neuro Kids Ltda
Paciente: ...
Número Cartão: 0220 090000 551.330-8
Profissional Executante: Mariana
Terapia aplicada: ABA - AV. Neuropsicológica

08/04/26 14:50 15:40 Bruno Marinho Aplicação testes Neuropsicológicos`}
                  data-testid="lancamento-import-transcricao"
                />
              </label>

              {importError ? (
                <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                  {importError}
                </p>
              ) : null}

              <div className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-300">
                {importAntecipacaoSelecionada
                  ? `Antecipação selecionada: #${importAntecipacaoSelecionada.id} · ${importAntecipacaoSelecionada.status}`
                  : 'Selecione uma antecipação para analisar o registro.'}
              </div>

              <button
                type="submit"
                disabled={importarLancamentos.isPending || importForm.transcricao.trim() === ''}
                className="inline-flex w-full items-center justify-center rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60"
                data-testid="lancamento-importar"
              >
                {importarLancamentos.isPending ? 'Analisando...' : 'Analisar transcrição'}
              </button>
            </form>

            {importPreview ? (
              <div className="mt-6 space-y-4">
                <div className="rounded-2xl border border-cyan-400/20 bg-cyan-500/10 px-4 py-3 text-sm text-cyan-50">
                  Nenhuma sessão foi salva ainda. Ajuste datas e horários, depois confirme o envio.
                </div>
                {exigePdf ? (
                  <div className="rounded-2xl border border-amber-400/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-50">
                    Regional 0220 detectada pela carteirinha. O PDF do registro de sessões é
                    obrigatório para confirmar o envio.
                  </div>
                ) : null}

                <label className="block space-y-2">
                  <span className="text-sm font-medium text-slate-200">PDF do registro de sessões</span>
                  <input
                    type="file"
                    accept="application/pdf,.pdf"
                    onChange={(event) => setImportPdfFile(event.target.files?.[0] ?? null)}
                    className="block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-200 file:mr-4 file:rounded-full file:border-0 file:bg-cyan-400 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-950"
                    data-testid="lancamento-import-pdf"
                  />
                </label>

                <div className="grid gap-3 md:grid-cols-2">
                  {Object.entries(importPreview.cabecalho).map(([key, value]) => (
                    <div key={key} className="rounded-2xl border border-white/10 bg-white/5 p-4">
                      <p className="text-xs uppercase tracking-[0.25em] text-slate-400">{key}</p>
                      <p className="mt-2 text-sm font-medium text-white">{formatEmpty(value)}</p>
                    </div>
                  ))}
                </div>

                <div className="overflow-hidden rounded-3xl border border-white/10">
                  <table className="w-full border-collapse text-left text-sm">
                    <thead className="bg-white/5 text-xs uppercase tracking-[0.25em] text-slate-400">
                      <tr>
                        <th className="px-4 py-3">Data</th>
                        <th className="px-4 py-3">Início</th>
                        <th className="px-4 py-3">Fim</th>
                        <th className="px-4 py-3">Acompanhante</th>
                        <th className="px-4 py-3">Resumo</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-white/10 bg-slate-950/30">
                      {importDraftSessoes.map((sessao, index) => (
                        <tr key={`${sessao.data_sessao ?? index}-${index}`}>
                          <td className="px-4 py-4">
                            <input
                              type="date"
                              value={sessao.data_sessao ?? ''}
                              onChange={(event) =>
                                atualizarSessaoImportada(index, 'data_sessao', event.target.value)
                              }
                              className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-300/70"
                            />
                          </td>
                          <td className="px-4 py-4">
                            <input
                              type="time"
                              value={sessao.hora_inicio ?? ''}
                              onChange={(event) =>
                                atualizarSessaoImportada(index, 'hora_inicio', event.target.value)
                              }
                              className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-300/70"
                            />
                          </td>
                          <td className="px-4 py-4">
                            <input
                              type="time"
                              value={sessao.hora_fim ?? ''}
                              onChange={(event) =>
                                atualizarSessaoImportada(index, 'hora_fim', event.target.value)
                              }
                              className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-300/70"
                            />
                          </td>
                          <td className="px-4 py-4">
                            <input
                              value={sessao.acompanhante ?? ''}
                              onChange={(event) =>
                                atualizarSessaoImportada(index, 'acompanhante', event.target.value)
                              }
                              className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-300/70"
                            />
                          </td>
                          <td className="px-4 py-4">
                            <textarea
                              value={sessao.resumo_atividades ?? ''}
                              onChange={(event) =>
                                atualizarSessaoImportada(
                                  index,
                                  'resumo_atividades',
                                  event.target.value,
                                )
                              }
                              className="min-h-20 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-300/70"
                            />
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3">
                  <p className="text-sm text-slate-300">
                    {importDraftSessoes.length} sessão(ões) pronta(s) para confirmação.
                  </p>
                  <button
                    type="button"
                    onClick={handleConfirmImport}
                    disabled={
                      confirmarLancamentos.isPending ||
                      importDraftSessoes.length === 0 ||
                      (exigePdf && !importPdfFile)
                    }
                    className="inline-flex items-center justify-center rounded-2xl bg-emerald-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-emerald-300 disabled:opacity-60"
                    data-testid="lancamento-confirmar-envio"
                  >
                    {confirmarLancamentos.isPending ? 'Confirmando...' : 'Confirmar envio'}
                  </button>
                </div>
              </div>
            ) : (
              <div className="mt-6 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
                A pré-visualização aparece depois da análise da transcrição.
              </div>
            )}
          </article>
        </section>

        </>
        ) : null}

        {isFormOpen ? (
          <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <h3 className="text-lg font-semibold text-white">Novo lançamento manual</h3>
                  <p className="text-sm text-slate-300">
                    Selecione uma antecipação e preencha a sessão quando houver ajuste fino.
                  </p>
                </div>
              <button
                type="button"
                onClick={() => {
                  if (isCreateRoute) {
                    navigate('/lancamentos')
                    return
                  }

                  setIsFormOpen(false)
                }}
                className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                data-testid="lancamento-fechar"
              >
                  Fechar
                </button>
              </div>

              <label className="block space-y-2">
                <span className="text-sm font-medium text-slate-200">Antecipação</span>
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

              <button
                type="submit"
                disabled={criarLancamento.isPending}
                className="inline-flex w-full items-center justify-center rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60"
                data-testid="lancamento-submit"
              >
                {criarLancamento.isPending ? 'Salvando...' : 'Registrar sessão'}
              </button>
            </form>
          </section>
        ) : null}

        {!isCreateRoute ? (
        <section className="space-y-4 rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <h3 className="text-lg font-semibold text-white">Filtros e lista</h3>
              <p className="text-sm text-slate-300">
                Cada linha já nasce como uma sessão contabilizável da antecipação.
              </p>
            </div>

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

              <button
                type="submit"
                className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
              >
                Aplicar
              </button>
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
                <thead className="bg-white/5 text-xs uppercase tracking-[0.25em] text-slate-400">
                  <tr>
                    <th className="px-4 py-3">ID</th>
                    <th className="px-4 py-3">Antecipação</th>
                    <th className="px-4 py-3">Profissional executante</th>
                    <th className="px-4 py-3">Data / Hora</th>
                    <th className="px-4 py-3">Acompanhante</th>
                    <th className="px-4 py-3">Resumo</th>
                    <th className="px-4 py-3">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-white/10 bg-slate-950/30">
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
                    </tr>
                  ))}
                  {lancamentos.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="px-4 py-8 text-center text-slate-300">
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
