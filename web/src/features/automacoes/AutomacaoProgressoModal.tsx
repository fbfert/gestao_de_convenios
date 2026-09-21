import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react'
import { useFechamentoExplicito } from '../../lib/useFechamentoExplicito'
import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { LoaderCircle } from 'lucide-react'
import {
  getHttpErrorMessage,
  useAtualizarNomeMedico,
  useAutomacao,
  useReprocessarAutomacao,
} from './useAutomacoes'
import { useConfirm } from '../../components/ui/ConfirmDialog'
import { medicoAmbiguoInfo } from './medicoAmbiguo'
import type { AutomacaoExecucao } from './types'

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

/**
 * Passos reais que o worker segue no portal, por operação — não temos como
 * saber ao vivo em qual deles a execução está agora (worker não reporta
 * progresso incremental, só o resultado final), então isto é uma lista
 * informativa da sequência, não um rastreador passo-a-passo de verdade.
 */
const PASSOS_POR_OPERACAO: Record<string, string[]> = {
  gerar_guia: [
    'Login no portal da Unimed',
    'Localizar o beneficiário pela carteirinha',
    'Abrir a digitação de guia SP/SADT',
    'Selecionar o contratado (clínica)',
    'Buscar o profissional solicitante (CRM, nome ou fallback)',
    'Preencher os dados principais, procedimento e quantidade',
    'Anexar o pedido médico e demais documentos',
    'Selecionar o profissional executante',
    'Finalizar e confirmar o resultado',
  ],
  confirmar_guia_incerta: [
    'Login no portal da Unimed',
    'Abrir "Exames em aberto"',
    'Procurar o paciente pela carteirinha nas páginas listadas',
    'Confirmar se a guia foi criada ou não',
  ],
  consult_status_batch: [
    'Login no portal da Unimed',
    'Abrir "Exames em aberto" e localizar a guia pelo número',
    'Ler o status atual da guia',
  ],
  capture_authorization_data_batch: [
    'Login no portal da Unimed',
    'Abrir "Exames em aberto" e localizar a guia pelo número',
    'Capturar senha e validade de autorização',
  ],
}

function contextoExecucao(payload: Record<string, unknown> | null | undefined): {
  paciente: string | null
  especialidade: string | null
  profissional: string | null
} {
  const paciente = payload?.paciente as { nome?: string } | undefined

  return {
    paciente: paciente?.nome ?? null,
    especialidade: typeof payload?.especialidade === 'string' ? payload.especialidade : null,
    profissional: typeof payload?.profissional === 'string' ? payload.profissional : null,
  }
}

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

/**
 * Rótulo e tom por `guia_status` que o worker devolve no resultado (mesmos
 * valores usados em ImportarGuiasPage.tsx e statusTone.ts para a Guia em si).
 * `needs_verification` e `denied` terminam a execução como "succeeded" do
 * ponto de vista do worker — ele rodou até o fim sem quebrar — mas não são
 * uma boa notícia pro operador, por isso não usam o tom 'sucesso'.
 */
const GUIA_STATUS_ROTULO: Record<string, string> = {
  approved: 'Autorizado',
  finalized: 'Finalizado',
  under_review: 'Em análise',
  denied: 'Negado',
  canceled: 'Cancelado',
  needs_verification: 'Restrição administrativa — verificar',
}

const GUIA_STATUS_TOM: Record<string, 'sucesso' | 'erro' | 'alerta'> = {
  approved: 'sucesso',
  finalized: 'sucesso',
  under_review: 'alerta',
  denied: 'erro',
  canceled: 'erro',
  needs_verification: 'alerta',
}

/** Primeiro campo string não-vazio entre as chaves candidatas do resultado bruto do worker. */
function campoResultado(resultado: Record<string, unknown> | null | undefined, ...chaves: string[]): string | null {
  for (const chave of chaves) {
    const valor = resultado?.[chave]
    if (typeof valor === 'string' && valor.trim()) {
      return valor
    }
  }

  return null
}

function resultadoSucesso(execucao: AutomacaoExecucao): { tom: 'sucesso' | 'erro' | 'alerta'; titulo: string; mensagem: string; detalhes: string[] } {
  const resultado = execucao.resultado
  // guia_status/status_guia/portal_status: nomes diferentes usados pelos 4
  // tipos de operação (ver GerarGuiaUnimedService, ConfirmarGuiaIncertaUnimedService,
  // ConsultarStatusUnimedService) pro mesmo conceito.
  const guiaStatus = campoResultado(resultado, 'guia_status', 'status_guia', 'portal_status') ?? execucao.guia?.status ?? null
  const numeroGuia = campoResultado(resultado, 'numero_guia') ?? execucao.guia?.numero_guia ?? null
  const statusOperadora = campoResultado(resultado, 'unimed_status', 'status_operadora', 'situacao_portal')
  const mensagemResultado = campoResultado(resultado, 'message', 'mensagem')
  const senha = campoResultado(resultado, 'senha')
  const validadeSenha = campoResultado(resultado, 'validade_senha')

  const detalhes: string[] = []

  /*
   * A conferência de guias finalizadas devolve um resumo em vez de um status
   * de guia: ela responde sobre VÁRIAS guias de uma vez, e o que interessa é a
   * contagem dos três desfechos.
   *
   * Um lote que devolva zero finalizadas é sinal de alerta — provavelmente o
   * filtro de data do portal não foi limpo —, e é por isso que os três números
   * aparecem juntos em vez de só "concluído".
   */
  const resumo = (resultado as { resumo?: Record<string, number> } | null)?.resumo
  if (resumo) {
    detalhes.push(
      `${resumo.finalizadas ?? 0} finalizada(s) na operadora`,
      `${resumo.nao_finalizadas ?? 0} não finalizada(s)`,
      `${resumo.falhas ?? 0} falha(s)`,
    )
  }

  if (numeroGuia) {
    detalhes.push(`Guia nº ${numeroGuia}`)
  }
  if (statusOperadora && statusOperadora !== mensagemResultado) {
    detalhes.push(statusOperadora)
  }
  if (senha) {
    detalhes.push(`Senha: ${senha}${validadeSenha ? ` · validade ${validadeSenha}` : ''}`)
  }

  if (guiaStatus) {
    const tom = GUIA_STATUS_TOM[guiaStatus] ?? 'sucesso'
    return {
      tom,
      titulo: `Concluído — ${GUIA_STATUS_ROTULO[guiaStatus] ?? guiaStatus}`,
      mensagem:
        mensagemResultado ??
        (tom === 'sucesso' ? 'O robô concluiu a execução.' : 'Confira o motivo abaixo e os próximos passos em Automações.'),
      detalhes,
    }
  }

  return {
    tom: 'sucesso',
    titulo: 'Concluído com sucesso',
    mensagem: mensagemResultado ?? 'O robô concluiu a execução.',
    detalhes,
  }
}

function resultadoResumo(
  execucao: AutomacaoExecucao,
): { tom: 'sucesso' | 'erro' | 'alerta'; titulo: string; mensagem: string; detalhes: string[] } {
  const status = execucao.status

  switch (status) {
    case 'succeeded':
      return resultadoSucesso(execucao)
    case 'failed':
      return {
        tom: 'erro',
        titulo: 'A automação falhou',
        mensagem: 'Confira os detalhes e, se for o caso, tente novamente em Automações.',
        detalhes: [],
      }
    case 'needs_attention':
      return {
        tom: 'alerta',
        titulo: 'Precisa de atenção',
        mensagem: 'O robô não conseguiu concluir sozinho. Veja os detalhes em Automações.',
        detalhes: [],
      }
    case 'uncertain':
      return {
        tom: 'alerta',
        titulo: 'Resultado incerto',
        mensagem:
          'Não foi possível confirmar o resultado. Confira em Automações antes de tentar de novo — reenviar sem confirmar pode duplicar a ação.',
        detalhes: [],
      }
    default:
      return {
        tom: 'alerta',
        titulo: 'Status desconhecido',
        mensagem: 'Veja os detalhes completos em Automações.',
        detalhes: [],
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
  const confirmar = useConfirm()
  const reprocessar = useReprocessarAutomacao()
  const atualizarNomeMedico = useAtualizarNomeMedico()

  // Confirmar o médico (ou "Tentar novamente" direto daqui) reenfileira uma
  // execução NOVA — o modal passa a acompanhar essa nova execução no lugar
  // da original, sem fechar/reabrir e sem o chamador (SolicitacoesPage,
  // GuiasPage...) precisar saber que o id mudou. `execucaoId` (prop) só
  // marca QUAL execução o chamador queria abrir; `idEfetivo` é a que está
  // de fato sendo mostrada agora.
  const [idOverride, setIdOverride] = useState<number | null>(null)
  const idEfetivo = idOverride ?? execucaoId
  const [nomeCorrigido, setNomeCorrigido] = useState('')
  const [erroAcao, setErroAcao] = useState<string | null>(null)

  const execucaoQuery = useAutomacao(idEfetivo, { acompanharProgresso: open })
  const execucao = execucaoQuery.data
  const emAndamento = STATUS_EM_ANDAMENTO.includes(execucao?.status ?? 'queued')
  const statusAnteriorRef = useRef<string | null>(null)
  const medicoAmbiguo = execucao ? medicoAmbiguoInfo(execucao) : null

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
    // idEfetivo evita reexecutar o efeito a cada render por identidade nova
    // do array.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [execucao, queryClient, idEfetivo])

  useEffect(() => {
    if (open) {
      statusAnteriorRef.current = null
    }
  }, [open, execucaoId])

  // Um novo `execucaoId` do chamador é uma abertura nova do modal — descarta
  // qualquer retentativa da execução anterior.
  useEffect(() => {
    setIdOverride(null)
    setErroAcao(null)
  }, [execucaoId])

  // Só assume a sugestão da Unimed como valor inicial do campo editável uma
  // vez por execução — depois disso, o texto é do operador.
  useEffect(() => {
    if (medicoAmbiguo) {
      setNomeCorrigido(medicoAmbiguo.sugestaoPortal)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [execucao?.id])

  const handleReprocessar = async (execucaoIdAtual: number) => {
    const ok = await confirmar({
      titulo: 'Tentar novamente',
      descricao: `Reenfileira a execução #${execucaoIdAtual} para rodar de novo. Confirma?`,
      confirmarTexto: 'Tentar novamente',
      variante: 'primario',
    })

    if (!ok) {
      return
    }

    setErroAcao(null)
    try {
      const nova = await reprocessar.mutateAsync(execucaoIdAtual)
      setIdOverride(nova.id)
    } catch (error) {
      setErroAcao(getHttpErrorMessage(error, 'Não foi possível reprocessar a execução.'))
    }
  }

  const handleConfirmarNomeMedico = async (execucaoIdAtual: number, medicoId: number, nome: string) => {
    const ok = await confirmar({
      titulo: 'Confirmar médico e tentar novamente',
      descricao: `Atualiza o cadastro do médico para "${nome}" e reenfileira a execução #${execucaoIdAtual}. Confirma?`,
      confirmarTexto: 'Confirmar e tentar novamente',
      variante: 'primario',
    })

    if (!ok) {
      return
    }

    setErroAcao(null)
    try {
      await atualizarNomeMedico.mutateAsync({ id: medicoId, nome })
      const nova = await reprocessar.mutateAsync(execucaoIdAtual)
      setIdOverride(nova.id)
    } catch (error) {
      setErroAcao(getHttpErrorMessage(error, 'Não foi possível confirmar o médico e reprocessar.'))
    }
  }

  const passoAtual = etapaAtual(execucao?.status)
  const resultado = execucao && !emAndamento ? resultadoResumo(execucao) : null
  const contexto = contextoExecucao(execucao?.payload)
  const contextoTexto = [contexto.paciente, contexto.especialidade, contexto.profissional]
    .filter(Boolean)
    .join(' · ')
  const passos = execucao ? PASSOS_POR_OPERACAO[execucao.operacao] : undefined

  return (
    <Dialog {...useFechamentoExplicito(open, onClose)} className="relative z-(--z-dialogo)">
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
                  {idEfetivo ? ` · execução #${idEfetivo}` : ''}
                </DialogTitle>
                {contextoTexto ? (
                  <p
                    className="mt-1 text-meta font-medium text-cyan-100"
                    data-testid="automacao-progresso-contexto"
                  >
                    {contextoTexto}
                  </p>
                ) : null}
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
              {erroAcao ? (
                <p
                  className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100"
                  role="alert"
                  data-testid="automacao-progresso-erro-acao"
                >
                  {erroAcao}
                </p>
              ) : null}
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
                      {resultado.detalhes.length > 0 ? (
                        <ul className="mt-2 space-y-1 text-meta opacity-90" data-testid="automacao-progresso-detalhes">
                          {resultado.detalhes.map((linha) => (
                            <li key={linha}>{linha}</li>
                          ))}
                        </ul>
                      ) : null}
                      {execucao.erro_codigo ? (
                        <p className="mt-2 text-meta font-semibold opacity-90">{execucao.erro_codigo}</p>
                      ) : null}
                      {execucao.erro_mensagem ? (
                        <p className="mt-1 text-meta opacity-90">{execucao.erro_mensagem}</p>
                      ) : null}

                      {medicoAmbiguo ? (
                        <div
                          className="mt-3 space-y-3 rounded-2xl border border-amber-400/30 bg-amber-500/10 p-4 text-amber-100"
                          data-testid="automacao-progresso-medico-ambiguo"
                        >
                          <p>
                            Não foi possível confirmar com segurança o médico solicitante na Unimed.
                            <br />
                            Nome lido: <strong>{medicoAmbiguo.nomeLido}</strong>
                            <br />
                            Sugestão encontrada no portal: <strong>{medicoAmbiguo.sugestaoPortal}</strong>{' '}
                            ({medicoAmbiguo.similaridade}% de similaridade)
                          </p>

                          <label className="block space-y-2">
                            <span className="text-meta uppercase tracking-[0.2em] text-amber-200/80">
                              Nome correto do médico
                            </span>
                            <input
                              value={nomeCorrigido}
                              onChange={(event) => setNomeCorrigido(event.target.value)}
                              className="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20"
                              data-testid="automacao-progresso-medico-ambiguo-nome-input"
                            />
                          </label>

                          <button
                            type="button"
                            disabled={
                              atualizarNomeMedico.isPending ||
                              reprocessar.isPending ||
                              nomeCorrigido.trim() === ''
                            }
                            onClick={() =>
                              void handleConfirmarNomeMedico(
                                execucao.id,
                                medicoAmbiguo.medicoId,
                                nomeCorrigido.trim(),
                              )
                            }
                            className="inline-flex h-10 items-center justify-center rounded-2xl border border-cyan-400/30 bg-cyan-400/10 px-4 text-corpo font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:cursor-not-allowed disabled:opacity-50"
                            data-testid="automacao-progresso-medico-ambiguo-confirmar"
                          >
                            Confirmar e tentar novamente
                          </button>
                        </div>
                      ) : null}

                      <div className="mt-3 flex flex-wrap items-center gap-4">
                        {!medicoAmbiguo && ['failed', 'needs_attention'].includes(execucao.status) ? (
                          <button
                            type="button"
                            onClick={() => void handleReprocessar(execucao.id)}
                            disabled={reprocessar.isPending}
                            className="inline-flex h-10 items-center justify-center rounded-2xl border border-cyan-400/30 bg-cyan-400/10 px-4 text-corpo font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:cursor-not-allowed disabled:opacity-50"
                            data-testid="automacao-progresso-tentar-novamente"
                          >
                            Tentar novamente
                          </button>
                        ) : null}
                        {resultado.tom !== 'sucesso' ? (
                          <Link
                            to={`/automacoes/${execucao.id}`}
                            className="inline-block text-meta font-semibold underline decoration-current/40 underline-offset-4"
                          >
                            Ver detalhes em Automações
                          </Link>
                        ) : null}
                      </div>
                    </div>
                  ) : (
                    <div
                      className="rounded-superficie border border-linha bg-fundo p-5 shadow-e1"
                      data-testid="automacao-progresso-execucao"
                    >
                      <div className="flex items-center gap-3 text-corpo text-slate-300">
                        <LoaderCircle className="size-4 shrink-0 animate-spin text-cyan-200" aria-hidden="true" />
                        {execucao.status === 'running' ? mensagemExecutando : 'Aguardando um worker disponível para iniciar...'}
                      </div>

                      {execucao.status === 'running' && passos ? (
                        <>
                          <p className="mt-4 text-meta text-slate-400">
                            Passos que o robô costuma seguir aqui — não é possível saber em tempo
                            real em qual deles a execução está agora:
                          </p>
                          <ol className="mt-2 space-y-1.5" data-testid="automacao-progresso-passos">
                            {passos.map((passo, index) => (
                              <li
                                key={passo}
                                className="flex items-start gap-2 text-meta text-slate-300"
                              >
                                <span className="mt-0.5 text-slate-500">{index + 1}.</span>
                                <span>{passo}</span>
                              </li>
                            ))}
                          </ol>
                        </>
                      ) : null}
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
