import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useSearchParams } from 'react-router-dom'
import { translateStatus } from '../../lib/statusLabels'
import { Select } from '../../components/ui/Select'
import { useProfissionais } from '../../lib/queries/useReferenceData'
import { useAntecipacoes } from '../antecipacoes/useAntecipacoes'
import {
  useImportarAnaliticoUnimed,
  useConfirmarLancamentosTranscritos,
  useCriarLancamento,
  useImportarLancamentosTranscritos,
  useLancamentos,
} from './useLancamentos'
import type {
  AnaliticoUnimedPreview,
  LancamentoConfirmImportForm,
  LancamentoFilters,
  LancamentoForm,
  LancamentoImportForm,
  LancamentoTranscricaoPreview,
  LancamentoTranscricaoSessao,
} from './types'
import { getHttpErrorMessage } from './useLancamentos'

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

const blankRows = Array.from({ length: 8 }, (_, index) => index + 1)

function selectClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function formatEmpty(value: string | null | undefined) {
  return value && value.trim() !== '' ? value : '—'
}

function formatDateTime(value: string | null | undefined) {
  if (!value) {
    return '—'
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return date.toLocaleString('pt-BR')
}

export function LancamentosPage() {
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
  const [analiticoFile, setAnaliticoFile] = useState<File | null>(null)
  const [analiticoPreview, setAnaliticoPreview] = useState<AnaliticoUnimedPreview | null>(null)
  const [analiticoError, setAnaliticoError] = useState<string | null>(null)
  const [formError, setFormError] = useState<string | null>(null)
  const [importError, setImportError] = useState<string | null>(null)

  const profissionaisQuery = useProfissionais()
  const antecipacoesQuery = useAntecipacoes({ status: '', paciente_id: '', convenio_id: '' }, 1)
  const lancamentosQuery = useLancamentos(filters, page)
  const criarLancamento = useCriarLancamento()
  const importarLancamentos = useImportarLancamentosTranscritos()
  const confirmarLancamentos = useConfirmarLancamentosTranscritos()
  const importarAnalitico = useImportarAnaliticoUnimed()

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

  const handleFilterSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setPage(1)
    setFilters(draftFilters)
  }

  const handleNew = () => {
    setForm((current) => ({
      ...emptyForm,
      antecipacao_id: current.antecipacao_id || initialAntecipacaoId || current.antecipacao_id,
      profissional_id: current.profissional_id,
    }))
    setFormError(null)
    setIsFormOpen(true)
  }

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFormError(null)

    try {
      await criarLancamento.mutateAsync(form)
      setIsFormOpen(false)
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

  const handleAnaliticoSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setAnaliticoError(null)

    if (!analiticoFile) {
      setAnaliticoError('Selecione um arquivo Excel para importar o analítico.')
      return
    }

    try {
      const result = await importarAnalitico.mutateAsync(analiticoFile)
      setAnaliticoPreview(result)
    } catch (error) {
      setAnaliticoError(getHttpErrorMessage(error, 'Não foi possível ler o analítico da Unimed.'))
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
  const analiticoPreviewRows = analiticoPreview?.analitico.linhas.slice(0, 8) ?? []
  const glosaPreviewRows = analiticoPreview?.glosas.linhas.slice(0, 8) ?? []
  const conciliacaoPreviewRows = analiticoPreview?.conciliacao.linhas.slice(0, 8) ?? []
  const analiticoLote = analiticoPreview?.lote

  return (
    <>
      <div className="space-y-8 print:hidden" data-testid="lancamentos-page">
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
              <button
                type="button"
                onClick={() => window.print()}
                className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                data-testid="lancamento-imprimir-modelo"
              >
                Imprimir modelo em branco
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

          <div className="grid gap-4 md:grid-cols-3">
            <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
              <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Total na página</p>
              <p className="mt-2 text-2xl font-semibold text-white">{lancamentos.length}</p>
            </article>
            <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
              <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Antecipação</p>
              <p className="mt-2 text-2xl font-semibold text-white">
                {importAntecipacaoSelecionada?.id ?? antecipaSelecionada?.id ?? '—'}
              </p>
            </article>
            <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
              <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Importação</p>
              <p className="mt-2 text-2xl font-semibold text-white">
                {importProfissionalSelecionado?.nome ?? 'Pendente'}
              </p>
            </article>
          </div>
        </section>

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

        <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <div className="flex items-start justify-between gap-4">
            <div>
              <h3 className="text-lg font-semibold text-white">Importar analítico da Unimed</h3>
              <p className="text-sm text-slate-300">
                Carregue o Excel devolvido pela Unimed para ler as linhas de atendimento e as
                glosas antes da conciliação.
              </p>
            </div>
          </div>

          <form onSubmit={handleAnaliticoSubmit} className="mt-4 space-y-4">
            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Arquivo Excel</span>
              <input
                type="file"
                accept=".xlsx,.xls"
                onChange={(event) => setAnaliticoFile(event.target.files?.[0] ?? null)}
                className="block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-200 file:mr-4 file:rounded-full file:border-0 file:bg-cyan-400 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-950"
                data-testid="analitico-import-arquivo"
              />
            </label>

            {analiticoError ? (
              <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                {analiticoError}
              </p>
            ) : null}

            <button
              type="submit"
              disabled={importarAnalitico.isPending}
              className="inline-flex items-center justify-center rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60"
              data-testid="analitico-importar"
            >
              {importarAnalitico.isPending ? 'Lendo planilha...' : 'Importar analítico'}
            </button>
          </form>

          {analiticoPreview ? (
            <div className="mt-6 space-y-6">
              <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <article className="rounded-2xl border border-white/10 bg-white/5 p-4">
                  <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Arquivo</p>
                  <p className="mt-2 text-sm font-medium text-white">
                    {analiticoPreview.arquivo}
                  </p>
                </article>
                {analiticoPreview.planilhas.map((planilha) => (
                  <article key={planilha.nome} className="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <p className="text-xs uppercase tracking-[0.25em] text-slate-400">{planilha.nome}</p>
                    <p className="mt-2 text-2xl font-semibold text-white">{planilha.linhas}</p>
                  </article>
                ))}
                <article className="rounded-2xl border border-white/10 bg-white/5 p-4">
                  <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Lote salvo</p>
                  <p className="mt-2 text-2xl font-semibold text-white">#{analiticoLote?.id ?? '—'}</p>
                </article>
                <article className="rounded-2xl border border-white/10 bg-white/5 p-4">
                  <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Status</p>
                  <p className="mt-2 text-sm font-medium text-white">
                    {analiticoLote?.status ?? 'importado'}
                  </p>
                  <p className="mt-1 text-xs text-slate-400">
                    {formatDateTime(analiticoLote?.importado_em)}
                  </p>
                </article>
              </div>

              <div className="grid gap-4 xl:grid-cols-2">
                <article className="rounded-3xl border border-white/10 bg-white/5 p-4">
                  <h4 className="text-base font-semibold text-white">Cabeçalho do analítico</h4>
                  <div className="mt-4 grid gap-3 md:grid-cols-2">
                    {Object.entries(analiticoPreview.analitico.cabecalho).map(([key, value]) => (
                      <div key={key} className="rounded-2xl border border-white/10 bg-slate-950/40 p-3">
                        <p className="text-[11px] uppercase tracking-[0.25em] text-slate-400">{key}</p>
                        <p className="mt-2 text-sm text-white">
                          {formatEmpty(value.raw)}
                        </p>
                      </div>
                    ))}
                  </div>
                  <div className="mt-4 grid gap-3 md:grid-cols-2">
                    <div className="rounded-2xl border border-white/10 bg-slate-950/40 p-3">
                      <p className="text-[11px] uppercase tracking-[0.25em] text-slate-400">
                        Total prestador
                      </p>
                      <p className="mt-2 text-sm text-white">
                        {formatEmpty(analiticoPreview.analitico.totais.prestador)}
                      </p>
                    </div>
                    <div className="rounded-2xl border border-white/10 bg-slate-950/40 p-3">
                      <p className="text-[11px] uppercase tracking-[0.25em] text-slate-400">
                        Total lote
                      </p>
                      <p className="mt-2 text-sm text-white">
                        {formatEmpty(analiticoPreview.analitico.totais.lote)}
                      </p>
                    </div>
                  </div>
                </article>

                <article className="rounded-3xl border border-white/10 bg-white/5 p-4">
                  <h4 className="text-base font-semibold text-white">Resumo de glosas</h4>
                  <p className="mt-2 text-sm text-slate-300">
                    {analiticoPreview.glosas.linhas.length} linha(s) de glosa encontradas.
                  </p>
                  <div className="mt-4 rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                    <p className="text-[11px] uppercase tracking-[0.25em] text-slate-400">Total</p>
                    <p className="mt-2 text-sm text-white">
                      {formatEmpty(analiticoPreview.glosas.total.valor)}
                    </p>
                  </div>
                </article>
              </div>

              <div className="grid gap-4 xl:grid-cols-3">
                <article className="rounded-3xl border border-white/10 bg-white/5 p-4">
                  <h4 className="text-base font-semibold text-white">Conciliação processável</h4>
                  <p className="mt-2 text-sm text-slate-300">
                    {analiticoPreview.conciliacao.linhas.length} linha(s) normalizada(s) para
                    conciliação.
                  </p>
                  <div className="mt-4 grid gap-3 md:grid-cols-3">
                    <div className="rounded-2xl border border-white/10 bg-slate-950/40 p-3">
                      <p className="text-[11px] uppercase tracking-[0.25em] text-slate-400">Pago</p>
                      <p className="mt-2 text-sm text-white">
                        {formatEmpty(analiticoPreview.conciliacao.totais.pago)}
                      </p>
                    </div>
                    <div className="rounded-2xl border border-white/10 bg-slate-950/40 p-3">
                      <p className="text-[11px] uppercase tracking-[0.25em] text-slate-400">
                        Glosado
                      </p>
                      <p className="mt-2 text-sm text-white">
                        {formatEmpty(analiticoPreview.conciliacao.totais.glosado)}
                      </p>
                    </div>
                    <div className="rounded-2xl border border-white/10 bg-slate-950/40 p-3">
                      <p className="text-[11px] uppercase tracking-[0.25em] text-slate-400">Saldo</p>
                      <p className="mt-2 text-sm text-white">
                        {formatEmpty(analiticoPreview.conciliacao.totais.saldo)}
                      </p>
                    </div>
                  </div>
                  <div className="mt-4 rounded-2xl border border-cyan-400/20 bg-cyan-500/10 p-4 text-sm text-cyan-50">
                    <p className="text-[11px] uppercase tracking-[0.25em] text-cyan-100/80">
                      Lote persistido
                    </p>
                    <p className="mt-2">
                      Total pago {formatEmpty(analiticoLote?.total_pago)} · glosado{' '}
                      {formatEmpty(analiticoLote?.total_glosado)} · saldo{' '}
                      {formatEmpty(analiticoLote?.saldo_total)}
                    </p>
                  </div>
                </article>

                <article className="rounded-3xl border border-white/10 bg-white/5 p-4">
                  <h4 className="text-base font-semibold text-white">Resumo por guia</h4>
                  <p className="mt-2 text-sm text-slate-300">
                    {analiticoPreview.conciliacao.resumo_por_guia.length} guia(s) agrupada(s).
                  </p>
                  <div className="mt-4 overflow-hidden rounded-2xl border border-white/10">
                    <table className="w-full border-collapse text-left text-sm">
                      <thead className="bg-white/5 text-[11px] uppercase tracking-[0.25em] text-slate-400">
                        <tr>
                          <th className="px-3 py-2">Guia</th>
                          <th className="px-3 py-2">Pago</th>
                          <th className="px-3 py-2">Glosado</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-white/10 bg-slate-950/30">
                        {analiticoPreview.conciliacao.resumo_por_guia.slice(0, 5).map((item) => (
                          <tr key={item.numero_guia_operadora}>
                            <td className="px-3 py-3 text-slate-200">{item.numero_guia_operadora}</td>
                            <td className="px-3 py-3 text-slate-200">{item.valor_pago}</td>
                            <td className="px-3 py-3 text-slate-200">{item.valor_glosado}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </article>

                <article className="rounded-3xl border border-white/10 bg-white/5 p-4">
                  <h4 className="text-base font-semibold text-white">Linhas normalizadas</h4>
                  <p className="mt-2 text-sm text-slate-300">
                    As linhas já estão prontas para virar lançamento de conciliação.
                  </p>
                  <div className="mt-4 rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                    <p className="text-[11px] uppercase tracking-[0.25em] text-slate-400">Guia</p>
                    <p className="mt-2 text-sm text-white">
                      {formatEmpty(analiticoPreview.conciliacao.linhas[0]?.numero_guia_operadora)}
                    </p>
                  </div>
                </article>
              </div>

              <div className="grid gap-6 xl:grid-cols-2">
                <div className="overflow-hidden rounded-3xl border border-white/10">
                  <table className="w-full border-collapse text-left text-sm">
                    <thead className="bg-white/5 text-xs uppercase tracking-[0.25em] text-slate-400">
                      <tr>
                        <th className="px-4 py-3">Guia</th>
                        <th className="px-4 py-3">Realização</th>
                        <th className="px-4 py-3">Descrição</th>
                        <th className="px-4 py-3">Valor</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-white/10 bg-slate-950/30">
                      {analiticoPreviewRows.map((linha) => (
                        <tr key={linha.linha}>
                          <td className="px-4 py-4 text-slate-200">
                            {formatEmpty(linha.numero_guia_operadora)}
                          </td>
                          <td className="px-4 py-4 text-slate-200">
                            {formatEmpty(linha.data_realizacao)}
                          </td>
                          <td className="px-4 py-4 text-slate-200">
                            {formatEmpty(linha.descricao_procedimento)}
                          </td>
                          <td className="px-4 py-4 text-slate-200">{formatEmpty(linha.valor)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                <div className="overflow-hidden rounded-3xl border border-white/10">
                  <table className="w-full border-collapse text-left text-sm">
                    <thead className="bg-white/5 text-xs uppercase tracking-[0.25em] text-slate-400">
                      <tr>
                        <th className="px-4 py-3">Guia</th>
                        <th className="px-4 py-3">Data</th>
                        <th className="px-4 py-3">Tipo</th>
                        <th className="px-4 py-3">Motivo</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-white/10 bg-slate-950/30">
                      {glosaPreviewRows.map((linha) => (
                        <tr key={linha.linha}>
                          <td className="px-4 py-4 text-slate-200">
                            {formatEmpty(linha.numero_guia_operadora)}
                          </td>
                          <td className="px-4 py-4 text-slate-200">
                            {formatEmpty(linha.data_realizacao)}
                          </td>
                          <td className="px-4 py-4 text-slate-200">{formatEmpty(linha.tipo)}</td>
                          <td className="px-4 py-4 text-slate-200">{formatEmpty(linha.motivo)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>

              <div className="overflow-hidden rounded-3xl border border-white/10">
                <table className="w-full border-collapse text-left text-sm">
                  <thead className="bg-white/5 text-xs uppercase tracking-[0.25em] text-slate-400">
                    <tr>
                      <th className="px-4 py-3">Origem</th>
                      <th className="px-4 py-3">Guia</th>
                      <th className="px-4 py-3">Natureza</th>
                      <th className="px-4 py-3">Valor</th>
                      <th className="px-4 py-3">Motivo</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-white/10 bg-slate-950/30">
                    {conciliacaoPreviewRows.map((linha) => (
                      <tr key={`${linha.origem}-${linha.linha ?? linha.chave_conciliacao}`}>
                        <td className="px-4 py-4 text-slate-200">{linha.origem}</td>
                        <td className="px-4 py-4 text-slate-200">
                          {formatEmpty(linha.numero_guia_operadora)}
                        </td>
                        <td className="px-4 py-4 text-slate-200">{linha.natureza}</td>
                        <td className="px-4 py-4 text-slate-200">
                          {formatEmpty(linha.valor_normalizado)}
                        </td>
                        <td className="px-4 py-4 text-slate-200">{formatEmpty(linha.motivo)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          ) : (
            <div className="mt-6 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
              O analítico aparece aqui depois da primeira importação do Excel e já fica salvo como
              lote para conferência.
            </div>
          )}
        </section>

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
                  onClick={() => setIsFormOpen(false)}
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
      </div>

      <section className="hidden print:block space-y-6 rounded-[1.75rem] border border-slate-400 bg-white p-8 text-slate-950">
        <div className="flex items-start justify-between gap-6">
          <div>
            <p className="text-xs uppercase tracking-[0.3em] text-slate-500">Registro de Sessões</p>
            <h2 className="mt-2 text-3xl font-semibold text-slate-950">Modelo em branco</h2>
            <p className="mt-2 text-sm text-slate-600">
              Formulário para impressão e preenchimento manual antes da transcrição.
            </p>
          </div>
          <div className="text-right text-sm text-slate-700">
            <p>Guia Nº __________________</p>
            <p className="mt-1">Clínica __________________</p>
            <p className="mt-1">Paciente __________________</p>
            <p className="mt-1">Cartão __________________</p>
          </div>
        </div>

        <div className="grid gap-4 md:grid-cols-2">
          <div className="rounded-2xl border border-slate-300 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-500">Profissional executante</p>
            <div className="mt-3 h-10 border-b border-slate-300" />
          </div>
          <div className="rounded-2xl border border-slate-300 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-500">Terapia aplicada</p>
            <div className="mt-3 h-10 border-b border-slate-300" />
          </div>
        </div>

        <table className="w-full border-collapse text-left text-sm">
          <thead>
            <tr className="border-b border-slate-400 text-xs uppercase tracking-[0.25em] text-slate-500">
              <th className="py-3 pr-3">#</th>
              <th className="py-3 pr-3">Data</th>
              <th className="py-3 pr-3">Hora início</th>
              <th className="py-3 pr-3">Hora fim</th>
              <th className="py-3 pr-3">Acompanhante</th>
              <th className="py-3 pr-3">Resumo das atividades</th>
            </tr>
          </thead>
          <tbody>
            {blankRows.map((row) => (
              <tr key={row} className="border-b border-slate-200">
                <td className="py-4 pr-3 font-medium">{row}</td>
                <td className="py-4 pr-3">__________________</td>
                <td className="py-4 pr-3">__________________</td>
                <td className="py-4 pr-3">__________________</td>
                <td className="py-4 pr-3">__________________</td>
                <td className="py-4 pr-3">____________________________________________</td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>
    </>
  )
}
