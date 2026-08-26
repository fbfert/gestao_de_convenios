import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { apiClient } from '../../api/client'
import { usePode } from '../../lib/permissoes'
import { filtrarItens, type NavLeaf } from '../../routes/navigation'

type DashboardBlock = {
  key: string
  label: string
  href: string | null
  value: number
  detail: string
}

type DashboardResponse = {
  blocks: DashboardBlock[]
}

const cartao =
  'rounded-janela border border-linha bg-superficie p-5 shadow-e1'

export type GrupoPageProps = {
  titulo: string
  chapeu: string
  resumo: string
  itens: NavLeaf[]
  /**
   * Numera os cartoes. Usado em Operacao Convenios, onde a sequencia dos itens
   * e a propria ordem de uso; em Cadastros as telas sao independentes e a
   * numeracao sugeriria um passo a passo que nao existe.
   */
  ordenado?: boolean
  testId: string
}

export function GrupoPage({ titulo, chapeu, resumo, itens: todos, ordenado = false, testId }: GrupoPageProps) {
  const pode = usePode()
  // Mesma regra do menu: cartao para uma tela que o papel nao abre so leva a
  // pessoa ate um 403.
  const itens = filtrarItens(todos, pode)

  const dashboardQuery = useQuery({
    queryKey: ['dashboard'],
    queryFn: async () => (await apiClient.get<{ data: DashboardResponse }>('/dashboard')).data.data,
  })

  const blocosPorChave = new Map(
    (dashboardQuery.data?.blocks ?? []).map((bloco) => [bloco.key, bloco]),
  )

  // So entram metricas que o /dashboard devolveu, ou seja, que o papel do
  // usuario pode ver. Um item sem bloco simplesmente nao aparece aqui.
  const metricas = itens
    .map((item) => (item.metricKey ? blocosPorChave.get(item.metricKey) : undefined))
    .filter((bloco): bloco is DashboardBlock => Boolean(bloco))

  const metricasUnicas = Array.from(new Map(metricas.map((m) => [m.key, m])).values())

  return (
    <div className="space-y-8" data-testid={testId}>
      <section className="overflow-hidden rounded-janela border border-acento/25 bg-acento-suave p-6 shadow-e1">
        <div className="space-y-4">
          <p className="text-meta uppercase tracking-[0.35em] text-cyan-300/80">{chapeu}</p>
          <h2 className="text-display font-semibold text-texto">{titulo}</h2>
          <p className="max-w-3xl text-corpo leading-6 text-slate-300">{resumo}</p>
        </div>
      </section>

      <section className="space-y-4">
        <h3 className="text-subtitulo font-semibold text-white">
          {ordenado ? 'Telas, na ordem de uso' : 'O que cada cadastro guarda'}
        </h3>

        <div className="grid gap-4 md:grid-cols-2">
          {itens.map((item, indice) => (
            <Link
              key={`${item.to}-${indice}`}
              to={item.to}
              className={`${cartao} group block transition hover:border-acento/40 hover:bg-acento-suave`}
              data-testid={`${testId}-card-${item.label.toLowerCase()}`}
            >
              <div className="flex items-start gap-3">
                {ordenado ? (
                  <span className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-cyan-400/20 text-meta font-semibold text-cyan-100">
                    {indice + 1}
                  </span>
                ) : null}

                <div className="space-y-2">
                  <p className="text-corpo-lg font-semibold text-white group-hover:text-cyan-50">
                    {item.label}
                  </p>
                  <p className="text-corpo leading-6 text-slate-300">{item.descricao}</p>
                </div>
              </div>
            </Link>
          ))}
        </div>
      </section>

      <section className="space-y-4">
        <h3 className="text-subtitulo font-semibold text-white">Métricas</h3>

        {dashboardQuery.isPending ? (
          <p className="text-corpo text-slate-400">Carregando métricas...</p>
        ) : null}

        {dashboardQuery.isError ? (
          <p className="text-corpo text-rose-300">
            Não foi possível carregar as métricas. Recarregue a página para tentar de novo.
          </p>
        ) : null}

        {!dashboardQuery.isPending && !dashboardQuery.isError && metricasUnicas.length === 0 ? (
          <p className="text-corpo text-slate-400">
            Seu papel não tem permissão para ver os números destas telas.
          </p>
        ) : null}

        {metricasUnicas.length > 0 ? (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {metricasUnicas.map((bloco) => {
              const conteudo = (
                <>
                  <p className="text-meta uppercase tracking-[0.25em] text-slate-400">{bloco.label}</p>
                  <p className="mt-2 text-display font-semibold text-white">{bloco.value}</p>
                  <p className="mt-1 text-meta text-slate-400">{bloco.detail}</p>
                </>
              )

              return bloco.href ? (
                <Link
                  key={bloco.key}
                  to={bloco.href}
                  className={`${cartao} block transition hover:border-acento/40 hover:bg-acento-suave`}
                  data-testid={`${testId}-metrica-${bloco.key}`}
                >
                  {conteudo}
                </Link>
              ) : (
                <div key={bloco.key} className={cartao} data-testid={`${testId}-metrica-${bloco.key}`}>
                  {conteudo}
                </div>
              )
            })}
          </div>
        ) : null}
      </section>
    </div>
  )
}
