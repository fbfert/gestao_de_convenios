import { Link } from 'react-router-dom'
import { NIVEIS, rotuloDaChave } from './nivel'
import type { NivelAlerta } from './types'

export type AlertaDoCard = {
  id: number
  chave: string
  nivel: NivelAlerta
  titulo: string
  descricao: string | null
  aberto_em: string | null
}

/**
 * Card de alertas do dashboard: os cinco abertos mais recentes.
 *
 * Só amarelo e vermelho. Se verde significasse "tudo certo", o card encheria de
 * linhas irrelevantes e as vermelhas sumiriam no meio — a ausência de alerta já
 * É o estado verde, e é isso que este card diz quando não há nada.
 *
 * E não some quando está vazio: "não apareceu nada" e "está tudo bem" precisam
 * ser distinguíveis, senão o operador aprende a não procurar.
 */
export function AlertasCard({ alertas }: { alertas: AlertaDoCard[] }) {
  return (
    <section
      className="rounded-janela border border-linha bg-superficie p-5 shadow-e1"
      aria-label="Alertas"
      data-testid="dashboard-alertas-card"
    >
      <div className="flex items-baseline justify-between gap-4">
        <p className="text-meta font-semibold uppercase tracking-[0.2em] text-acento">Alertas</p>
        <Link
          to="/alertas"
          className="text-meta font-semibold text-acento transition hover:text-acento-intenso"
        >
          Ver todos →
        </Link>
      </div>

      {alertas.length === 0 ? (
        <p className="mt-3 text-corpo text-texto-suave" data-testid="dashboard-alertas-vazio">
          Nenhum alerta pendente.
        </p>
      ) : (
        <ul className="mt-3 space-y-2">
          {alertas.map((alerta) => (
            <li key={alerta.id}>
              <Link
                to="/alertas"
                className="flex flex-wrap items-center gap-3 rounded-superficie border border-linha bg-fundo px-4 py-3 transition hover:border-acento/40 hover:bg-acento-suave"
              >
                {/* Cor E texto: sob deuteranopia, amarelo e vermelho seriam a
                    mesma mancha (ADR-23). */}
                <span
                  className={`inline-flex items-center gap-1 rounded-pilula px-2 py-1 text-meta font-semibold ${NIVEIS[alerta.nivel].etiqueta}`}
                >
                  <span aria-hidden="true">{NIVEIS[alerta.nivel].glifo}</span>
                  {NIVEIS[alerta.nivel].rotulo}
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-corpo text-texto">{alerta.titulo}</span>
                  <span className="block text-meta text-texto-suave">
                    {rotuloDaChave(alerta.chave)}
                  </span>
                </span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
