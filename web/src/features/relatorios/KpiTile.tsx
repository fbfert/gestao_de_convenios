import { ArrowDown, ArrowUp, Minus } from 'lucide-react'
import { Tooltip } from '../../components/ui/Tooltip'
import { AUSENTE, formatar, variacao } from './formato'
import type { Kpi } from './tipos'

/**
 * Um indicador, com o valor do período anterior quando há comparação.
 *
 * Duas regras mandam aqui:
 *
 * **Ausência não é zero.** `valor: null` vira travessão, e não "0". Taxa de
 * aprovação sem nenhuma guia decidida é uma pergunta sem resposta, não uma
 * clínica que não aprovou nada — e quem lê "0%" toma uma decisão sobre um
 * número que ninguém mediu.
 *
 * **A cor da variação vem da API.** Cada KPI declara o `sentido`
 * (`maior_melhor`, `menor_melhor`, `neutro`) junto de onde é calculado. A
 * alternativa seria o front manter uma lista de exceções por chave — e alguém
 * criaria um KPI novo sem lembrar de atualizá-la, deixando "taxa de negação
 * subiu" pintado de verde.
 */
export function KpiTile({ kpi }: { kpi: Kpi }) {
  const valor = formatar(kpi.valor, kpi.formato)
  const delta = variacao(kpi.valor, kpi.anterior)

  return (
    <div
      className="rounded-janela border border-linha bg-superficie p-4 shadow-e1"
      data-testid={`kpi-${kpi.key}`}
    >
      <div className="flex items-start justify-between gap-2">
        <p className="text-rotulo text-texto-suave">{kpi.label}</p>
        {kpi.hint ? (
          <Tooltip rotulo={`Como "${kpi.label}" é calculado`}>{kpi.hint}</Tooltip>
        ) : null}
      </div>

      <p
        className="mt-2 text-display font-semibold text-texto tabular-nums"
        data-testid={`kpi-${kpi.key}-valor`}
      >
        {valor}
      </p>

      <Variacao kpi={kpi} delta={delta} />
    </div>
  )
}

function Variacao({ kpi, delta }: { kpi: Kpi; delta: number | null }) {
  if (kpi.anterior === null) {
    return <p className="mt-1 text-meta text-texto-desativado">sem comparação</p>
  }

  const anterior = formatar(kpi.anterior, kpi.formato)

  // Anterior existe mas a divisão não fecha (anterior zero): mostra os dois
  // números em vez de um percentual inventado.
  if (delta === null) {
    return (
      <p className="mt-1 text-meta text-texto-suave" data-testid={`kpi-${kpi.key}-variacao`}>
        antes: <span className="tabular-nums">{anterior}</span>
      </p>
    )
  }

  const subiu = delta > 0
  const parado = Math.abs(delta) < 0.05
  const Seta = parado ? Minus : subiu ? ArrowUp : ArrowDown

  const melhorou =
    kpi.sentido === 'neutro' || parado
      ? null
      : kpi.sentido === 'maior_melhor'
        ? subiu
        : !subiu

  const cor =
    melhorou === null ? 'text-texto-suave' : melhorou ? 'text-sucesso-texto' : 'text-perigo-texto'

  const sinal = parado ? '' : subiu ? '+' : '−'
  const numero = Math.abs(delta).toLocaleString('pt-BR', { maximumFractionDigits: 1 })

  return (
    <p className={`mt-1 flex items-center gap-1 text-meta ${cor}`} data-testid={`kpi-${kpi.key}-variacao`}>
      <Seta aria-hidden="true" className="size-3.5" />
      <span className="tabular-nums">
        {parado ? 'estável' : `${sinal}${numero}%`}
      </span>
      <span className="text-texto-desativado">
        · antes {anterior === AUSENTE ? AUSENTE : anterior}
      </span>
    </p>
  )
}
