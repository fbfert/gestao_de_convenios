import { useEffect, useRef, useState, type ReactNode } from 'react'

/**
 * Menu de três pontinhos: some as ações de uma linha de tabela atrás de um
 * gatilho, para a tabela não ficar larga com um botão por ação.
 *
 * Não fecha ao clicar dentro do painel — só em clique fora ou Esc. Ações
 * como "Finalizar" abrem um formulário inline dentro do próprio painel
 * (senha/validade da guia); fechar no primeiro clique apagaria esse
 * formulário antes de dar tempo de preenchê-lo.
 */
export function DropdownMenu({
  children,
  rotulo = 'Mais ações',
  testId,
}: {
  children: ReactNode
  rotulo?: string
  testId?: string
}) {
  const [aberto, setAberto] = useState(false)
  const containerRef = useRef<HTMLDivElement | null>(null)

  useEffect(() => {
    if (!aberto) {
      return
    }

    const aoClicarFora = (evento: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(evento.target as Node)) {
        setAberto(false)
      }
    }

    const aoTeclar = (evento: KeyboardEvent) => {
      if (evento.key === 'Escape') {
        setAberto(false)
      }
    }

    document.addEventListener('mousedown', aoClicarFora)
    document.addEventListener('keydown', aoTeclar)

    return () => {
      document.removeEventListener('mousedown', aoClicarFora)
      document.removeEventListener('keydown', aoTeclar)
    }
  }, [aberto])

  return (
    <div className="relative inline-block text-left" ref={containerRef}>
      <button
        type="button"
        onClick={() => setAberto((atual) => !atual)}
        aria-haspopup="true"
        aria-expanded={aberto}
        aria-label={rotulo}
        title={rotulo}
        className="flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-200 transition hover:border-cyan-300/40 hover:bg-white/10"
        data-testid={testId}
      >
        <svg aria-hidden="true" viewBox="0 0 4 16" className="h-4 w-1" fill="currentColor">
          <circle cx="2" cy="2" r="1.7" />
          <circle cx="2" cy="8" r="1.7" />
          <circle cx="2" cy="14" r="1.7" />
        </svg>
      </button>

      {aberto ? (
        <div
          role="menu"
          className="absolute right-0 z-(--z-menu) mt-2 w-72 max-w-[90vw] space-y-2 rounded-superficie border border-linha bg-superficie-elevada p-3 shadow-e2"
          data-testid={testId ? `${testId}-painel` : undefined}
        >
          {children}
        </div>
      ) : null}
    </div>
  )
}
