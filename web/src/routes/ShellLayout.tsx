import { useEffect, useRef, useState } from 'react'
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useLogout, useSessaoAtual } from '../features/auth'
import { usePode } from '../lib/permissoes'
import { useAuthStore } from '../stores/authStore'
import { Botao } from '../components/ui/Botao'
import { isGroup, montarMenu, type NavGroup } from './navigation'

const linkBase = 'rounded-pilula px-4 py-2 text-sm font-medium transition'
const linkAtivo = 'bg-acento-suave font-semibold text-acento-intenso'
const linkInativo = 'text-texto-suave hover:bg-fundo hover:text-texto'

/**
 * Telas de Cadastros, Operação e Auditoria são dominadas por uma tabela — o
 * usuário quer ver o máximo de colunas possível, e a largura central de
 * 1152px (max-w-6xl) sobra pouco em monitor grande. As demais telas
 * (Dashboard, Manual, Configurações...) continuam no container padrão: são
 * formulários e painéis pequenos, que ficariam com linhas de leitura enormes
 * se esticados sem necessidade.
 *
 * O prefixo cobre a família de rotas inteira (lista, novo, editar, detalhe),
 * não só a URL exata da listagem: trocar de largura ao entrar num formulário
 * da mesma tela pareceria quebrado.
 */
const LARGURA_AMPLA_PREFIXOS = [
  '/pacientes',
  '/solicitacoes',
  '/guias',
  '/lancamentos',
  '/antecipacoes',
  '/analiticos',
  '/conciliacao',
  '/profissionais',
  '/especialidades',
  '/cids',
  '/medicos',
  '/convenios',
  '/usuarios',
  '/auditoria',
]

function usaLarguraAmpla(pathname: string): boolean {
  return LARGURA_AMPLA_PREFIXOS.some(
    (prefixo) => pathname === prefixo || pathname.startsWith(`${prefixo}/`),
  )
}

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
      <div className={`flex items-stretch overflow-hidden rounded-pilula ${ativo ? 'bg-acento-suave' : ''}`}>
        <NavLink
          to={grupo.to}
          title={grupo.descricao}
          className={`px-4 py-2 text-sm font-medium transition ${
            ativo ? 'font-semibold text-acento-intenso' : 'text-texto-suave hover:bg-fundo hover:text-texto'
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
          className={`px-2 transition ${
            ativo
              ? 'font-semibold text-acento-intenso hover:bg-acento-suave'
              : 'text-texto-suave hover:bg-fundo hover:text-texto'
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
        <div className="absolute left-0 top-full z-(--z-menu) pt-2">
          <div
            role="menu"
            className="min-w-60 overflow-hidden rounded-superficie border border-linha bg-superficie-elevada p-1 shadow-e2"
          >
            {grupo.children.map((filho) => (
              <NavLink
                key={`${grupo.to}${filho.to}`}
                to={filho.to}
                role="menuitem"
                title={filho.descricao}
                className={({ isActive }) =>
                  [
                    'block rounded-controle px-3 py-2 text-sm transition',
                    isActive
                      ? 'bg-acento-suave font-semibold text-acento-intenso'
                      : 'text-texto-suave hover:bg-fundo hover:text-texto',
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
  const larguraAmpla = usaLarguraAmpla(location.pathname)
  const larguraContainer = larguraAmpla ? 'max-w-[1900px]' : 'max-w-6xl'
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
    <div className="min-h-screen bg-fundo text-texto" data-testid="shell-layout">
      {/*
        `relative z-(--z-fixo)` e o que mantem o submenu na frente do
        conteudo — sem z-index explicito vence a ordem do DOM, e o <main> vem
        depois. Header flat (sem blur/transparencia): o clinica nao usa
        glassmorphism, e o painel de submenu opaco precisava disso pra nao
        ficar ilegivel por cima da pagina.
      */}
      <header className="relative z-(--z-fixo) border-b border-linha bg-superficie">
        <div
          className={`mx-auto flex w-full flex-col gap-4 px-6 py-4 transition-[max-width] duration-200 lg:flex-row lg:items-center lg:justify-between ${larguraContainer}`}
        >
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
                  title={entry.descricao}
                  className={({ isActive }) => classesLink(isActive)}
                >
                  {entry.label}
                </NavLink>
              ),
            )}
          </nav>

          <div className="flex items-center gap-4 self-end lg:self-auto">
            <div className="text-right">
              <p className="text-sm font-medium text-texto">{user?.name}</p>
              <p className="text-xs text-texto-suave">
                {tenant?.nome} · {tenant?.slug}
              </p>
            </div>

            <Botao
              type="button"
              variante="secundario"
              tamanho="sm"
              onClick={handleLogout}
              carregando={logout.isPending}
              data-testid="shell-logout"
            >
              Sair
            </Botao>
          </div>
        </div>
      </header>

      {/* Nenhum painel envolvendo o conteudo — o clinica nao usa uma unica
          "moldura" gigante por cima de tudo; cada tela constroi seus proprios
          cards (rounded-janela/superficie) direto sobre o fundo da pagina. */}
      <main
        className={`relative z-0 mx-auto w-full px-6 py-10 transition-[max-width] duration-200 ${larguraContainer}`}
      >
        <Outlet />
      </main>
    </div>
  )
}
