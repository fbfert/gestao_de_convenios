import { Link } from 'react-router-dom'
import { ETIQUETA_TIPO, ROTULO_TIPO } from './tipo'
import { useNovidades } from './useNovidades'

/**
 * Card de novidades no dashboard: as cinco últimas.
 *
 * Mostra quantas ainda não foram lidas por ESTE usuário — sem isso o card vira
 * paisagem em duas semanas, que é o mesmo defeito do digest vazio.
 */
export function NovidadesCard() {
  const novidadesQuery = useNovidades(5)

  const novidades = novidadesQuery.data?.data ?? []
  const naoLidas = novidadesQuery.data?.meta.nao_lidas ?? 0

  if (novidadesQuery.isLoading || novidades.length === 0) {
    return null
  }

  return (
    <section
      className="rounded-janela border border-linha bg-superficie p-5 shadow-e1"
      aria-label="Novidades"
      data-testid="dashboard-novidades-card"
    >
      <div className="flex flex-wrap items-baseline justify-between gap-3">
        <div className="flex items-baseline gap-2">
          <p className="text-meta font-semibold uppercase tracking-[0.2em] text-acento">Novidades</p>
          {naoLidas > 0 ? (
            <span className="rounded-pilula bg-acento-suave px-2 py-1 text-meta font-semibold text-acento-intenso">
              {naoLidas === 1 ? '1 não lida' : `${naoLidas} não lidas`}
            </span>
          ) : null}
        </div>
        <Link
          to="/novidades"
          className="text-meta font-semibold text-acento transition hover:text-acento-intenso"
        >
          Ver todas →
        </Link>
      </div>

      <ul className="mt-3 space-y-2">
        {novidades.map((novidade) => (
          <li key={novidade.slug}>
            <Link
              to="/novidades"
              className="flex flex-wrap items-center gap-3 rounded-superficie border border-linha bg-fundo px-4 py-3 transition hover:border-acento/40 hover:bg-acento-suave"
            >
              <span
                className={`rounded-pilula px-2 py-1 text-meta font-semibold ${ETIQUETA_TIPO[novidade.tipo]}`}
              >
                {ROTULO_TIPO[novidade.tipo]}
              </span>
              <span className="min-w-0 flex-1 truncate text-corpo text-texto">
                {novidade.titulo}
              </span>
              {!novidade.lida ? (
                <span className="text-meta font-semibold text-acento">novo</span>
              ) : null}
            </Link>
          </li>
        ))}
      </ul>
    </section>
  )
}
