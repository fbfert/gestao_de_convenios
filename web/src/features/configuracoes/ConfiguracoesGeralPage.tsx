import { Link } from 'react-router-dom'
import { usePode } from '../../lib/permissoes'
import { configuracoesItems, filtrarItens } from '../../routes/navigation'
import { themeOptions, useThemeStore, type Theme } from '../../stores/themeStore'

/**
 * Miniatura de cada tema. Cores literais de propósito (não tokens): a
 * miniatura precisa mostrar os OUTROS temas também, não só o que está em
 * vigor — usar var(--color-*) faria as três amostras ficarem iguais.
 */
const previewsPorTema: Record<Theme, { fundo: string; textoPrincipal: string; acento: string }> = {
  'clinicas-claro': {
    fundo: 'linear-gradient(180deg, #faf9f7 0%, #f1efeb 100%)',
    textoPrincipal: '#211f1b',
    acento: '#16695f',
  },
  claro: {
    fundo: 'linear-gradient(180deg, #f7f9fc 0%, #eef2f7 100%)',
    textoPrincipal: '#1e293b',
    acento: '#0e7490',
  },
  escuro: {
    fundo: 'linear-gradient(180deg, #07111f 0%, #0f172a 100%)',
    textoPrincipal: '#e2e8f0',
    acento: '#22d3ee',
  },
}

/**
 * Tela de entrada de /configuracoes. Recebeu o que antes era a aba "Geral":
 * a escolha de tema fica no topo, e abaixo os cartoes explicam cada item do
 * submenu. As demais abas viraram rotas proprias (ConfiguracoesPage).
 */
export function ConfiguracoesGeralPage() {
  const theme = useThemeStore((state) => state.theme)
  const setTheme = useThemeStore((state) => state.setTheme)
  const pode = usePode()
  // Mesma regra do submenu: cartao so aparece para quem pode abrir a tela.
  const itens = filtrarItens(configuracoesItems, pode)

  return (
    <div className="space-y-6" data-testid="configuracoes-geral">
      <section className="space-y-2">
        <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Configurações</p>
        <h2 className="text-3xl font-semibold text-white">Ajustes do sistema</h2>
        <p className="max-w-3xl text-sm leading-6 text-slate-300">
          A aparência vale só para este navegador e muda na hora. As demais configurações são do
          tenant inteiro e estão no submenu de Configurações, no menu acima.
        </p>
      </section>

      <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
        <h3 className="text-lg font-semibold text-white">Aparência</h3>
        <p className="mt-2 text-sm text-slate-300">
          Escolha o tema visual do sistema. A preferência vale para este navegador e é aplicada
          imediatamente.
        </p>

        <div className="mt-5 grid gap-3 sm:grid-cols-3">
          {themeOptions.map((option) => {
            const isActive = theme === option.value
            const preview = previewsPorTema[option.value]

            return (
              <button
                key={option.value}
                type="button"
                onClick={() => setTheme(option.value)}
                aria-pressed={isActive}
                className={`rounded-2xl border p-4 text-left transition ${
                  isActive
                    ? 'border-cyan-300/70 bg-cyan-400/10 ring-2 ring-cyan-300/20'
                    : 'border-white/10 bg-white/5 hover:bg-white/10'
                }`}
                data-testid={`configuracoes-tema-${option.value}`}
              >
                <span className="flex items-center justify-between gap-3">
                  <span className="text-sm font-semibold text-white">{option.label}</span>
                  {isActive ? (
                    <span className="rounded-full bg-cyan-400 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-slate-950">
                      Ativo
                    </span>
                  ) : null}
                </span>
                <span className="mt-2 block text-xs leading-5 text-slate-400">
                  {option.description}
                </span>
                <span
                  aria-hidden="true"
                  className="mt-3 flex h-12 items-center gap-2 overflow-hidden rounded-xl border border-white/10 px-3"
                  style={{ background: preview.fundo }}
                >
                  <span className="h-2 w-16 rounded-full" style={{ background: preview.textoPrincipal }} />
                  <span className="h-2 w-8 rounded-full" style={{ background: preview.acento }} />
                </span>
              </button>
            )
          })}
        </div>
      </section>

      <section className="space-y-4">
        <h3 className="text-lg font-semibold text-white">O que há em cada configuração</h3>

        <div className="grid gap-4 md:grid-cols-2">
          {itens.map((item) => (
            <Link
              key={item.to}
              to={item.to}
              className="group block rounded-janela border border-linha bg-fundo p-5 shadow-e1 transition hover:border-acento/40 hover:bg-superficie"
              data-testid={`configuracoes-card-${item.to.split('/').pop()}`}
            >
              <p className="text-base font-semibold text-white group-hover:text-cyan-50">
                {item.label}
              </p>
              <p className="mt-2 text-sm leading-6 text-slate-300">{item.descricao}</p>
            </Link>
          ))}
        </div>
      </section>
    </div>
  )
}
