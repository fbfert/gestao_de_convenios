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

const card = 'rounded-janela border border-linha bg-fundo p-5 shadow-e1'

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
    // O app desliga refetchOnWindowFocus globalmente (App.tsx) — sem isso os
    // números deste painel ficam parados até um F5 manual. Aqui religamos e
    // ainda somamos um polling curto, já que é uma tela de visão geral que
    // as pessoas costumam deixar aberta.
    refetchOnWindowFocus: true,
    refetchInterval: 30000,
  })

  /*
    Usuários sai do resumo para a grade fechar em múltiplo de 3 (12 blocos com
    o papel de administrador, três por linha em telas largas). O bloco continua
    vindo da API: a tela de Cadastros mostra essa mesma métrica, então filtrar
    aqui em vez de remover do DashboardController evita derrubá-la de lá junto.
    A tela de Usuários segue acessível pelo menu Cadastros.
  */
  const blocks = (dashboardQuery.data?.blocks ?? []).filter((block) => block.key !== 'usuarios')
  const recentAudits = dashboardQuery.data?.recent_audits ?? []

  return (
    <div className="space-y-8" data-testid="dashboard-page">
      <section className="overflow-hidden rounded-janela border border-cyan-300/20 bg-gradient-to-br from-cyan-400/10 via-slate-950 to-slate-900 p-6 shadow-e3 shadow-slate-950/30">
        <div className="space-y-4">
          <div className="space-y-4">
            <h2 className="text-display font-semibold text-white">Visão geral operacional</h2>
            <p className="max-w-3xl text-corpo leading-6 text-slate-300">
              Clique nos Atalhos ou Acesse pelo Menu Superior. Em caso de dúvidas acesse o{' '}
              <Link
                to="/manual"
                className="font-semibold text-cyan-200 underline underline-offset-2 transition hover:text-cyan-100"
              >
                Manual
              </Link>
              .
            </p>
          </div>
        </div>
      </section>

      {dashboardQuery.isLoading ? (
        <div className="rounded-janela border border-linha bg-fundo p-6 text-corpo text-texto-suave">
          Carregando Dashboard...
        </div>
      ) : dashboardQuery.isError ? (
        <div className="rounded-janela border border-perigo/30 bg-perigo-suave p-6 text-corpo text-perigo-texto">
          Não foi possível carregar o Dashboard.
        </div>
      ) : null}

      <section className="space-y-4">
        <div>
          <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Relatórios</p>
          <h3 className="mt-2 text-titulo font-semibold text-white">Resumo por área</h3>
        </div>

        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          {blocks.map((block) =>
            block.href ? (
              <Link
                key={block.key}
                to={block.href}
                className="rounded-janela border border-linha bg-fundo px-4 py-3 shadow-e1 transition hover:border-cyan-300/30"
              >
                <p className="text-meta uppercase tracking-[0.2em] text-cyan-300/70">{block.label}</p>
                <div className="mt-1 flex items-baseline justify-between gap-2">
                  <span className="text-display font-semibold text-white">{block.value}</span>
                  <span className="text-meta font-medium text-cyan-200">Abrir →</span>
                </div>
                <p className="mt-1 truncate text-meta text-slate-400" title={block.detail}>
                  {block.detail}
                </p>
              </Link>
            ) : (
              <article key={block.key} className="rounded-janela border border-linha bg-fundo px-4 py-3 shadow-e1">
                <p className="text-meta uppercase tracking-[0.2em] text-cyan-300/70">{block.label}</p>
                <p className="mt-1 text-display font-semibold text-white">{block.value}</p>
                <p className="mt-1 truncate text-meta text-slate-400" title={block.detail}>
                  {block.detail}
                </p>
              </article>
            ),
          )}
        </div>
      </section>

      <section className="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <article className={card}>
          <div className="flex items-center justify-between gap-4">
            <div>
              <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Acesso rápido</p>
              <h3 className="mt-2 text-titulo font-semibold text-white">Telas operacionais</h3>
            </div>
            <Link
              to="/auditoria"
              className="rounded-full border border-cyan-300/30 px-4 py-2 text-corpo font-semibold text-cyan-100 transition hover:bg-cyan-400/10"
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
                className="inline-flex items-center justify-center rounded-2xl border border-white/10 bg-white/5 h-10 px-4 text-corpo font-medium text-slate-100 transition hover:border-cyan-300/30 hover:bg-white/10"
              >
                {label}
              </Link>
            ))}
          </div>
        </article>

        <article className={card}>
          <div>
            <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Auditoria</p>
            <h3 className="mt-2 text-titulo font-semibold text-white">Últimas alterações</h3>
            <p className="mt-2 text-corpo text-slate-300">
              Linha do tempo resumida das ações recentes do seu tenant.
            </p>
          </div>

          <div className="mt-5 space-y-3">
            {recentAudits.length === 0 ? (
              <p className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1 text-corpo text-slate-300">
                Nenhum evento de auditoria disponível para o seu perfil.
              </p>
            ) : (
              recentAudits.map((item) => (
                <div key={item.id} className="rounded-2xl border border-white/10 bg-slate-950/50 p-4">
                  <p className="text-corpo font-medium text-white">
                    {item.usuario ?? 'Sistema'} · {item.acao}
                  </p>
                  <p className="mt-1 text-corpo text-slate-300">
                    {item.entidade} #{item.entidade_id}
                  </p>
                  <p className="mt-2 text-meta text-slate-400">{formatDateTime(item.created_at)}</p>
                </div>
              ))
            )}
          </div>
        </article>
      </section>
    </div>
  )
}
