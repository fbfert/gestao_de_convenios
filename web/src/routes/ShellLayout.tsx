import { useEffect, useRef, useState } from 'react'
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useLogout, useSessaoAtual } from '../features/auth'
import { usePode } from '../lib/permissoes'
import { useAuthStore } from '../stores/authStore'
import { isGroup, montarMenu, type NavGroup } from './navigation'

const linkBase = 'rounded-full border px-4 py-2 text-sm font-medium transition'
const linkAtivo = 'border-cyan-300/40 bg-cyan-400/15 text-cyan-50'
const linkInativo = 'border-white/10 bg-white/5 text-slate-200 hover:bg-white/10'

function classesLink(isActive: boolean) {
  return [linkBase, isActive ? linkAtivo : linkInativo].join(' ')
}

/**
 * Item de grupo: o rotulo navega para a tela de visao geral do grupo e a seta
 * abre o submenu. Sao dois alvos separados de proposito — clicar em "Cadastros"
 * precisa abrir a pagina de Cadastros, nao so revelar a lista.
 */
function GrupoNav({
  grupo,
  aberto,
  onAbrir,
  onFechar,
  onAlternar,
}: {
  grupo: NavGroup
  aberto: boolean
  onAbrir: () => void
  onFechar: () => void
  onAlternar: () => void
}) {
  const location = useLocation()
  const rotasFilhas = grupo.children.map((filho) => filho.to)
  const ativo =
    location.pathname === grupo.to ||
    rotasFilhas.some((rota) => location.pathname === rota || location.pathname.startsWith(`${rota}/`))

  return (
    <div className="relative" onMouseEnter={onAbrir} onMouseLeave={onFechar}>
      <div className={`flex items-stretch overflow-hidden rounded-full border ${ativo ? 'border-cyan-300/40' : 'border-white/10'}`}>
        <NavLink
          to={grupo.to}
          className={`px-4 py-2 text-sm font-medium transition ${
            ativo ? 'bg-cyan-400/15 text-cyan-50' : 'bg-white/5 text-slate-200 hover:bg-white/10'
          }`}
        >
          {grupo.label}
        </NavLink>

        <button
          type="button"
          onClick={onAlternar}
          aria-expanded={aberto}
          aria-haspopup="true"
          aria-label={`Abrir submenu de ${grupo.label}`}
          className={`border-l px-2 transition ${
            ativo
              ? 'border-cyan-300/30 bg-cyan-400/15 text-cyan-50 hover:bg-cyan-400/25'
              : 'border-white/10 bg-white/5 text-slate-300 hover:bg-white/10'
          }`}
          data-testid={`nav-grupo-${grupo.to.replace('/', '')}`}
        >
          <svg
            aria-hidden="true"
            viewBox="0 0 12 12"
            className={`h-3 w-3 transition-transform ${aberto ? 'rotate-180' : ''}`}
          >
            <path d="M2 4.5 6 8.5 10 4.5" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </button>
      </div>

      {aberto ? (
        /*
          O respiro entre o botao e o painel e `pt-2` deste involucro, nunca
          `mt-2` no painel: margem seria espaco vazio fora da area sensivel do
          grupo, e ao atravessa-la o ponteiro deixava de estar sobre o gatilho
          e sobre o painel ao mesmo tempo. O onMouseLeave disparava e o menu
          sumia antes de o clique chegar no item. Como padding, a faixa
          pertence ao involucro e a travessia continua dentro do grupo.
        */
        <div className="absolute left-0 top-full z-50 pt-2">
          <div
            role="menu"
            /* bg-slate-950 e opaco nos dois temas (no claro o token vira branco).
               Fundo translucido aqui deixava o submenu ilegivel sobre a pagina. */
            className="min-w-60 overflow-hidden rounded-2xl border border-white/10 bg-slate-950 p-1 shadow-2xl shadow-slate-950/40"
          >
            {grupo.children.map((filho) => (
              <NavLink
                key={`${grupo.to}${filho.to}`}
                to={filho.to}
                role="menuitem"
                className={({ isActive }) =>
                  [
                    'block rounded-xl px-3 py-2 text-sm transition',
                    isActive
                      ? 'bg-cyan-400/15 text-cyan-50'
                      : 'text-slate-200 hover:bg-white/10 hover:text-white',
                  ].join(' ')
                }
              >
                {filho.label}
              </NavLink>
            ))}
          </div>
        </div>
      ) : null}
    </div>
  )
}

export function ShellLayout() {
  const navigate = useNavigate()
  const location = useLocation()
  const user = useAuthStore((state) => state.user)
  const tenant = useAuthStore((state) => state.tenant)
  const logout = useLogout()
  const pode = usePode()
  // Reconsulta papel e permissoes: e o que faz uma mudanca de permissao valer
  // sem exigir que a pessoa saia e entre de novo.
  useSessaoAtual()
  const entradas = montarMenu(Boolean(user?.super_admin), pode)
  const [grupoAberto, setGrupoAberto] = useState<string | null>(null)
  const navRef = useRef<HTMLElement | null>(null)
  const timerFechar = useRef<number | null>(null)

  const cancelarFechamento = () => {
    if (timerFechar.current !== null) {
      window.clearTimeout(timerFechar.current)
      timerFechar.current = null
    }
  }

  const abrirGrupo = (to: string) => {
    cancelarFechamento()
    setGrupoAberto(to)
  }

  /*
    Fecha com um respiro. O painel e mais largo que o botao, entao um movimento
    na diagonal pode sair da area do grupo por um instante antes de entrar no
    item. Fechar na hora tornava esses itens praticamente inalcancaveis.
  */
  const fecharGrupo = () => {
    cancelarFechamento()
    timerFechar.current = window.setTimeout(() => {
      setGrupoAberto(null)
      timerFechar.current = null
    }, 180)
  }

  const alternarGrupo = (to: string) => {
    cancelarFechamento()
    setGrupoAberto((atual) => (atual === to ? null : to))
  }

  useEffect(() => cancelarFechamento, [])

  // Trocar de rota fecha o submenu: sem isso ele fica pendurado sobre a tela nova.
  useEffect(() => {
    cancelarFechamento()
    setGrupoAberto(null)
  }, [location.pathname])

  useEffect(() => {
    if (!grupoAberto) {
      return
    }

    const aoClicarFora = (evento: MouseEvent) => {
      if (navRef.current && !navRef.current.contains(evento.target as Node)) {
        setGrupoAberto(null)
      }
    }

    const aoTeclar = (evento: KeyboardEvent) => {
      if (evento.key === 'Escape') {
        setGrupoAberto(null)
      }
    }

    document.addEventListener('mousedown', aoClicarFora)
    document.addEventListener('keydown', aoTeclar)

    return () => {
      document.removeEventListener('mousedown', aoClicarFora)
      document.removeEventListener('keydown', aoTeclar)
    }
  }, [grupoAberto])

  const handleLogout = async () => {
    await logout.mutateAsync().catch(() => undefined)
    navigate('/login', { replace: true })
  }

  return (
    <div className="min-h-screen text-slate-100" data-testid="shell-layout">
      {/*
        `relative z-50` e o que mantem o submenu na frente do conteudo. O
        `backdrop-blur` daqui e o `backdrop-blur-sm` do card do <main> criam,
        cada um, um contexto de empilhamento proprio; sem z-index explicito
        vence a ordem do DOM, e o <main> vem depois. O z-50 do painel do
        submenu so vale dentro do header, entao precisa ser o header inteiro a
        subir.
      */}
      <header className="relative z-50 border-b border-white/10 bg-slate-950/60 backdrop-blur">
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
          <nav ref={navRef} className="flex flex-wrap items-center gap-2">
            {entradas.map((entry) =>
              isGroup(entry) ? (
                <GrupoNav
                  key={entry.to}
                  grupo={entry}
                  aberto={grupoAberto === entry.to}
                  onAbrir={() => abrirGrupo(entry.to)}
                  onFechar={fecharGrupo}
                  onAlternar={() => alternarGrupo(entry.to)}
                />
              ) : (
                <NavLink
                  key={entry.to}
                  to={entry.to}
                  className={({ isActive }) => classesLink(isActive)}
                >
                  {entry.label}
                </NavLink>
              ),
            )}
          </nav>

          <div className="flex items-center gap-4 self-end lg:self-auto">
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
      </header>

      <main className="relative z-0 mx-auto w-full max-w-6xl px-6 py-10">
        <div className="rounded-[2rem] border border-white/10 bg-white/5 p-8 shadow-2xl shadow-slate-950/40 backdrop-blur-sm">
          <Outlet />
        </div>
      </main>
    </div>
  )
}
