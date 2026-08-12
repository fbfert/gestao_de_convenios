import { Link } from 'react-router-dom'
import { configuracoesItems } from '../../routes/navigation'
import { themeOptions, useThemeStore } from '../../stores/themeStore'

/**
 * Tela de entrada de /configuracoes. Recebeu o que antes era a aba "Geral":
 * a escolha de tema fica no topo, e abaixo os cartoes explicam cada item do
 * submenu. As demais abas viraram rotas proprias (ConfiguracoesPage).
 */
export function ConfiguracoesGeralPage() {
  const theme = useThemeStore((state) => state.theme)
  const setTheme = useThemeStore((state) => state.setTheme)

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

      <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
        <h3 className="text-lg font-semibold text-white">Aparência</h3>
        <p className="mt-2 text-sm text-slate-300">
          Escolha o tema visual do sistema. A preferência vale para este navegador e é aplicada
          imediatamente.
        </p>

        <div className="mt-5 grid gap-3 sm:grid-cols-2">
          {themeOptions.map((option) => {
            const isActive = theme === option.value

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
                {/*
                  Miniatura do tema. As cores sao literais porque precisam
                  mostrar o tema oposto tambem: usar os tokens faria as duas
                  amostras ficarem iguais, sempre a do tema em vigor.
                */}
                <span
                  aria-hidden="true"
                  className="mt-3 flex h-12 items-center gap-2 overflow-hidden rounded-xl border border-white/10 px-3"
                  style={
                    option.value === 'claro'
                      ? { background: 'linear-gradient(180deg, #f7f9fc 0%, #eef2f7 100%)' }
                      : { background: 'linear-gradient(180deg, #07111f 0%, #0f172a 100%)' }
                  }
                >
                  <span
                    className="h-2 w-16 rounded-full"
                    style={{ background: option.value === 'claro' ? '#1e293b' : '#e2e8f0' }}
                  />
                  <span
                    className="h-2 w-8 rounded-full"
                    style={{ background: option.value === 'claro' ? '#0e7490' : '#22d3ee' }}
                  />
                </span>
              </button>
            )
          })}
        </div>
      </section>

      <section className="space-y-4">
        <h3 className="text-lg font-semibold text-white">O que há em cada configuração</h3>

        <div className="grid gap-4 md:grid-cols-2">
          {configuracoesItems.map((item) => (
            <Link
              key={item.to}
              to={item.to}
              className="group block rounded-[1.75rem] border border-white/10 bg-white/5 p-5 shadow-xl shadow-slate-950/20 transition hover:border-cyan-300/40 hover:bg-white/10"
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
