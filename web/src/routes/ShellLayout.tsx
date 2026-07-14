import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useLogout } from '../features/auth'
import { useAuthStore } from '../stores/authStore'

const navItems = [
  { to: '/solicitacoes', label: 'Solicitações' },
  { to: '/pacientes', label: 'Pacientes' },
  { to: '/guias', label: 'Guias' },
  { to: '/convenios', label: 'Convênios' },
  { to: '/antecipacoes', label: 'Antecipações' },
  { to: '/lancamentos', label: 'Lançamentos' },
  { to: '/conciliacao', label: 'Conciliação' },
  { to: '/medicos', label: 'Médicos' },
  { to: '/usuarios', label: 'Usuários' },
  { to: '/permissoes', label: 'Permissões' },
]

export function ShellLayout() {
  const navigate = useNavigate()
  const user = useAuthStore((state) => state.user)
  const tenant = useAuthStore((state) => state.tenant)
  const logout = useLogout()

  const handleLogout = async () => {
    await logout.mutateAsync().catch(() => undefined)
    navigate('/login', { replace: true })
  }

  return (
    <div className="min-h-screen text-slate-100" data-testid="shell-layout">
      <header className="border-b border-white/10 bg-slate-950/60 backdrop-blur">
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex items-center gap-4">
            <div className="grid h-12 w-12 place-items-center rounded-2xl border border-cyan-300/20 bg-cyan-400/10 text-sm font-semibold text-cyan-100">
              GC
            </div>
            <div>
              <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">
                Gestão de Convênios
              </p>
              <h1 className="text-lg font-semibold text-white">
                Base autenticada pronta
              </h1>
            </div>
          </div>

          <div className="flex flex-col gap-3 lg:items-end">
            <nav className="flex flex-wrap gap-2">
              {navItems.map((item) => (
                <NavLink
                  key={item.to}
                  to={item.to}
                  className={({ isActive }) =>
                    [
                      'rounded-full border px-4 py-2 text-sm font-medium transition',
                      isActive
                        ? 'border-cyan-300/40 bg-cyan-400/15 text-cyan-50'
                        : 'border-white/10 bg-white/5 text-slate-200 hover:bg-white/10',
                    ].join(' ')
                  }
                >
                  {item.label}
                </NavLink>
              ))}
            </nav>

            <div className="flex items-center gap-4 self-end">
              <div className="text-right">
                <p className="text-sm font-medium text-white">{user?.name}</p>
                <p className="text-xs text-slate-300">
                  {tenant?.nome} · {tenant?.slug}
                </p>
              </div>

              <button
                type="button"
                onClick={handleLogout}
                className="rounded-full border border-white/15 bg-white/5 px-4 py-2 text-sm font-medium text-white transition hover:bg-white/10 disabled:opacity-60"
                disabled={logout.isPending}
                data-testid="shell-logout"
              >
                {logout.isPending ? 'Saindo...' : 'Sair'}
              </button>
            </div>
          </div>
        </div>
      </header>

      <main className="mx-auto w-full max-w-6xl px-6 py-10">
        <div className="rounded-[2rem] border border-white/10 bg-white/5 p-8 shadow-2xl shadow-slate-950/40 backdrop-blur-sm">
          <Outlet />
        </div>
      </main>
    </div>
  )
}
