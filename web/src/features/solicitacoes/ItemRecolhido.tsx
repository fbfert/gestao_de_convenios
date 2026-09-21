import { ChevronRight } from 'lucide-react'
import { SeloFinalizadaNaOperadora } from '../guias/SeloFinalizadaNaOperadora'
import type { SolicitacaoItem } from './types'

/**
 * O item cuja guia a operadora já deu por finalizada, recolhido numa linha.
 *
 * O que ele esconde são as AÇÕES — enviar para a automação, excluir, gerar
 * guia. Numa solicitação antiga, com tudo já resolvido no portal, essas ações
 * são ruído: não há o que fazer ali, e elas competem por atenção com os itens
 * que ainda pedem trabalho.
 *
 * O que ele mostra é o suficiente para reconhecer o item sem expandir:
 * especialidade, número da guia e o selo.
 *
 * Quem guarda o "está aberto?" é a página, num conjunto de ids — e não este
 * componente, num `useState` próprio. A lista é montada dentro de um `.map`, e
 * estado por item ali dentro se perde a cada reordenação da lista. Recolhido é
 * estado de tela de qualquer forma: nada é gravado, e não sobrevive ao
 * recarregar.
 */
export function itemFinalizadoNaOperadora(item: SolicitacaoItem): boolean {
  return Boolean(item.guia?.finalizada_na_operadora_em)
}

export function ItemRecolhido({
  item,
  onExpandir,
}: {
  item: SolicitacaoItem
  onExpandir: () => void
}) {
  return (
    <button
      type="button"
      onClick={onExpandir}
      className="flex w-full flex-wrap items-center gap-2 rounded-superficie border border-linha bg-fundo px-3 py-2 text-left shadow-e1 transition hover:bg-superficie"
      data-testid={`solicitacao-item-recolhido-${item.id}`}
    >
      <ChevronRight className="size-4 shrink-0 text-slate-400" aria-hidden="true" />
      <span className="text-corpo text-slate-200">
        {item.especialidade?.nome ?? item.especialidade_id}
      </span>
      {item.guia?.numero_operadora ? (
        <span className="rounded-pilula border border-linha bg-superficie px-2.5 py-0.5 text-meta font-semibold text-texto">
          Guia {item.guia.numero_operadora}
        </span>
      ) : null}
      <SeloFinalizadaNaOperadora
        finalizadaEm={item.guia?.finalizada_na_operadora_em ?? null}
        testId={`solicitacao-item-selo-operadora-${item.id}`}
      />
    </button>
  )
}
