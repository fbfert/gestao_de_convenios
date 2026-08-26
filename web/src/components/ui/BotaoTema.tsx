import { useTemaStore, temaOpcoes, type Tema } from '../../stores/temaStore'

/**
 * Alterna entre os dois temas, direto da barra superior.
 *
 * É um botão de duas posições, e não um menu: com dois temas, o menu esconderia
 * a escolha atrás de um clique a mais sem informar nada. `aria-pressed` diz o
 * estado para o leitor de tela, e o rótulo muda junto — quem não distingue as
 * cores precisa ler qual está em vigor, não deduzir pelo desenho.
 */
export function BotaoTema({ className = '' }: { className?: string }) {
  const tema = useTemaStore((estado) => estado.tema)
  const definirTema = useTemaStore((estado) => estado.definirTema)

  const proximo: Tema = tema === 'claro' ? 'contraste' : 'claro'
  const opcaoAtual = temaOpcoes.find((opcao) => opcao.value === tema)
  const opcaoProxima = temaOpcoes.find((opcao) => opcao.value === proximo)

  return (
    <button
      type="button"
      onClick={() => definirTema(proximo)}
      aria-pressed={tema === 'contraste'}
      title={`Tema ${opcaoAtual?.label}. Clique para usar ${opcaoProxima?.label}.`}
      data-testid="alternar-tema"
      className={`inline-flex h-10 items-center gap-2 rounded-pilula border border-linha px-3 text-meta font-semibold text-texto-suave transition hover:border-acento/40 hover:bg-acento-suave hover:text-acento-intenso ${className}`}
    >
      <svg aria-hidden="true" viewBox="0 0 20 20" className="h-4 w-4 shrink-0">
        {/* Círculo meio cheio: a metáfora universal de contraste. */}
        <circle cx="10" cy="10" r="7" fill="none" stroke="currentColor" strokeWidth="1.8" />
        <path d="M10 3a7 7 0 0 0 0 14z" fill="currentColor" />
      </svg>
      <span className="whitespace-nowrap">{opcaoAtual?.label}</span>
    </button>
  )
}
