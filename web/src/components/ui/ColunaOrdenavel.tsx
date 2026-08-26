import type { Ordenacao } from '../../lib/useOrdenacao'

/**
 * Cabeçalho de tabela que ordena ao clique.
 *
 * Sem `coluna`, vira um `th` comum — usado nas colunas que não ordenam, como
 * Ações, para a tabela não prometer o que a API não faz.
 */
export function ColunaOrdenavel({
  titulo,
  coluna,
  ordenacao,
  onOrdenar,
  className = 'px-4 py-3',
}: {
  titulo: string
  coluna?: string
  ordenacao?: Ordenacao
  onOrdenar?: (coluna: string) => void
  className?: string
}) {
  if (!coluna || !ordenacao || !onOrdenar) {
    return <th className={className}>{titulo}</th>
  }

  const ativa = ordenacao.ordenar_por === coluna

  return (
    <th className={className}>
      <button
        type="button"
        onClick={() => onOrdenar(coluna)}
        // `-my-3 py-3` estende o alvo de clique ate a altura do <th> sem
        // mexer no espacamento: 16px de altura reprovava o minimo de 24x24.
        className="-my-3 flex w-full items-center gap-1 py-3 text-left uppercase tracking-[0.25em] transition hover:text-cyan-100"
        data-testid={`ordenar-${coluna}`}
      >
        {titulo}
        <span className={ativa ? 'text-cyan-200' : 'text-slate-600'} aria-hidden="true">
          {ativa ? (ordenacao.direcao === 'asc' ? '▲' : '▼') : '↕'}
        </span>
      </button>
    </th>
  )
}
