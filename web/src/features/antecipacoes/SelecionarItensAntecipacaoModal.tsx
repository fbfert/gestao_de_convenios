import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react'
import { useEffect, useState } from 'react'
import { X } from 'lucide-react'
import { Botao } from '../../components/ui/Botao'
import { useFechamentoExplicito } from '../../lib/useFechamentoExplicito'
import type { Solicitacao } from '../solicitacoes/types'
import { useCriarAntecipacao, getHttpErrorMessage } from './useAntecipacoes'

type SelecionarItensAntecipacaoModalProps = {
  open: boolean
  onClose: () => void
  solicitacao: Solicitacao | null
}

const GUIAS_ELEGIVEIS = ['approved', 'finalized']

function chave(especialidadeId: number, profissionalId: number) {
  return `${especialidadeId}-${profissionalId}`
}

/**
 * Checklist dos itens (especialidade+profissional) de uma solicitação já
 * carregada, pra decidir o que repete no próximo ciclo. Ao confirmar, gera
 * de verdade: cria um item novo por renovação (`renovacao_de_item_id`) PRA
 * CADA item marcado, na MESMA solicitação — não uma solicitação nova (ver
 * App\Services\AntecipacaoService::criar()). Convênio manual já ganha guia
 * na hora; Unimed RDA fica pronto pra alguém clicar "Enviar para Unimed"
 * depois, exatamente como "Adicionar sessões" hoje.
 */
export function SelecionarItensAntecipacaoModal({
  open,
  onClose,
  solicitacao,
}: SelecionarItensAntecipacaoModalProps) {
  const criarAntecipacao = useCriarAntecipacao()
  const [selecionados, setSelecionados] = useState<Set<string>>(new Set())
  const [erro, setErro] = useState<string | null>(null)
  // Chamado incondicionalmente, antes do `return null` abaixo — Hook não
  // pode depender de `solicitacao` estar presente.
  const fechamento = useFechamentoExplicito(open, onClose)

  const itensComDados = (solicitacao?.itens ?? []).filter(
    (item) => item.especialidade && item.profissional,
  )

  useEffect(() => {
    if (!open || !solicitacao) return

    setErro(null)
    setSelecionados(
      new Set(
        itensComDados
          .filter((item) => item.guia && GUIAS_ELEGIVEIS.includes(item.guia.status))
          .map((item) => chave(item.especialidade_id, item.profissional_id)),
      ),
    )
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, solicitacao?.id])

  if (!solicitacao) {
    return null
  }

  const alternar = (especialidadeId: number, profissionalId: number) => {
    const key = chave(especialidadeId, profissionalId)
    setSelecionados((atual) => {
      const proximo = new Set(atual)
      if (proximo.has(key)) {
        proximo.delete(key)
      } else {
        proximo.add(key)
      }
      return proximo
    })
  }

  const handleConfirmar = async () => {
    setErro(null)

    const itens = itensComDados.filter((item) =>
      selecionados.has(chave(item.especialidade_id, item.profissional_id)),
    )

    if (itens.length === 0) {
      setErro('Selecione ao menos um item.')
      return
    }

    try {
      await criarAntecipacao.mutateAsync({
        solicitacao_origem_id: solicitacao.id,
        itens_selecionados: itens.map((item) => ({
          especialidade_id: item.especialidade_id,
          profissional_id: item.profissional_id,
        })),
      })

      onClose()
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível gerar a antecipação.'))
    }
  }

  return (
    <Dialog {...fechamento} className="relative z-(--z-dialogo)">
      <DialogBackdrop className="fixed inset-0 bg-slate-950/75 backdrop-blur-sm" />
      <div className="fixed inset-0 overflow-y-auto p-4 sm:p-6">
        <div className="flex min-h-full items-center justify-center">
          <DialogPanel
            className="w-full max-w-lg rounded-janela border border-white/10 bg-slate-950 p-6 text-white shadow-e3 shadow-black/60"
            data-testid="selecionar-itens-antecipacao-modal"
          >
            <div className="flex items-start justify-between gap-4">
              <DialogTitle className="text-titulo font-semibold">Gerar antecipação</DialogTitle>
              <button
                type="button"
                onClick={onClose}
                className="rounded-full border border-white/10 bg-white/5 p-2 text-white transition hover:bg-white/10"
                aria-label="Fechar"
              >
                <X className="size-4" aria-hidden="true" />
              </button>
            </div>

            <p className="mt-2 text-corpo text-slate-300">
              {solicitacao.paciente?.nome ?? 'Paciente não informado'} ·{' '}
              {solicitacao.convenio?.nome ?? 'Convênio não informado'}. Escolha os itens que
              repetem no próximo ciclo — cada um vira um item novo (e, quando possível, a guia já
              junto) nesta mesma solicitação, pronto pra entrar na automação.
            </p>

            <div className="mt-4 max-h-80 space-y-2 overflow-y-auto">
              {itensComDados.length === 0 ? (
                <p className="text-corpo text-slate-400">Esta solicitação não tem itens completos.</p>
              ) : null}

              {itensComDados.map((item) => (
                <label
                  key={item.id}
                  className="flex items-start gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3"
                  data-testid="selecionar-itens-antecipacao-item"
                >
                  <input
                    type="checkbox"
                    className="mt-1"
                    checked={selecionados.has(chave(item.especialidade_id, item.profissional_id))}
                    onChange={() => alternar(item.especialidade_id, item.profissional_id)}
                  />
                  <span>
                    <span className="block text-corpo font-medium text-white">
                      {item.especialidade?.nome ?? 'Especialidade'} — {item.profissional?.nome ?? 'Profissional'}
                    </span>
                    <span className="block text-meta text-slate-400">
                      Guia {item.guia?.numero_guia ?? `#${item.guia?.id ?? '—'}`}
                      {item.guia ? ` · ${item.guia.status}` : ' · sem guia'}
                    </span>
                  </span>
                </label>
              ))}
            </div>

            {erro ? (
              <p className="mt-4 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
                {erro}
              </p>
            ) : null}

            <div className="mt-6 flex justify-end gap-3">
              <Botao type="button" variante="secundario" onClick={onClose}>
                Cancelar
              </Botao>
              <Botao
                type="button"
                variante="primario"
                disabled={criarAntecipacao.isPending}
                onClick={() => void handleConfirmar()}
                data-testid="selecionar-itens-antecipacao-confirmar"
              >
                {criarAntecipacao.isPending ? 'Gerando...' : 'Gerar guias'}
              </Botao>
            </div>
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  )
}
