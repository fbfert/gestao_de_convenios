import { Link } from 'react-router-dom'

export type GuiasCardLinha = {
  key: string
  label: string
  value: number
  unidade: string | null
  detail: string
  href: string
}

/**
 * Card denso de Guias: três linhas, cada uma clicável e levando à listagem JÁ
 * FILTRADA.
 *
 * O bloco antigo levava a `/guias` cru, e o operador precisava refazer o filtro
 * que o número acabara de prometer. O número grande é o que ainda exige ação —
 * em "Negadas", as que ninguém tratou — e "hoje / na semana" contam pela data da
 * transição de status, não pela data de criação da guia.
 */
export function GuiasCard({ linhas }: { linhas: GuiasCardLinha[] }) {
  if (linhas.length === 0) {
    return null
  }

  return (
    <article
      className="rounded-janela border border-linha bg-superficie p-5 shadow-e1"
      data-testid="dashboard-guias-card"
    >
      <div className="flex items-baseline justify-between gap-4">
        <p className="text-meta font-semibold uppercase tracking-[0.2em] text-acento">Guias</p>
        <Link
          to="/guias"
          className="text-meta font-semibold text-acento transition hover:text-acento-intenso"
        >
          Ver todas →
        </Link>
      </div>

      <ul className="mt-4 space-y-2">
        {linhas.map((linha) => (
          <li key={linha.key}>
            <Link
              to={linha.href}
              className="flex flex-wrap items-center justify-between gap-3 rounded-superficie border border-linha bg-fundo px-4 py-3 transition hover:border-acento/40 hover:bg-acento-suave"
              data-testid={`dashboard-guias-linha-${linha.key}`}
            >
              <span className="text-corpo font-medium text-texto">{linha.label}</span>
              <span className="flex items-baseline gap-2">
                <span className="text-titulo font-semibold text-texto">{linha.value}</span>
                {linha.unidade ? (
                  <span className="text-meta text-texto-suave">{linha.unidade}</span>
                ) : null}
              </span>
              <span className="w-full text-meta text-texto-suave sm:w-auto">{linha.detail}</span>
            </Link>
          </li>
        ))}
      </ul>
    </article>
  )
}
