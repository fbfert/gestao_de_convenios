import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo } from 'react'
import { Link } from 'react-router-dom'
import { Select } from '../../components/ui/Select'
import type { EspecialidadeRef, ProfissionalRef } from '../../lib/queries/useReferenceData'
import { especialidadesRepetidas, rotuloEspecialidade } from './solicitacaoItens'
import type { SolicitacaoFormItem } from './types'
import { Tooltip } from '../../components/ui/Tooltip'

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

  /*
    O cadastro de profissional abre em outra aba, senao o pedido meio
    preenchido — e a leitura do documento, no fluxo da IA — iria embora. Ao
    voltar o foco para esta aba, a lista e recarregada para o profissional novo
    aparecer no seletor.
  */
  const queryClient = useQueryClient()

  useEffect(() => {
    const aoFocar = () => {
      void queryClient.invalidateQueries({ queryKey: ['profissionais'] })
    }

    window.addEventListener('focus', aoFocar)

    return () => window.removeEventListener('focus', aoFocar)
  }, [queryClient])

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
              <span className="flex items-center gap-1 text-xs uppercase tracking-[0.25em] text-slate-400">
                Profissional executante
                <Tooltip rotulo="Por que a lista muda">
                  Só aparece quem atende a especialidade escolhida ao lado — como especialidade
                  principal ou em &quot;Atende também em&quot;, cadastrado em Profissionais. Lançar
                  no nome de quem não faz a terapia gera glosa na conciliação.
                </Tooltip>
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

              {item.especialidade_id !== '' && disponiveis.length === 0 ? (
                <span className="block space-y-2 rounded-2xl border border-amber-300/20 bg-amber-400/10 p-3">
                  <span className="block text-xs leading-5 text-amber-100">
                    Nenhum profissional atende{' '}
                    {rotuloEspecialidade(
                      especialidades.find(
                        (especialidade) => String(especialidade.id) === item.especialidade_id,
                      ) ?? { id: 0, nome: 'esta especialidade' },
                    )}
                    . Sem executante, a solicitação não pode ser aberta.
                  </span>

                  <Link
                    to={`/profissionais/novo?especialidade_id=${item.especialidade_id}`}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-block rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1.5 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
                    data-testid={`solicitacao-item-adicionar-profissional-${index}`}
                  >
                    Adicionar profissional
                  </Link>
                </span>
              ) : null}
            </label>

            <label className="block space-y-2">
              <span className="flex items-center gap-1 text-xs uppercase tracking-[0.25em] text-slate-400">
                Qtd.
                <Tooltip rotulo="O que este número significa">
                  Quantas sessões dessa especialidade estão sendo pedidas ao convênio nesta guia —
                  não é a cota por ciclo (isso quem define é a regra do convênio, na Antecipação).
                </Tooltip>
              </span>
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
