import { Link, useLocation, useParams } from 'react-router-dom'
import { useAnaliticoLote } from './useAnaliticos'

function formatEmpty(value: string | number | null | undefined) {
  return value === null || value === undefined || value === '' ? '—' : String(value)
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

function DetailTable({
  title,
  rows,
  columns,
}: {
  title: string
  rows: Array<Record<string, unknown>>
  columns: Array<{ key: string; label: string }>
}) {
  return (
    <article className="overflow-hidden rounded-3xl border border-white/10 bg-white/5">
      <div className="border-b border-white/10 px-4 py-3">
        <h3 className="text-corpo-lg font-semibold text-white">{title}</h3>
      </div>
      <table className="w-full border-collapse text-left text-corpo" data-cartoes="lg">
        <thead className="bg-fundo text-meta uppercase tracking-[0.25em] text-texto-suave">
          <tr>
            {columns.map((column) => (
              <th key={column.key} className="px-4 py-3">
                {column.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-linha bg-superficie">
          {rows.map((row, index) => (
            <tr key={`${title}-${index}`}>
              {columns.map((column) => (
                <td data-rotulo={column.label} key={column.key} className="px-4 py-4 text-slate-200">
                  {formatEmpty(row[column.key] as string | number | null | undefined)}
                </td>
              ))}
            </tr>
          ))}
          {rows.length === 0 ? (
            <tr>
              <td colSpan={columns.length} className="px-4 py-8 text-center text-slate-300">
                Nenhum registro encontrado.
              </td>
            </tr>
          ) : null}
        </tbody>
      </table>
    </article>
  )
}

export function AnaliticoDetalhePage() {
  const params = useParams()
  const location = useLocation()
  const voltarPara = (location.state as { from?: string } | null)?.from ?? '/analiticos'
  const loteId = params.id ? Number(params.id) : null
  const loteQuery = useAnaliticoLote(Number.isFinite(loteId ?? NaN) ? loteId : null)
  const data = loteQuery.data

  return (
    <div className="space-y-8" data-testid="analitico-detalhe-page">
      <section className="space-y-4">
        <div className="flex flex-col gap-4 sm:items-start lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="flex items-center gap-3">
              <Link
                to={voltarPara}
                className="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-corpo font-semibold text-white transition hover:bg-white/10"
              >
                Voltar
              </Link>
              <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Analíticos</p>
            </div>
            <h2 className="mt-2 text-display font-semibold text-white">
              Lote #{data?.lote.id ?? '—'}
            </h2>
          </div>
        </div>

        {loteQuery.isLoading ? (
          <div className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1 text-corpo text-slate-300">
            Carregando lote...
          </div>
        ) : loteQuery.isError ? (
          <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-corpo text-rose-100">
            Não foi possível carregar este lote.
          </div>
        ) : data ? (
          <>
            <div className="grid gap-4 md:grid-cols-3 lg:grid-cols-4">
              <article className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1">
                <p className="text-meta uppercase tracking-[0.25em] text-slate-400">Arquivo</p>
                <p className="mt-2 break-all text-corpo font-medium text-white">{data.lote.arquivo_nome_original}</p>
              </article>
              <article className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1">
                <p className="text-meta uppercase tracking-[0.25em] text-slate-400">Importado em</p>
                <p className="mt-2 break-all text-corpo font-medium text-white">
                  {formatDateTime(data.lote.importado_em)}
                </p>
              </article>
              <article className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1">
                <p className="text-meta uppercase tracking-[0.25em] text-slate-400">Status</p>
                <p className="mt-2 break-all text-corpo font-medium text-white">{data.lote.status}</p>
              </article>
              <article className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1">
                <p className="text-meta uppercase tracking-[0.25em] text-slate-400">Saldo</p>
                <p className="mt-2 break-all text-corpo font-medium text-white">{data.lote.saldo_total}</p>
              </article>
            </div>

            <div className="grid gap-4 md:grid-cols-3">
              <article className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1">
                <p className="text-meta uppercase tracking-[0.25em] text-slate-400">Linhas analítico</p>
                <p className="mt-2 text-titulo font-semibold text-white">
                  {data.analitico.totais.linhas}
                </p>
                <p className="mt-1 text-corpo text-slate-300">Total bruto: {data.analitico.totais.valor}</p>
              </article>
              <article className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1">
                <p className="text-meta uppercase tracking-[0.25em] text-slate-400">Linhas glosa</p>
                <p className="mt-2 text-titulo font-semibold text-white">{data.glosas.totais.linhas}</p>
                <p className="mt-1 text-corpo text-slate-300">Total glosado: {data.glosas.totais.valor}</p>
              </article>
              <article className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1">
                <p className="text-meta uppercase tracking-[0.25em] text-slate-400">Conciliação</p>
                <p className="mt-2 text-titulo font-semibold text-white">
                  {data.lote.total_linhas_conciliacao}
                </p>
                <p className="mt-1 text-corpo text-slate-300">
                  Pago {data.conciliacao.totais.pago} · Glosado {data.conciliacao.totais.glosado}
                </p>
              </article>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
              <DetailTable
                title="Linhas do analítico"
                rows={data.analitico.linhas}
                columns={[
                  { key: 'numero_guia_operadora', label: 'Guia' },
                  { key: 'data_realizacao', label: 'Realização' },
                  { key: 'descricao_procedimento', label: 'Descrição' },
                  { key: 'qtd', label: 'Qtd.' },
                  { key: 'valor_normalizado', label: 'Valor' },
                ]}
              />
              <DetailTable
                title="Linhas de glosa"
                rows={data.glosas.linhas}
                columns={[
                  { key: 'numero_guia_operadora', label: 'Guia' },
                  { key: 'data_realizacao', label: 'Realização' },
                  { key: 'motivo', label: 'Motivo' },
                  { key: 'valor_normalizado', label: 'Valor' },
                ]}
              />
            </div>

            <DetailTable
              title="Conciliação"
              rows={data.conciliacao.linhas}
              columns={[
                { key: 'origem', label: 'Origem' },
                { key: 'numero_guia_operadora', label: 'Guia' },
                { key: 'natureza', label: 'Natureza' },
                { key: 'valor_normalizado', label: 'Valor' },
                { key: 'chave_conciliacao', label: 'Chave' },
              ]}
            />
          </>
        ) : null}
      </section>
    </div>
  )
}
