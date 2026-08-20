import { useMemo, useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { getHttpErrorMessage, useImportarAnaliticoUnimed } from '../lancamentos/useLancamentos'
import type {
  AnaliticoUnimedConciliacaoLinha,
  AnaliticoUnimedGlosaLinha,
  AnaliticoUnimedLinha,
  AnaliticoUnimedPreview,
} from '../lancamentos/types'
import { useAnaliticosLotes, type AnaliticoLoteFilters } from './useAnaliticos'
import { Tooltip } from '../../components/ui/Tooltip'
import { Botao } from '../../components/ui/Botao'

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

function cell(value: string | null | undefined) {
  return <span className="text-slate-200">{formatEmpty(value)}</span>
}

function AnaliticoTable({ rows }: { rows: AnaliticoUnimedLinha[] }) {
  return (
    <article className="overflow-hidden rounded-3xl border border-white/10 bg-white/5">
      <div className="border-b border-white/10 px-4 py-3">
        <h4 className="text-sm font-semibold uppercase tracking-[0.25em] text-cyan-100">Analítico</h4>
      </div>
      <table className="w-full border-collapse text-left text-sm">
        <thead className="bg-fundo text-xs uppercase tracking-[0.25em] text-texto-suave">
          <tr>
            <th className="px-4 py-3">Guia</th>
            <th className="px-4 py-3">Realização</th>
            <th className="px-4 py-3">Descrição</th>
            <th className="px-4 py-3">Qtd.</th>
            <th className="px-4 py-3">Valor</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-linha bg-superficie">
          {rows.map((linha) => (
            <tr key={linha.linha}>
              <td className="px-4 py-4">{cell(linha.numero_guia_operadora)}</td>
              <td className="px-4 py-4">{cell(linha.data_realizacao)}</td>
              <td className="px-4 py-4">{cell(linha.descricao_procedimento)}</td>
              <td className="px-4 py-4">{cell(linha.qtd)}</td>
              <td className="px-4 py-4">{cell(linha.valor)}</td>
            </tr>
          ))}
          {rows.length === 0 ? (
            <tr>
              <td colSpan={5} className="px-4 py-6 text-center text-slate-300">
                Nenhuma linha encontrada.
              </td>
            </tr>
          ) : null}
        </tbody>
      </table>
    </article>
  )
}

function GlosaTable({ rows }: { rows: AnaliticoUnimedGlosaLinha[] }) {
  return (
    <article className="overflow-hidden rounded-3xl border border-white/10 bg-white/5">
      <div className="border-b border-white/10 px-4 py-3">
        <h4 className="text-sm font-semibold uppercase tracking-[0.25em] text-cyan-100">Glosas</h4>
      </div>
      <table className="w-full border-collapse text-left text-sm">
        <thead className="bg-fundo text-xs uppercase tracking-[0.25em] text-texto-suave">
          <tr>
            <th className="px-4 py-3">Guia</th>
            <th className="px-4 py-3">Realização</th>
            <th className="px-4 py-3">Motivo</th>
            <th className="px-4 py-3">Valor</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-linha bg-superficie">
          {rows.map((linha) => (
            <tr key={linha.linha}>
              <td className="px-4 py-4">{cell(linha.numero_guia_operadora)}</td>
              <td className="px-4 py-4">{cell(linha.data_realizacao)}</td>
              <td className="px-4 py-4">{cell(linha.motivo)}</td>
              <td className="px-4 py-4">{cell(linha.valor)}</td>
            </tr>
          ))}
          {rows.length === 0 ? (
            <tr>
              <td colSpan={4} className="px-4 py-6 text-center text-slate-300">
                Nenhuma glosa encontrada.
              </td>
            </tr>
          ) : null}
        </tbody>
      </table>
    </article>
  )
}

function ConciliacaoTable({ rows }: { rows: AnaliticoUnimedConciliacaoLinha[] }) {
  return (
    <article className="overflow-hidden rounded-3xl border border-white/10 bg-white/5">
      <div className="border-b border-white/10 px-4 py-3">
        <h4 className="text-sm font-semibold uppercase tracking-[0.25em] text-cyan-100">
          Conciliação
        </h4>
      </div>
      <table className="w-full border-collapse text-left text-sm">
        <thead className="bg-fundo text-xs uppercase tracking-[0.25em] text-texto-suave">
          <tr>
            <th className="px-4 py-3">Origem</th>
            <th className="px-4 py-3">Guia</th>
            <th className="px-4 py-3">Natureza</th>
            <th className="px-4 py-3">Valor</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-linha bg-superficie">
          {rows.map((linha) => (
            <tr key={`${linha.origem}-${linha.linha ?? linha.chave_conciliacao}`}>
              <td className="px-4 py-4 text-slate-200">{linha.origem}</td>
              <td className="px-4 py-4 text-slate-200">{cell(linha.numero_guia_operadora)}</td>
              <td className="px-4 py-4 text-slate-200">{linha.natureza}</td>
              <td className="px-4 py-4 text-slate-200">{cell(linha.valor_normalizado)}</td>
            </tr>
          ))}
          {rows.length === 0 ? (
            <tr>
              <td colSpan={4} className="px-4 py-6 text-center text-slate-300">
                Nenhuma linha conciliável encontrada.
              </td>
            </tr>
          ) : null}
        </tbody>
      </table>
    </article>
  )
}

function LotesTable({
  rows,
  isLoading,
  isError,
  filtros,
  onFiltroChange,
  onLimparFiltros,
}: {
  rows: Array<{
    id: number
    arquivo_nome_original: string
    status: string
    importado_em: string | null
    total_linhas_analitico: number
    total_linhas_glosa: number
    total_linhas_conciliacao: number
    total_pago: string
    total_glosado: string
    saldo_total: string
  }>
  isLoading: boolean
  isError: boolean
  filtros: AnaliticoLoteFilters
  onFiltroChange: (campo: keyof AnaliticoLoteFilters, valor: string) => void
  onLimparFiltros: () => void
}) {
  return (
    <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
      <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h3 className="text-lg font-semibold text-white">Lotes importados</h3>
        </div>
      </div>

      <form
        className="mt-4 grid gap-4 rounded-3xl border border-white/10 bg-white/5 p-4 xl:grid-cols-4"
        onSubmit={(event) => event.preventDefault()}
      >
        <label className="space-y-2">
          <span className="text-xs uppercase tracking-[0.2em] text-slate-400">Texto</span>
          <input
            type="text"
            value={filtros.busca}
            onChange={(event) => onFiltroChange('busca', event.target.value)}
            placeholder="Nome do arquivo"
            className="w-full rounded-2xl border border-white/10 bg-slate-950/50 px-4 py-3 text-sm text-slate-100 placeholder:text-slate-500"
          />
        </label>
        <label className="space-y-2">
          <span className="flex items-center gap-1 text-xs uppercase tracking-[0.2em] text-slate-400">
            Status
            <Tooltip rotulo="Valores aceitos">
              Campo de texto livre, não uma lista. Digite parte do status do lote — por exemplo
              &quot;importado&quot; — para filtrar.
            </Tooltip>
          </span>
          <input
            type="text"
            value={filtros.status}
            onChange={(event) => onFiltroChange('status', event.target.value)}
            placeholder="importado"
            className="w-full rounded-2xl border border-white/10 bg-slate-950/50 px-4 py-3 text-sm text-slate-100 placeholder:text-slate-500"
          />
        </label>
        <label className="space-y-2">
          <span className="text-xs uppercase tracking-[0.2em] text-slate-400">Importado de</span>
          <input
            type="date"
            value={filtros.importado_de}
            onChange={(event) => onFiltroChange('importado_de', event.target.value)}
            className="w-full rounded-2xl border border-white/10 bg-slate-950/50 px-4 py-3 text-sm text-slate-100"
          />
        </label>
        <label className="space-y-2">
          <span className="text-xs uppercase tracking-[0.2em] text-slate-400">Importado até</span>
          <input
            type="date"
            value={filtros.importado_ate}
            onChange={(event) => onFiltroChange('importado_ate', event.target.value)}
            className="w-full rounded-2xl border border-white/10 bg-slate-950/50 px-4 py-3 text-sm text-slate-100"
          />
        </label>
        <div className="xl:col-span-4 flex flex-wrap gap-3">
          <Botao type="button" variante="secundario" onClick={onLimparFiltros}>
            Limpar filtros
          </Botao>
        </div>
      </form>

      {isLoading ? (
        <div className="mt-4 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
          Carregando lotes salvos...
        </div>
      ) : isError ? (
        <div className="mt-4 rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-sm text-rose-100">
          Não foi possível carregar os lotes importados.
        </div>
      ) : (
        <div className="mt-4 overflow-hidden rounded-3xl border border-white/10">
          <table className="w-full border-collapse text-left text-sm">
            <thead className="bg-fundo text-xs uppercase tracking-[0.25em] text-texto-suave">
              <tr>
                <th className="px-4 py-3">Arquivo</th>
                <th className="px-4 py-3">Importado em</th>
                <th className="px-4 py-3">Linhas</th>
                <th className="px-4 py-3">Totais</th>
                <th className="px-4 py-3">Ações</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-linha bg-superficie">
              {rows.map((lote) => (
                <tr key={lote.id}>
                  <td className="px-4 py-4">
                    <div className="font-medium text-white">#{lote.id}</div>
                    <div className="text-xs text-slate-400">{lote.arquivo_nome_original}</div>
                    <div className="mt-1 text-xs text-slate-500">{lote.status}</div>
                  </td>
                  <td className="px-4 py-4 text-slate-200">
                    {lote.importado_em ? new Date(lote.importado_em).toLocaleString('pt-BR') : '—'}
                  </td>
                  <td className="px-4 py-4 text-slate-200">
                    <div>Analítico: {lote.total_linhas_analitico}</div>
                    <div>Glosa: {lote.total_linhas_glosa}</div>
                    <div>Conciliação: {lote.total_linhas_conciliacao}</div>
                  </td>
                  <td className="px-4 py-4 text-slate-200">
                    <div>Pago: {lote.total_pago}</div>
                    <div>Glosado: {lote.total_glosado}</div>
                    <div>Saldo: {lote.saldo_total}</div>
                  </td>
                  <td className="px-4 py-4">
                    <Link
                      to={`/analiticos/${lote.id}`}
                      className="inline-flex rounded-2xl border border-cyan-300/30 bg-cyan-400/10 px-4 py-2 text-sm font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
                    >
                      Abrir
                    </Link>
                  </td>
                </tr>
              ))}
              {rows.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-4 py-8 text-center text-slate-300">
                    Nenhum lote importado encontrado.
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}

export function AnaliticosPage() {
  const [arquivo, setArquivo] = useState<File | null>(null)
  const [preview, setPreview] = useState<AnaliticoUnimedPreview | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [salvo, setSalvo] = useState(false)
  const [filtros, setFiltros] = useState<AnaliticoLoteFilters>({
    busca: '',
    status: '',
    importado_de: '',
    importado_ate: '',
  })
  const importarAnalitico = useImportarAnaliticoUnimed()
  const lotesQuery = useAnaliticosLotes(filtros)

  const analiticoRows = useMemo(() => preview?.analitico.linhas.slice(0, 8) ?? [], [preview])
  const glosaRows = useMemo(() => preview?.glosas.linhas.slice(0, 8) ?? [], [preview])
  const conciliacaoRows = useMemo(() => preview?.conciliacao.linhas.slice(0, 8) ?? [], [preview])

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setError(null)
    setSalvo(false)

    if (!arquivo) {
      setError('Selecione um arquivo Excel para importar.')
      return
    }

    try {
      const result = await importarAnalitico.mutateAsync(arquivo)
      setPreview(result)
    } catch (mutationError) {
      setError(getHttpErrorMessage(mutationError, 'Não foi possível ler o analítico da Unimed.'))
    }
  }

  const handleSalvar = () => {
    if (!preview) {
      return
    }

    setSalvo(true)
    void lotesQuery.refetch()
  }

  const handleRecusar = () => {
    setArquivo(null)
    setPreview(null)
    setError(null)
    setSalvo(false)
  }

  const handleFiltroChange = (campo: keyof AnaliticoLoteFilters, valor: string) => {
    setFiltros((current) => ({
      ...current,
      [campo]: valor,
    }))
  }

  const limparFiltros = () => {
    setFiltros({
      busca: '',
      status: '',
      importado_de: '',
      importado_ate: '',
    })
  }

  return (
    <div className="space-y-8" data-testid="analiticos-page">
      <section className="space-y-4">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Analíticos</p>
            <h2 className="mt-2 flex items-center gap-2 text-3xl font-semibold text-white">
              Importação e conferência
              <Tooltip rotulo="O que é um analítico">
                É o relatório que o convênio envia com o que foi pago e o que foi glosado (recusado)
                por guia. Importe aqui antes de gerar a conciliação financeira — o cálculo de
                pago/glosado/saldo por guia já aparece na pré-visualização.
              </Tooltip>
            </h2>
          </div>

          <Link
            to="/conciliacao"
            className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
          >
            Abrir conciliação
          </Link>
        </div>

        <div className="grid gap-4 md:grid-cols-3">
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Arquivo</p>
            <p className="mt-2 text-sm font-medium text-white">{arquivo?.name ?? 'Nenhum'}</p>
          </article>
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Planilhas</p>
            <p className="mt-2 text-2xl font-semibold text-white">{preview?.planilhas.length ?? 0}</p>
          </article>
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Status</p>
            <p className="mt-2 text-sm font-medium text-white">{salvo ? 'Lote salvo' : 'Aguardando'}</p>
            <p className="mt-1 text-xs text-slate-400">
              {preview?.lote.importado_em ? formatDateTime(preview.lote.importado_em) : '—'}
            </p>
          </article>
        </div>
      </section>

      <LotesTable
        rows={lotesQuery.data ?? []}
        isLoading={lotesQuery.isLoading}
        isError={lotesQuery.isError}
        filtros={filtros}
        onFiltroChange={handleFiltroChange}
        onLimparFiltros={limparFiltros}
      />

      <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="flex items-start justify-between gap-4">
            <div>
              <h3 className="text-lg font-semibold text-white">Importar analítico</h3>
            </div>
          </div>

          <label className="block space-y-2">
            <span className="flex items-center gap-1 text-sm font-medium text-slate-200">
              Arquivo Excel
              <Tooltip rotulo="Formato exigido">
                Envie o arquivo .xlsx/.xls no formato original exportado pelo convênio, sem colunas
                removidas ou reordenadas — é esse cabeçalho que identifica cada coluna. Uma
                planilha reformatada manualmente costuma vir vazia ou com valores trocados.
              </Tooltip>
            </span>
            <input
              type="file"
              accept=".xlsx,.xls"
              onChange={(event) => setArquivo(event.target.files?.[0] ?? null)}
              className="block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-200 file:mr-4 file:rounded-full file:border-0 file:bg-cyan-400 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-950"
              data-testid="analiticos-arquivo"
            />
          </label>

          {error ? (
            <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
              {error}
            </p>
          ) : null}

          <Botao
            type="submit"
            carregando={importarAnalitico.isPending}
            data-testid="analiticos-importar"
          >
            {importarAnalitico.isPending ? 'Lendo planilha...' : 'Importar analítico'}
          </Botao>
        </form>

        {preview ? (
          <div className="mt-6 space-y-6">
            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
              <article className="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Arquivo</p>
                <p className="mt-2 text-sm font-medium text-white">{preview.arquivo}</p>
              </article>
              {preview.planilhas.map((planilha) => (
                <article
                  key={planilha.nome}
                  className="rounded-2xl border border-white/10 bg-white/5 p-4"
                >
                  <p className="text-xs uppercase tracking-[0.25em] text-slate-400">{planilha.nome}</p>
                  <p className="mt-2 text-2xl font-semibold text-white">{planilha.linhas}</p>
                </article>
              ))}
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
              <article className="rounded-3xl border border-white/10 bg-white/5 p-4">
                <h4 className="text-base font-semibold text-white">Cabeçalho</h4>
                <div className="mt-4 grid gap-3 md:grid-cols-2">
                  {Object.entries(preview.analitico.cabecalho).map(([key, value]) => (
                    <div key={key} className="rounded-2xl border border-white/10 bg-slate-950/40 p-3">
                      <p className="text-[11px] uppercase tracking-[0.25em] text-slate-400">{key}</p>
                      <p className="mt-2 text-sm text-white">{formatEmpty(value.raw)}</p>
                    </div>
                  ))}
                </div>
                <div className="mt-4 grid gap-3 md:grid-cols-2">
                  <div className="rounded-2xl border border-white/10 bg-slate-950/40 p-3">
                    <p className="text-[11px] uppercase tracking-[0.25em] text-slate-400">
                      Total prestador
                    </p>
                    <p className="mt-2 text-sm text-white">
                      {formatEmpty(preview.analitico.totais.prestador)}
                    </p>
                  </div>
                  <div className="rounded-2xl border border-white/10 bg-slate-950/40 p-3">
                    <p className="text-[11px] uppercase tracking-[0.25em] text-slate-400">
                      Total lote
                    </p>
                    <p className="mt-2 text-sm text-white">
                      {formatEmpty(preview.analitico.totais.lote)}
                    </p>
                  </div>
                </div>
              </article>

              <article className="rounded-3xl border border-white/10 bg-white/5 p-4">
                <h4 className="text-base font-semibold text-white">Totais</h4>
                <div className="mt-4 grid gap-3 md:grid-cols-3">
                  <div className="rounded-2xl border border-white/10 bg-slate-950/40 p-3">
                    <p className="text-[11px] uppercase tracking-[0.25em] text-slate-400">Pago</p>
                    <p className="mt-2 text-sm text-white">
                      {formatEmpty(preview.conciliacao.totais.pago)}
                    </p>
                  </div>
                  <div className="rounded-2xl border border-white/10 bg-slate-950/40 p-3">
                    <p className="text-[11px] uppercase tracking-[0.25em] text-slate-400">Glosado</p>
                    <p className="mt-2 text-sm text-white">
                      {formatEmpty(preview.conciliacao.totais.glosado)}
                    </p>
                  </div>
                  <div className="rounded-2xl border border-white/10 bg-slate-950/40 p-3">
                    <p className="text-[11px] uppercase tracking-[0.25em] text-slate-400">Saldo</p>
                    <p className="mt-2 text-sm text-white">
                      {formatEmpty(preview.conciliacao.totais.saldo)}
                    </p>
                  </div>
                </div>
                <div className="mt-4 rounded-2xl border border-cyan-400/20 bg-cyan-500/10 p-4 text-sm text-cyan-50">
                  {preview.conciliacao.resumo_por_guia.length} guia(s) normalizada(s) para conferência.
                </div>
              </article>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
              <AnaliticoTable rows={analiticoRows} />
              <GlosaTable rows={glosaRows} />
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
              <ConciliacaoTable rows={conciliacaoRows} />
              <article className="rounded-3xl border border-white/10 bg-white/5 p-4">
                <h4 className="text-base font-semibold text-white">Revisão final</h4>
                <p className="mt-2 flex items-start gap-1 text-sm text-slate-300">
                  <span>
                    Use o botão salvar para manter a revisão atual ou recusar para limpar a
                    visualização e iniciar outra importação.
                  </span>
                  <Tooltip rotulo="O que já aconteceu no servidor">
                    O lote já foi gravado durante a importação. &quot;Salvar lote&quot; só confirma
                    visualmente que foi conferido; &quot;Recusar&quot; apenas limpa esta tela, sem
                    desfazer nada no servidor.
                  </Tooltip>
                </p>

                <div className="mt-6 flex flex-wrap gap-3">
                  <button
                    type="button"
                    onClick={handleSalvar}
                    className="rounded-2xl bg-emerald-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-emerald-300"
                    data-testid="analiticos-salvar"
                  >
                    {salvo ? 'Lote salvo' : 'Salvar lote'}
                  </button>
                  <Botao
                    type="button"
                    variante="secundario"
                    onClick={handleRecusar}
                    data-testid="analiticos-recusar"
                  >
                    Recusar
                  </Botao>
                </div>

                {salvo ? (
                  <p className="mt-4 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                    Lote mantido para conferência e conciliação.
                  </p>
                ) : null}
              </article>
            </div>
          </div>
        ) : (
          <div className="mt-6 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
            O analítico aparece aqui depois da importação do Excel e segue o modelo da planilha
            item3.3.xlsx.
          </div>
        )}
      </section>
    </div>
  )
}
