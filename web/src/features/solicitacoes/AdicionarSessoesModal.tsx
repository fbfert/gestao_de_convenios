import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react'
import { useFechamentoExplicito } from '../../lib/useFechamentoExplicito'
import { CalendarClock, CircleAlert, Layers, Repeat2 } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { Botao } from '../../components/ui/Botao'
import { Select } from '../../components/ui/Select'
import type { EspecialidadeRef, ProfissionalRef } from '../../lib/queries/useReferenceData'
import { rotuloDaRemessa, rotuloEspecialidade } from './solicitacaoItens'
import { getHttpErrorMessage, useAdicionarItem, useContextoAdicao } from './useSolicitacoes'
import type { AdicionarItemForm, Solicitacao, SolicitacaoItem } from './types'

type Caminho = 'repetir' | 'nova'

const vazio: AdicionarItemForm = {
  especialidade_id: '',
  profissional_id: '',
  quantidade: '',
  renovacao_de_item_id: null,
}

function campo() {
  return 'w-full rounded-campo border border-linha bg-fundo px-4 py-3 text-corpo text-texto outline-none transition focus:border-acento'
}

/**
 * Um aviso. Nenhum bloqueia o confirmar — bloquear a repetição bloquearia o
 * caso de uso principal desta feature (10 sessões por guia, paciente que
 * precisa de 20).
 */
function Aviso({ icone: Icone, children }: { icone: typeof Repeat2; children: React.ReactNode }) {
  return (
    <li className="flex items-start gap-3 rounded-superficie border border-alerta-texto/25 bg-alerta-suave px-4 py-3">
      <Icone aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-alerta-texto" />
      <span className="text-corpo text-alerta-texto">{children}</span>
    </li>
  )
}

export function AdicionarSessoesModal({
  solicitacao,
  especialidades,
  profissionais,
  onClose,
}: {
  solicitacao: Solicitacao | null
  especialidades: EspecialidadeRef[]
  profissionais: ProfissionalRef[]
  onClose: () => void
}) {
  const [caminho, setCaminho] = useState<Caminho>('repetir')
  const [form, setForm] = useState<AdicionarItemForm>(vazio)
  const [erro, setErro] = useState<string | null>(null)

  const itens = useMemo(() => solicitacao?.itens ?? [], [solicitacao])
  const adicionar = useAdicionarItem()

  const contexto = useContextoAdicao(solicitacao?.id ?? null, {
    especialidade_id: form.especialidade_id,
    profissional_id: form.profissional_id,
    renovacao_de_item_id: form.renovacao_de_item_id,
  })

  // Reabrir o modal para outra solicitação não pode herdar o rascunho anterior.
  useEffect(() => {
    if (solicitacao) {
      setForm(vazio)
      setErro(null)
      setCaminho(itens.length > 0 ? 'repetir' : 'nova')
    }
  }, [solicitacao, itens.length])

  /** Caminho A: escolher um item existente pré-preenche tudo. */
  const repetirItem = (item: SolicitacaoItem) => {
    setForm({
      especialidade_id: String(item.especialidade_id),
      profissional_id: String(item.profissional_id),
      quantidade: String(item.quantidade ?? ''),
      // A ORIGEM da cadeia, e não o item clicado. O servidor normaliza de novo
      // — aqui é só para o contexto dos avisos já vir certo.
      renovacao_de_item_id: item.renovacao_de_item_id ?? item.id,
    })
    setErro(null)
  }

  const trocarCaminho = (novo: Caminho) => {
    setCaminho(novo)
    setForm(
      novo === 'nova'
        ? { ...vazio, quantidade: String(contexto.data?.quantidade_padrao ?? '') }
        : vazio,
    )
    setErro(null)
  }

  const profissionaisDaEspecialidade = profissionais.filter((profissional) => {
    if (!form.especialidade_id) return true
    const ids = profissional.especialidade_ids?.length
      ? profissional.especialidade_ids
      : [profissional.especialidade_id]

    return ids.includes(Number(form.especialidade_id))
  })

  const podeConfirmar =
    form.especialidade_id !== '' && form.profissional_id !== '' && form.quantidade !== ''

  const confirmar = async () => {
    if (!solicitacao) return
    setErro(null)

    try {
      await adicionar.mutateAsync({ solicitacaoId: solicitacao.id, dados: form })
      onClose()
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível acrescentar as sessões.'))
    }
  }

  const dados = contexto.data
  const nomeEspecialidade = especialidades.find(
    (especialidade) => String(especialidade.id) === form.especialidade_id,
  )?.nome

  return (
    <Dialog
      {...useFechamentoExplicito(solicitacao !== null, onClose)}
      className="relative z-(--z-dialogo)"
    >
      <DialogBackdrop className="fixed inset-0 bg-slate-950/75 backdrop-blur-sm" />
      <div className="fixed inset-0 overflow-y-auto p-4 sm:p-6">
        <div className="flex min-h-full items-center justify-center">
          <DialogPanel
            className="w-full max-w-2xl rounded-janela border border-linha bg-superficie p-6 shadow-e3"
            data-testid="adicionar-sessoes-modal"
          >
            <DialogTitle className="text-titulo font-semibold text-texto">
              Adicionar sessões{solicitacao ? ` · Solicitação #${solicitacao.id}` : ''}
            </DialogTitle>
            <p className="mt-1 text-corpo text-texto-suave">
              As sessões novas entram no mesmo pedido médico, sem abrir outra solicitação.
            </p>

            <div className="mt-5 flex flex-wrap gap-2">
              <button
                type="button"
                onClick={() => trocarCaminho('repetir')}
                disabled={itens.length === 0}
                className={`rounded-pilula border px-4 py-2 text-corpo font-semibold transition disabled:opacity-50 ${
                  caminho === 'repetir'
                    ? 'border-acento/40 bg-acento-suave text-acento-intenso'
                    : 'border-linha bg-fundo text-texto-suave'
                }`}
                data-testid="adicionar-sessoes-caminho-repetir"
              >
                Repetir especialidade já pedida
              </button>
              <button
                type="button"
                onClick={() => trocarCaminho('nova')}
                className={`rounded-pilula border px-4 py-2 text-corpo font-semibold transition ${
                  caminho === 'nova'
                    ? 'border-acento/40 bg-acento-suave text-acento-intenso'
                    : 'border-linha bg-fundo text-texto-suave'
                }`}
                data-testid="adicionar-sessoes-caminho-nova"
              >
                Adicionar especialidade nova
              </button>
            </div>

            {caminho === 'repetir' ? (
              <ul className="mt-4 space-y-2" data-testid="adicionar-sessoes-itens">
                {itens.map((item) => {
                  const remessa = rotuloDaRemessa(item)
                  const escolhido =
                    form.especialidade_id === String(item.especialidade_id) &&
                    form.profissional_id === String(item.profissional_id)

                  return (
                    <li key={item.id}>
                      <button
                        type="button"
                        onClick={() => repetirItem(item)}
                        className={`w-full rounded-superficie border px-4 py-3 text-left transition ${
                          escolhido
                            ? 'border-acento/40 bg-acento-suave'
                            : 'border-linha bg-fundo hover:border-acento/40'
                        }`}
                        data-testid={`adicionar-sessoes-item-${item.id}`}
                      >
                        <span className="block text-corpo font-medium text-texto">
                          {item.especialidade?.nome ?? `Especialidade #${item.especialidade_id}`}
                          {remessa ? ` · ${remessa}` : ''}
                        </span>
                        <span className="block text-meta text-texto-suave">
                          {item.profissional?.nome ?? `Profissional #${item.profissional_id}`} ·{' '}
                          {item.quantidade} sessões
                        </span>
                      </button>
                    </li>
                  )
                })}
              </ul>
            ) : (
              <div className="mt-4 grid gap-3 md:grid-cols-2">
                <label className="space-y-1">
                  <span className="block text-meta font-semibold text-texto-suave">Especialidade</span>
                  <Select
                    value={form.especialidade_id}
                    onChange={(event) =>
                      setForm((atual) => ({
                        ...atual,
                        especialidade_id: event.target.value,
                        profissional_id: '',
                      }))
                    }
                    data-testid="adicionar-sessoes-especialidade"
                  >
                    <option value="">Selecione</option>
                    {especialidades.map((especialidade) => (
                      <option key={especialidade.id} value={especialidade.id}>
                        {rotuloEspecialidade(especialidade)}
                      </option>
                    ))}
                  </Select>
                </label>
                <label className="space-y-1">
                  <span className="block text-meta font-semibold text-texto-suave">Profissional</span>
                  <Select
                    value={form.profissional_id}
                    onChange={(event) =>
                      setForm((atual) => ({ ...atual, profissional_id: event.target.value }))
                    }
                    data-testid="adicionar-sessoes-profissional"
                  >
                    <option value="">Selecione</option>
                    {profissionaisDaEspecialidade.map((profissional) => (
                      <option key={profissional.id} value={profissional.id}>
                        {profissional.nome}
                      </option>
                    ))}
                  </Select>
                </label>
              </div>
            )}

            <label className="mt-4 block space-y-1">
              <span className="block text-meta font-semibold text-texto-suave">Quantidade de sessões</span>
              <input
                type="number"
                min="1"
                value={form.quantidade}
                onChange={(event) =>
                  setForm((atual) => ({ ...atual, quantidade: event.target.value }))
                }
                className={campo()}
                data-testid="adicionar-sessoes-quantidade"
              />
              {dados && dados.quantidade_padrao === null ? (
                <span className="block text-meta text-texto-suave">
                  Este convênio não tem sessões por guia cadastradas, então não há sugestão —
                  informe a quantidade.
                </span>
              ) : null}
            </label>

            {/* Avisos: nenhum desabilita o confirmar, e o que não se aplica não
                aparece. Convênio sem `sessoes_por_guia` não mostra o de limite,
                em vez de mostrar um limite inventado. */}
            {dados ? (
              <ul className="mt-4 space-y-2" data-testid="adicionar-sessoes-avisos">
                {dados.ja_existe_item_igual ? (
                  <Aviso icone={Repeat2}>
                    <span data-testid="aviso-repeticao">
                      Já existe {nomeEspecialidade ?? 'esta especialidade'} com este profissional
                      nesta solicitação.
                    </span>
                  </Aviso>
                ) : null}
                {dados.pedido_medico_dias !== null ? (
                  <Aviso icone={CalendarClock}>
                    <span data-testid="aviso-pedido-medico">
                      Pedido médico de {dados.pedido_medico_dias} dias atrás.
                    </span>
                  </Aviso>
                ) : null}
                {dados.sessoes_por_guia !== null ? (
                  <Aviso icone={Layers}>
                    <span data-testid="aviso-limite">
                      Este convênio libera {dados.sessoes_por_guia} sessões por guia.
                    </span>
                  </Aviso>
                ) : null}
                {dados.sessoes_na_cadeia !== null ? (
                  <Aviso icone={CircleAlert}>
                    <span data-testid="aviso-cadeia">
                      Já há {dados.sessoes_na_cadeia} sessões desta sequência nesta solicitação.
                    </span>
                  </Aviso>
                ) : null}
              </ul>
            ) : null}

            {erro ? (
              <p
                className="mt-4 rounded-superficie border border-perigo/30 bg-perigo-suave px-4 py-3 text-corpo text-perigo-texto"
                data-testid="adicionar-sessoes-erro"
              >
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
                onClick={() => void confirmar()}
                disabled={!podeConfirmar || adicionar.isPending}
                data-testid="adicionar-sessoes-confirmar"
              >
                {adicionar.isPending ? 'Adicionando...' : 'Adicionar sessões'}
              </Botao>
            </div>
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  )
}
