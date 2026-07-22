import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { apiClient } from '../../api/client'

type AuditItem = {
  id: number
  acao: string
  entidade: string
  entidade_id: number
  usuario: string | null
  payload: Record<string, unknown> | null
  created_at: string | null
}

const card = 'rounded-[1.75rem] border border-white/10 bg-white/5 p-5 shadow-xl shadow-slate-950/20'

function formatDateTime(value: string | null) {
  if (!value) {
    return '—'
  }

  return new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(value))
}

export function AuditoriaPage() {
  const auditQuery = useQuery({
    queryKey: ['auditoria'],
    queryFn: async () => (await apiClient.get<{ data: AuditItem[] }>('/auditoria')).data.data,
  })

  return (
    <div className="space-y-6" data-testid="auditoria-page">
      <div className="flex items-center justify-between gap-4">
        <div>
          <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Auditoria</p>
          <h2 className="mt-2 text-3xl font-semibold text-white">Linha do tempo de eventos</h2>
        </div>
        <Link to="/dashboard" className="text-sm font-medium text-cyan-200">
          ← Voltar ao Dashboard
        </Link>
      </div>

      {auditQuery.isLoading ? (
        <div className={card}>Carregando auditoria...</div>
      ) : auditQuery.isError ? (
        <div className="rounded-[1.75rem] border border-rose-400/20 bg-rose-500/10 p-6 text-sm text-rose-100">
          Não foi possível carregar a auditoria.
        </div>
      ) : null}

      <section className={card}>
        <p className="text-sm text-slate-300">
          O painel abaixo mostra os eventos mais recentes do tenant, úteis para revisão rápida de
          alterações críticas.
        </p>

        <div className="mt-5 space-y-3">
          {(auditQuery.data ?? []).length === 0 ? (
            <p className="rounded-2xl border border-white/10 bg-slate-950/50 p-4 text-sm text-slate-300">
              Nenhum evento registrado.
            </p>
          ) : (
            (auditQuery.data ?? []).map((item) => (
              <div key={item.id} className="rounded-2xl border border-white/10 bg-slate-950/50 p-4">
                <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                  <div>
                    <p className="text-sm font-medium text-white">
                      {item.usuario ?? 'Sistema'} · {item.acao}
                    </p>
                    <p className="mt-1 text-sm text-slate-300">
                      {item.entidade} #{item.entidade_id}
                    </p>
                  </div>
                  <p className="text-xs uppercase tracking-[0.25em] text-slate-400">
                    {formatDateTime(item.created_at)}
                  </p>
                </div>

                {item.payload ? (
                  <pre className="mt-3 overflow-auto rounded-2xl border border-white/10 bg-slate-950/80 p-3 text-xs text-slate-200">
                    {JSON.stringify(item.payload, null, 2)}
                  </pre>
                ) : null}
              </div>
            ))
          )}
        </div>
      </section>
    </div>
  )
}
