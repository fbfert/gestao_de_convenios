import { useMemo } from 'react'
import { Select } from '../../components/ui/Select'
import type { EspecialidadeRef, ProfissionalRef } from '../../lib/queries/useReferenceData'
import { especialidadesRepetidas, rotuloEspecialidade } from './solicitacaoItens'
import type { SolicitacaoFormItem } from './types'

function fieldClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

type SolicitacaoItensFieldsProps = {
  itens: SolicitacaoFormItem[]
  onChange: (itens: SolicitacaoFormItem[]) => void
  especialidades: EspecialidadeRef[]
  profissionais: ProfissionalRef[]
  disabled?: boolean
}

/**
 * Uma linha por especialidade do pedido, cada uma com o profissional que vai executá-la.
 * Compartilhado pelo cadastro manual e pelo fluxo de leitura do pedido médico.
 */
export function SolicitacaoItensFields({
  itens,
  onChange,
  especialidades,
  profissionais,
  disabled = false,
}: SolicitacaoItensFieldsProps) {
  const profissionaisPorEspecialidade = useMemo(() => {
    const mapa = new Map<number, ProfissionalRef[]>()

    for (const profissional of profissionais) {
      // Um profissional pode atender em varias especialidades; entra na lista
      // de cada uma. `especialidade_ids` so falta em resposta antiga em cache,
      // e nesse caso a principal serve de fallback.
      const ids = profissional.especialidade_ids?.length
        ? profissional.especialidade_ids
        : [profissional.especialidade_id]

      for (const id of ids) {
        const lista = mapa.get(id) ?? []
        lista.push(profissional)
        mapa.set(id, lista)
      }
    }

    return mapa
  }, [profissionais])

  const atualizarItem = (index: number, patch: Partial<SolicitacaoFormItem>) => {
    onChange(itens.map((item, posicao) => (posicao === index ? { ...item, ...patch } : item)))
  }

  const repetidas = especialidadesRepetidas(itens)

  return (
    <div className="space-y-3" data-testid="solicitacao-itens">
      <div>
        <span className="text-sm font-medium text-slate-200">Especialidades do pedido</span>
        <p className="text-xs text-slate-400">
          Cada especialidade vira um item com o seu próprio profissional executante.
        </p>
      </div>

      {itens.map((item, index) => {
        const disponiveis = profissionaisPorEspecialidade.get(Number(item.especialidade_id)) ?? []

        return (
          <div
            key={index}
            className="grid gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 md:grid-cols-[2fr_2fr_.8fr_auto] md:items-end"
            data-testid={`solicitacao-item-${index}`}
          >
            <label className="block space-y-2">
              <span className="text-xs uppercase tracking-[0.25em] text-slate-400">
                Especialidade
              </span>
              <Select
                value={item.especialidade_id}
                onChange={(event) =>
                  atualizarItem(index, {
                    especialidade_id: event.target.value,
                    profissional_id: '',
                  })
                }
                className={fieldClasses()}
                disabled={disabled || especialidades.length === 0}
                data-testid={`solicitacao-item-especialidade-${index}`}
              >
                <option value="" disabled>
                  Selecione
                </option>
                {especialidades.map((especialidade) => (
                  <option key={especialidade.id} value={especialidade.id}>
                    {rotuloEspecialidade(especialidade)}
                  </option>
                ))}
              </Select>
            </label>

            <label className="block space-y-2">
              <span className="text-xs uppercase tracking-[0.25em] text-slate-400">
                Profissional executante
              </span>
              <Select
                value={item.profissional_id}
                onChange={(event) => atualizarItem(index, { profissional_id: event.target.value })}
                className={fieldClasses()}
                disabled={disabled || item.especialidade_id === '' || disponiveis.length === 0}
                data-testid={`solicitacao-item-profissional-${index}`}
              >
                <option value="" disabled>
                  {item.especialidade_id === ''
                    ? 'Escolha a especialidade'
                    : disponiveis.length === 0
                      ? 'Nenhum profissional nesta especialidade'
                      : 'Selecione'}
                </option>
                {disponiveis.map((profissional) => (
                  <option key={profissional.id} value={profissional.id}>
                    {profissional.nome}
                  </option>
                ))}
              </Select>
            </label>

            <label className="block space-y-2">
              <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Qtd.</span>
              <input
                type="number"
                min="1"
                value={item.quantidade}
                onChange={(event) => atualizarItem(index, { quantidade: event.target.value })}
                className={fieldClasses()}
                disabled={disabled}
                data-testid={`solicitacao-item-quantidade-${index}`}
              />
            </label>

            <button
              type="button"
              onClick={() => onChange(itens.filter((_, posicao) => posicao !== index))}
              disabled={disabled || itens.length === 1}
              className="rounded-2xl border border-rose-400/30 bg-rose-400/10 px-4 py-3 text-sm font-semibold text-rose-100 transition hover:bg-rose-400/20 disabled:opacity-40"
              title={itens.length === 1 ? 'O pedido precisa de ao menos uma especialidade.' : undefined}
              data-testid={`solicitacao-item-remover-${index}`}
            >
              Remover
            </button>
          </div>
        )
      })}

      <div className="flex">
        <button
          type="button"
          onClick={() =>
            onChange([...itens, { especialidade_id: '', profissional_id: '', quantidade: '10' }])
          }
          disabled={disabled}
          className="rounded-2xl border border-cyan-300/30 bg-cyan-400/10 px-4 py-2 text-sm font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:opacity-50"
          data-testid="solicitacao-item-adicionar"
        >
          Adicionar especialidade
        </button>
      </div>

      {repetidas ? (
        <p
          className="rounded-2xl border border-amber-400/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-100"
          data-testid="solicitacao-itens-repetidas"
        >
          Há especialidades repetidas no pedido. Cada uma será enviada como uma guia separada à
          operadora.
        </p>
      ) : null}
    </div>
  )
}
