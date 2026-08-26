import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react'
import { useEffect, useRef } from 'react'
import { Link } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { LoaderCircle } from 'lucide-react'
import { useAutomacao } from './useAutomacoes'

type AutomacaoProgressoModalProps = {
  execucaoId: number | null
  onClose: () => void
  /** Título do modal. Padrão cobre o caso original (geração de guia). */
  titulo?: string
  descricao?: string
  /** Texto enquanto o worker está de fato rodando (status running). */
  mensagemExecutando?: string
  /**
   * Query keys para invalidar quando a execução sai de queued/running para um
   * status terminal — a linha/lista de origem só reflete o resultado depois
   * disso, sem isso o usuário precisaria de F5 pra ver.
   */
  queryKeysInvalidar?: string[][]
}

const STATUS_EM_ANDAMENTO = ['queued', 'running']

const ETAPAS = [
  { chave: 'queued', rotulo: 'Na fila' },
  { chave: 'running', rotulo: 'Em execução' },
  { chave: 'resultado', rotulo: 'Resultado' },
] as const

function etapaAtual(status: string | undefined): number {
  if (!status || status === 'queued') {
    return 0
  }

  if (status === 'running') {
    return 1
  }

  return 2
}

function resultadoResumo(status: string): { tom: 'sucesso' | 'erro' | 'alerta'; titulo: string; mensagem: string } {
  switch (status) {
    case 'succeeded':
      return {
        tom: 'sucesso',
        titulo: 'Concluído com sucesso',
        mensagem: 'O robô concluiu a execução.',
      }
    case 'failed':
      return {
        tom: 'erro',
        titulo: 'A automação falhou',
        mensagem: 'Confira os detalhes e, se for o caso, tente novamente em Automações.',
      }
    case 'needs_attention':
      return {
        tom: 'alerta',
        titulo: 'Precisa de atenção',
        mensagem: 'O robô não conseguiu concluir sozinho. Veja os detalhes em Automações.',
      }
    case 'uncertain':
      return {
        tom: 'alerta',
        titulo: 'Resultado incerto',
        mensagem:
          'Não foi possível confirmar o resultado. Confira em Automações antes de tentar de novo — reenviar sem confirmar pode duplicar a ação.',
      }
    default:
      return {
        tom: 'alerta',
        titulo: 'Status desconhecido',
        mensagem: 'Veja os detalhes completos em Automações.',
      }
  }
}

const tomClasses: Record<'sucesso' | 'erro' | 'alerta', string> = {
  sucesso: 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100',
  erro: 'border-rose-400/20 bg-rose-500/10 text-rose-100',
  alerta: 'border-amber-400/20 bg-amber-500/10 text-amber-100',
}

export function AutomacaoProgressoModal({
  execucaoId,
  onClose,
  titulo = 'Enviando para a Unimed',
  descricao = 'Acompanhe aqui a evolução do robô que gera a guia no portal da Unimed.',
  mensagemExecutando = 'O robô está preenchendo e enviando o pedido no portal da Unimed...',
  queryKeysInvalidar = [],
}: AutomacaoProgressoModalProps) {
  const open = execucaoId !== null
  const queryClient = useQueryClient()
  const execucaoQuery = useAutomacao(execucaoId, { acompanharProgresso: open })
  const execucao = execucaoQuery.data
  const emAndamento = STATUS_EM_ANDAMENTO.includes(execucao?.status ?? 'queued')
  const statusAnteriorRef = useRef<string | null>(null)

  useEffect(() => {
    if (!execucao) {
      return
    }

    const eraEmAndamento = statusAnteriorRef.current
      ? STATUS_EM_ANDAMENTO.includes(statusAnteriorRef.current)
      : true
    statusAnteriorRef.current = execucao.status

    if (eraEmAndamento && !STATUS_EM_ANDAMENTO.includes(execucao.status)) {
      queryKeysInvalidar.forEach((queryKey) => {
        void queryClient.invalidateQueries({ queryKey })
      })
    }
    // queryKeysInvalidar é passado inline pelos chamadores — comparar pelo
    // execucaoId evita reexecutar o efeito a cada render por identidade nova
    // do array.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [execucao, queryClient, execucaoId])

  useEffect(() => {
    if (open) {
      statusAnteriorRef.current = null
    }
  }, [open, execucaoId])

  const passoAtual = etapaAtual(execucao?.status)
  const resultado = execucao && !emAndamento ? resultadoResumo(execucao.status) : null

  return (
    <Dialog open={open} onClose={onClose} className="relative z-(--z-dialogo)">
      <DialogBackdrop className="fixed inset-0 bg-slate-950/75 backdrop-blur-sm" />
      <div className="fixed inset-0 overflow-y-auto p-4 sm:p-6">
        <div className="flex min-h-full items-center justify-center">
          <DialogPanel
            className="w-full max-w-xl rounded-janela border border-white/10 bg-slate-950 p-6 text-white shadow-e3 shadow-black/60"
            data-testid="automacao-progresso-modal"
          >
            <div className="flex items-start justify-between gap-4">
              <div>
                <DialogTitle className="text-titulo font-semibold">
                  {titulo}
                  {execucaoId ? ` · execução #${execucaoId}` : ''}
                </DialogTitle>
                <p className="mt-1 text-corpo text-slate-300">{descricao}</p>
              </div>
              <button
                type="button"
                onClick={onClose}
                className="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-corpo font-semibold text-white transition hover:bg-white/10"
                data-testid="automacao-progresso-fechar"
              >
                Fechar
              </button>
            </div>

            <div className="mt-6 space-y-6">
              {execucaoQuery.isLoading ? (
                <div className="rounded-superficie border border-linha bg-fundo p-5 shadow-e1 text-corpo text-slate-300">
                  Carregando execução...
                </div>
              ) : execucaoQuery.isError || !execucao ? (
                <div className="rounded-3xl border border-rose-400/20 bg-rose-500/10 p-5 text-corpo text-rose-100">
                  Não foi possível carregar o andamento da automação.
                </div>
              ) : (
                <>
                  <ol className="flex items-center gap-2" data-testid="automacao-progresso-etapas">
                    {ETAPAS.map((etapa, index) => {
                      const concluida = index < passoAtual
                      const ativa = index === passoAtual

                      return (
                        <li key={etapa.chave} className="flex flex-1 items-center gap-2">
                          <div
                            className={`flex size-8 shrink-0 items-center justify-center rounded-full border text-meta font-semibold ${
                              concluida
                                ? 'border-emerald-400/40 bg-emerald-400/15 text-emerald-100'
                                : ativa
                                  ? 'border-cyan-300/50 bg-cyan-400/15 text-cyan-100'
                                  : 'border-white/10 bg-white/5 text-slate-400'
                            }`}
                          >
                            {ativa && emAndamento ? (
                              <LoaderCircle className="size-4 animate-spin" aria-hidden="true" />
                            ) : (
                              index + 1
                            )}
                          </div>
                          <span
                            className={`text-meta font-medium ${
                              concluida || ativa ? 'text-white' : 'text-slate-400'
                            }`}
                          >
                            {etapa.rotulo}
                          </span>
                          {index < ETAPAS.length - 1 ? (
                            <span
                              className={`h-px flex-1 ${
                                concluida ? 'bg-emerald-400/40' : 'bg-white/10'
                              }`}
                              aria-hidden="true"
                            />
                          ) : null}
                        </li>
                      )
                    })}
                  </ol>

                  {resultado ? (
                    <div
                      className={`rounded-3xl border p-5 text-corpo ${tomClasses[resultado.tom]}`}
                      data-testid="automacao-progresso-resultado"
                    >
                      <p className="font-semibold">{resultado.titulo}</p>
                      <p className="mt-1">{resultado.mensagem}</p>
                      {execucao.erro_codigo ? (
                        <p className="mt-2 text-meta font-semibold opacity-90">{execucao.erro_codigo}</p>
                      ) : null}
                      {execucao.erro_mensagem ? (
                        <p className="mt-1 text-meta opacity-90">{execucao.erro_mensagem}</p>
                      ) : null}
                      {resultado.tom !== 'sucesso' ? (
                        <Link
                          to={`/automacoes/${execucao.id}`}
                          className="mt-3 inline-block text-meta font-semibold underline decoration-current/40 underline-offset-4"
                        >
                          Ver detalhes em Automações
                        </Link>
                      ) : null}
                    </div>
                  ) : (
                    <div className="flex items-center gap-3 rounded-superficie border border-linha bg-fundo p-5 shadow-e1 text-corpo text-slate-300">
                      <LoaderCircle className="size-4 shrink-0 animate-spin text-cyan-200" aria-hidden="true" />
                      {execucao.status === 'running' ? mensagemExecutando : 'Aguardando um worker disponível para iniciar...'}
                    </div>
                  )}
                </>
              )}
            </div>
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  )
}

export type { AutomacaoProgressoModalProps }
