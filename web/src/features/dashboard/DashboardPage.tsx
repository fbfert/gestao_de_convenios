import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { apiClient } from '../../api/client'

type DashboardBlock = {
  key: string
  permission: string
  label: string
  href: string | null
  value: number
  detail: string
}

type AuditItem = {
  id: number
  acao: string
  entidade: string
  entidade_id: number
  usuario: string | null
  created_at: string | null
}

type DashboardResponse = {
  blocks: DashboardBlock[]
  recent_audits: AuditItem[]
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

export function DashboardPage() {
  const dashboardQuery = useQuery({
    queryKey: ['dashboard'],
    queryFn: async () => (await apiClient.get<{ data: DashboardResponse }>('/dashboard')).data.data,
  })

  const blocks = dashboardQuery.data?.blocks ?? []
  const recentAudits = dashboardQuery.data?.recent_audits ?? []

  return (
    <div className="space-y-8" data-testid="dashboard-page">
      <section className="overflow-hidden rounded-[2rem] border border-cyan-300/20 bg-gradient-to-br from-cyan-400/10 via-slate-950 to-slate-900 p-6 shadow-2xl shadow-slate-950/30">
        <div className="space-y-4">
          <div className="space-y-4">
            <p className="text-xs uppercase tracking-[0.35em] text-cyan-300/80">Dashboard</p>
            <h2 className="text-4xl font-semibold text-white">Visão geral operacional</h2>
            <p className="max-w-3xl text-sm leading-6 text-slate-300">
              Este é o ponto de entrada do sistema. Os blocos abaixo refletem o que o seu papel
              pode ver, com atalhos para abrir as telas operacionais e um resumo da auditoria
              recente.
            </p>
          </div>
        </div>
      </section>

      {dashboardQuery.isLoading ? (
        <div className="rounded-[1.75rem] border border-white/10 bg-white/5 p-6 text-sm text-slate-300">
          Carregando Dashboard...
        </div>
      ) : dashboardQuery.isError ? (
        <div className="rounded-[1.75rem] border border-rose-400/20 bg-rose-500/10 p-6 text-sm text-rose-100">
          Não foi possível carregar o Dashboard.
        </div>
      ) : null}

      <section className="space-y-4">
        <div>
          <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Relatórios</p>
          <h3 className="mt-2 text-2xl font-semibold text-white">Resumo por área</h3>
        </div>

        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {blocks.map((block) =>
            block.href ? (
              <Link key={block.key} to={block.href} className={card}>
                <div className="flex h-full flex-col justify-between gap-4">
                  <div>
                    <p className="text-xs uppercase tracking-[0.25em] text-cyan-300/70">
                      {block.label}
                    </p>
                    <p className="mt-3 text-4xl font-semibold text-white">{block.value}</p>
                    <p className="mt-2 text-sm text-slate-300">{block.detail}</p>
                  </div>
                  <span className="text-sm font-medium text-cyan-200">Abrir {block.label}</span>
                </div>
              </Link>
            ) : (
              <article key={block.key} className={card}>
                <p className="text-xs uppercase tracking-[0.25em] text-cyan-300/70">{block.label}</p>
                <p className="mt-3 text-4xl font-semibold text-white">{block.value}</p>
                <p className="mt-2 text-sm text-slate-300">{block.detail}</p>
                <p className="mt-4 text-sm text-slate-400">Indicador sem tela própria.</p>
              </article>
            ),
          )}
        </div>
      </section>

      <section className="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <article className={card}>
          <div className="flex items-center justify-between gap-4">
            <div>
              <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Acesso rápido</p>
              <h3 className="mt-2 text-2xl font-semibold text-white">Telas operacionais</h3>
            </div>
            <Link
              to="/auditoria"
              className="rounded-full border border-cyan-300/30 px-4 py-2 text-sm font-semibold text-cyan-100 transition hover:bg-cyan-400/10"
            >
              Ver auditoria
            </Link>
          </div>

          <div className="mt-5 grid gap-3 sm:grid-cols-2">
            {[
              ['Solicitações', '/solicitacoes'],
              ['Pacientes', '/pacientes'],
              ['Guias', '/guias'],
              ['Sessões', '/lancamentos'],
              ['Antecipações', '/antecipacoes'],
              ['Analíticos', '/analiticos'],
              ['Conciliação', '/conciliacao'],
              ['Convênios', '/convenios'],
              ['Profissionais', '/profissionais'],
              ['Médicos', '/medicos'],
              ['Usuários', '/usuarios'],
              ['Configurações', '/configuracoes'],
            ].map(([label, href]) => (
              <Link
                key={href}
                to={href}
                className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-medium text-slate-100 transition hover:border-cyan-300/30 hover:bg-white/10"
              >
                {label}
              </Link>
            ))}
          </div>
        </article>

        <article className={card}>
          <div>
            <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Auditoria</p>
            <h3 className="mt-2 text-2xl font-semibold text-white">Últimas alterações</h3>
            <p className="mt-2 text-sm text-slate-300">
              Linha do tempo resumida das ações recentes do seu tenant.
            </p>
          </div>

          <div className="mt-5 space-y-3">
            {recentAudits.length === 0 ? (
              <p className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
                Nenhum evento de auditoria disponível para o seu perfil.
              </p>
            ) : (
              recentAudits.map((item) => (
                <div key={item.id} className="rounded-2xl border border-white/10 bg-slate-950/50 p-4">
                  <p className="text-sm font-medium text-white">
                    {item.usuario ?? 'Sistema'} · {item.acao}
                  </p>
                  <p className="mt-1 text-sm text-slate-300">
                    {item.entidade} #{item.entidade_id}
                  </p>
                  <p className="mt-2 text-xs text-slate-400">{formatDateTime(item.created_at)}</p>
                </div>
              ))
            )}
          </div>
        </article>
      </section>
    </div>
  )
}
