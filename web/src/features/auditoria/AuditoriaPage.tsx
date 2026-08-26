import { useMemo, useState, type FormEvent } from 'react'
import { Select } from '../../components/ui/Select'
import { Tooltip } from '../../components/ui/Tooltip'
import { Botao } from '../../components/ui/Botao'
import {
  exportarAuditoria,
  getHttpErrorMessage,
  useAuditoria,
  useAuditoriaOpcoes,
} from './useAuditoria'
import type { AuditFiltros, AuditItem, AuditPayload } from './types'

const card = 'rounded-janela border border-linha bg-superficie p-5 shadow-e1'
const campo =
  'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'

const filtrosVazios: AuditFiltros = {
  de: '',
  ate: '',
  usuario: '',
  autor: '',
  tipo: '',
  entidade: '',
  acao: '',
}

function formatarData(valor: string | null) {
  if (!valor) {
    return '—'
  }

  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'medium' }).format(
    new Date(valor),
  )
}

function formatarValor(valor: unknown) {
  if (valor === null || valor === undefined || valor === '') {
    return '(vazio)'
  }

  if (typeof valor === 'boolean') {
    return valor ? 'sim' : 'não'
  }

  if (typeof valor === 'object') {
    return JSON.stringify(valor)
  }

  return String(valor)
}

/**
 * Detalhe do evento.
 *
 * Com `antes`/`depois` vira tabela campo a campo; sem eles é um evento
 * explícito — login, importação, expurgo — e o payload aparece como está.
 */
function Detalhe({ payload }: { payload: AuditPayload | null }) {
  if (!payload) {
    return null
  }

  const { antes, depois, campos_ocultos: ocultos, ...resto } = payload
  const campos = Array.from(new Set([...Object.keys(depois ?? {}), ...Object.keys(antes ?? {})]))
  const extras = Object.entries(resto)

  return (
    <div className="mt-3 space-y-3">
      {campos.length > 0 ? (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[32rem] text-left text-meta" data-cartoes="md">
            <thead className="text-slate-400">
              <tr>
                <th className="pb-2 pr-4 font-medium">Campo</th>
                <th className="pb-2 pr-4 font-medium">Antes</th>
                <th className="pb-2 font-medium">Depois</th>
              </tr>
            </thead>
            <tbody className="align-top">
              {campos.map((nome) => (
                <tr key={nome} className="border-t border-white/5">
                  <td data-rotulo="Campo" className="py-2 pr-4 font-medium text-slate-200">{nome}</td>
                  <td data-rotulo="Antes" className="py-2 pr-4 text-rose-200">{formatarValor(antes?.[nome])}</td>
                  <td data-rotulo="Depois" className="py-2 text-emerald-200">{formatarValor(depois?.[nome])}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}

      {extras.length > 0 ? (
        <dl className="grid gap-1 text-meta sm:grid-cols-2">
          {extras.map(([chave, valor]) => (
            <div key={chave} className="flex gap-2">
              <dt className="text-slate-400">{chave}:</dt>
              <dd className="text-slate-200">{formatarValor(valor)}</dd>
            </div>
          ))}
        </dl>
      ) : null}

      {ocultos && ocultos.length > 0 ? (
        <p className="rounded-2xl border border-amber-300/20 bg-amber-400/10 px-3 py-2 text-meta text-amber-100">
          Campo sensível alterado, valor não registrado: {ocultos.join(', ')}
        </p>
      ) : null}
    </div>
  )
}

function Evento({ item }: { item: AuditItem }) {
  const [aberto, setAberto] = useState(false)

  return (
    <div
      className="rounded-2xl border border-white/10 bg-slate-950/50 p-4"
      data-testid="auditoria-evento"
    >
      <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
        <div>
          <p className="text-corpo font-medium text-white">
            {item.usuario ?? 'Sistema'} · {item.acao_label}
          </p>
          <p className="mt-1 text-corpo text-slate-300">
            {item.entidade_label} #{item.entidade_id}
            {item.ip ? <span className="text-slate-400"> · {item.ip}</span> : null}
          </p>
        </div>

        <div className="flex items-center gap-3">
          <p className="text-meta uppercase tracking-[0.25em] text-slate-400">
            {formatarData(item.created_at)}
          </p>
          {item.payload ? (
            <button
              type="button"
              onClick={() => setAberto((atual) => !atual)}
              className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-meta font-semibold text-white transition hover:bg-white/10"
              data-testid={`auditoria-detalhe-${item.id}`}
            >
              {aberto ? 'Ocultar' : 'O que mudou'}
            </button>
          ) : null}
        </div>
      </div>

      {aberto ? <Detalhe payload={item.payload} /> : null}
    </div>
  )
}

export function AuditoriaPage() {
  const [filtros, setFiltros] = useState<AuditFiltros>(filtrosVazios)
  const [rascunho, setRascunho] = useState<AuditFiltros>(filtrosVazios)
  const [pagina, setPagina] = useState(1)
  const [erro, setErro] = useState<string | null>(null)
  const [exportando, setExportando] = useState(false)

  const auditoria = useAuditoria(filtros, pagina)
  const opcoes = useAuditoriaOpcoes()

  const eventos = useMemo(() => auditoria.data?.data ?? [], [auditoria.data])
  // O seletor de acao mostra so o que pertence ao tipo escolhido.
  const acoesDisponiveis = useMemo(() => {
    const todas = opcoes.data?.acoes ?? []

    return rascunho.tipo ? todas.filter((acao) => acao.tipo === rascunho.tipo) : todas
  }, [opcoes.data, rascunho.tipo])
  const totalPaginas = auditoria.data?.meta?.last_page ?? 1
  const total = auditoria.data?.meta?.total ?? 0

  const aplicar = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setPagina(1)
    setFiltros(rascunho)
  }

  const limpar = () => {
    setRascunho(filtrosVazios)
    setFiltros(filtrosVazios)
    setPagina(1)
  }

  const exportar = async () => {
    setErro(null)
    setExportando(true)

    try {
      await exportarAuditoria(filtros)
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível exportar a auditoria.'))
    } finally {
      setExportando(false)
    }
  }

  return (
    <div className="space-y-6" data-testid="auditoria-page">
      <div className="flex flex-col gap-4 sm:items-start lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Auditoria</p>
          <h2 className="mt-2 flex items-center gap-2 text-display font-semibold text-white">
            Logs de auditoria
            {/* A promessa de que credencial nunca e gravada nao pode sumir junto
                com os textos decorativos: virou dica em vez de paragrafo. */}
            <Tooltip rotulo="O que a auditoria registra">
              <p className="font-semibold text-white">O que fica registrado</p>
              <p className="mt-1">
                Quem alterou o quê, quando e — nos eventos de acesso — a partir de onde.
              </p>
              <p className="mt-2">
                Senha e chave aparecem apenas como campo alterado. O valor nunca é registrado, nem o
                antigo nem o novo.
              </p>
            </Tooltip>
          </h2>
        </div>

        <button
          type="button"
          onClick={exportar}
          disabled={exportando || eventos.length === 0}
          className="inline-flex items-center justify-center rounded-2xl border border-cyan-300/30 bg-cyan-400/10 h-10 px-4 text-corpo font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:opacity-60"
          data-testid="auditoria-exportar"
        >
          {exportando ? 'Exportando...' : 'Exportar CSV'}
        </button>
      </div>

      <form onSubmit={aplicar} className={`${card} space-y-4`} data-testid="auditoria-filtros">
        <div className="grid gap-3 md:grid-cols-3 xl:grid-cols-4">
          <label className="space-y-1">
            <span className="text-meta text-slate-300">De</span>
            <input
              type="date"
              value={rascunho.de}
              onChange={(event) => setRascunho((atual) => ({ ...atual, de: event.target.value }))}
              className={campo}
              data-testid="auditoria-de"
            />
          </label>

          <label className="space-y-1">
            <span className="text-meta text-slate-300">Até</span>
            <input
              type="date"
              value={rascunho.ate}
              onChange={(event) => setRascunho((atual) => ({ ...atual, ate: event.target.value }))}
              className={campo}
            />
          </label>

          <label className="space-y-1">
            <span className="text-meta text-slate-300">Usuário</span>
            <input
              type="search"
              value={rascunho.usuario}
              onChange={(event) =>
                setRascunho((atual) => ({ ...atual, usuario: event.target.value }))
              }
              placeholder="Nome da pessoa"
              className={campo}
              data-testid="auditoria-usuario"
            />
          </label>

          <label className="space-y-1">
            <span className="text-meta text-slate-300">Autor</span>
            <Select
              value={rascunho.autor}
              onChange={(event) => setRascunho((atual) => ({ ...atual, autor: event.target.value }))}
            >
              <option value="">Todos</option>
              <option value="pessoas">Somente pessoas</option>
              <option value="sistema">Somente o sistema</option>
            </Select>
          </label>

          <label className="space-y-1">
            <span className="text-meta text-slate-300">Tipo de ação</span>
            <Select
              value={rascunho.tipo}
              onChange={(event) =>
                // Trocar de tipo limpa a acao: manter uma acao de outro tipo
                // deixaria o filtro pedindo um recorte impossivel.
                setRascunho((atual) => ({ ...atual, tipo: event.target.value, acao: '' }))
              }
              data-testid="auditoria-tipo"
            >
              <option value="">Todos</option>
              {(opcoes.data?.tipos ?? []).map((tipo) => (
                <option key={tipo.valor} value={tipo.valor}>
                  {tipo.rotulo}
                </option>
              ))}
            </Select>
          </label>

          <label className="space-y-1">
            <span className="text-meta text-slate-300">Entidade</span>
            <Select
              value={rascunho.entidade}
              onChange={(event) =>
                setRascunho((atual) => ({ ...atual, entidade: event.target.value }))
              }
            >
              <option value="">Todas</option>
              {(opcoes.data?.entidades ?? []).map((entidade) => (
                <option key={entidade.valor} value={entidade.valor}>
                  {entidade.rotulo}
                </option>
              ))}
            </Select>
          </label>

          <label className="space-y-1">
            <span className="text-meta text-slate-300">Ação</span>
            <Select
              value={rascunho.acao}
              onChange={(event) => setRascunho((atual) => ({ ...atual, acao: event.target.value }))}
            >
              <option value="">Todas</option>
              {acoesDisponiveis.map((acao) => (
                <option key={acao.valor} value={acao.valor}>
                  {acao.rotulo}
                </option>
              ))}
            </Select>
          </label>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <Botao type="submit" data-testid="auditoria-filtrar">Filtrar</Botao>
          <button type="button" onClick={limpar} className="inline-flex min-h-6 items-center text-corpo text-slate-300">
            Limpar
          </button>
          <span className="text-meta text-slate-400">
            {auditoria.isLoading ? 'Carregando...' : `${total} evento(s)`}
          </span>
        </div>
      </form>

      {erro ? (
        <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
          {erro}
        </p>
      ) : null}

      {auditoria.isError ? (
        <div className="rounded-janela border border-perigo/30 bg-perigo-suave p-6 text-corpo text-perigo-texto">
          Não foi possível carregar a auditoria.
        </div>
      ) : null}

      <section className={`${card} space-y-3`}>
        {eventos.length === 0 && !auditoria.isLoading ? (
          <p className="rounded-2xl border border-white/10 bg-slate-950/50 p-4 text-corpo text-slate-300">
            Nenhum evento para os filtros escolhidos.
          </p>
        ) : (
          eventos.map((item) => <Evento key={item.id} item={item} />)
        )}
      </section>

      {totalPaginas > 1 ? (
        <div className="flex items-center justify-between gap-4">
          <button
            type="button"
            onClick={() => setPagina((atual) => Math.max(1, atual - 1))}
            disabled={pagina <= 1}
            className="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-corpo text-white transition hover:bg-white/10 disabled:opacity-40"
          >
            Anterior
          </button>

          <span className="inline-flex min-h-6 items-center text-corpo text-slate-300">
            Página {pagina} de {totalPaginas}
          </span>

          <button
            type="button"
            onClick={() => setPagina((atual) => Math.min(totalPaginas, atual + 1))}
            disabled={pagina >= totalPaginas}
            className="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-corpo text-white transition hover:bg-white/10 disabled:opacity-40"
          >
            Próxima
          </button>
        </div>
      ) : null}
    </div>
  )
}
