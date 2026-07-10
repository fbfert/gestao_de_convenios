import { useNavigate, Outlet } from 'react-router-dom'
import { useLogout } from '../features/auth'
import { useAuthStore } from '../stores/authStore'

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
    <div className="min-h-screen text-slate-100">
      <header className="border-b border-white/10 bg-slate-950/60 backdrop-blur">
        <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-6 py-4">
          <div>
            <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">
              Gestão de Convênios
            </p>
            <h1 className="text-lg font-semibold text-white">
              Base autenticada pronta
            </h1>
          </div>

          <div className="flex items-center gap-4">
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
            >
              {logout.isPending ? 'Saindo...' : 'Sair'}
            </button>
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
