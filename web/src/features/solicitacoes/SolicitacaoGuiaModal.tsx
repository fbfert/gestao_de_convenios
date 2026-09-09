import { useFechamentoExplicito } from '../../lib/useFechamentoExplicito'
import {
  Dialog,
  DialogBackdrop,
  DialogPanel,
  DialogTitle,
  Tab,
  TabGroup,
  TabList,
  TabPanel,
  TabPanels,
} from '@headlessui/react'
import { Plus } from 'lucide-react'
import { useEffect, useState, type ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { usePode } from '../../lib/permissoes'
import type { MedicoRef } from '../../lib/queries/useReferenceData'
import { GuiaDetalheResumo } from '../guias/GuiaDetalheResumo'
import { getHttpErrorMessage, useGuia } from '../guias/useGuias'
import { useAtualizarSolicitacao, type SolicitacaoEditForm } from './useSolicitacoes'
import { SolicitacaoAnexos } from './SolicitacaoAnexos'
import { rotuloDaRemessa } from './solicitacaoItens'
import type { Solicitacao, SolicitacaoItem } from './types'
import { Botao } from '../../components/ui/Botao'
import { CidsCampo } from '../cids/CidsCampo'
import { SelecionarMedicoModal } from './SelecionarMedicoModal'

type SolicitacaoGuiaModalProps = {
  solicitacao: Solicitacao | null
  onClose: () => void
  /**
   * Ausente quando a pessoa não tem `solicitacoes.manage` ou o status não
   * aceita item novo. Quem decide é a página, que já conhece as duas coisas.
   */
  onAdicionarSessoes?: () => void
}

function fieldClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function DetailItem({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1">
      <p className="text-meta uppercase tracking-[0.25em] text-slate-400">{label}</p>
      <div className="mt-2 text-corpo font-medium text-white">{children}</div>
    </div>
  )
}

const formVazio: SolicitacaoEditForm = {
  medico_id: '',
  cid_ids: [],
  solicitado_em: '',
  observacoes: '',
}

function SolicitacaoDados({ solicitacao }: { solicitacao: Solicitacao }) {
  const pode = usePode()
  const atualizar = useAtualizarSolicitacao()

  const [editando, setEditando] = useState(false)
  const [form, setForm] = useState<SolicitacaoEditForm>(formVazio)
  const [erro, setErro] = useState<string | null>(null)
  const [medicoSelecionado, setMedicoSelecionado] = useState<MedicoRef | null>(null)
  const [medicoModalAberto, setMedicoModalAberto] = useState(false)

  useEffect(() => {
    setEditando(false)
  }, [solicitacao.id])

  const iniciarEdicao = () => {
    setForm({
      medico_id: String(solicitacao.medico_id),
      cid_ids: (solicitacao.cids ?? []).map((cid) => String(cid.id)),
      solicitado_em: solicitacao.solicitado_em.slice(0, 10),
      observacoes: solicitacao.observacoes ?? '',
    })
    setMedicoSelecionado(
      solicitacao.medico ? { ...solicitacao.medico, telefone: '', email: null, ativo: true } : null,
    )
    setErro(null)
    setEditando(true)
  }

  const salvar = async () => {
    setErro(null)

    try {
      await atualizar.mutateAsync({ id: solicitacao.id, payload: form })
      setEditando(false)
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível salvar as alterações.'))
    }
  }

  return (
    <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h3 className="text-subtitulo font-semibold text-white">Dados da solicitação</h3>
        {pode('solicitacoes.manage') && !editando ? (
          <button
            type="button"
            onClick={iniciarEdicao}
            className="rounded-2xl border border-cyan-300/40 bg-cyan-400/15 px-4 py-2 text-corpo font-medium text-cyan-50 transition hover:bg-cyan-400/25"
            data-testid="solicitacao-modal-ativar-edicao"
          >
            Ativar edição
          </button>
        ) : null}
      </div>

      <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <DetailItem label="Paciente">{solicitacao.paciente?.nome ?? solicitacao.paciente_id}</DetailItem>
        <DetailItem label="Convênio">{solicitacao.convenio?.nome ?? solicitacao.convenio_id}</DetailItem>
      </div>
      <p className="mt-2 text-meta text-slate-400">
        Paciente e convênio não são editáveis aqui: guia, antecipação e conciliação já geradas usam esses dados.
      </p>

      {!editando ? (
        <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <DetailItem label="Médico solicitante">{solicitacao.medico?.nome ?? solicitacao.medico_id}</DetailItem>
          <DetailItem label="CID">
            {solicitacao.cids && solicitacao.cids.length > 0
              ? solicitacao.cids.map((cid) => `${cid.codigo} — ${cid.descricao}`).join('; ')
              : '-'}
          </DetailItem>
          <DetailItem label="Data da solicitação">{solicitacao.solicitado_em}</DetailItem>
          <DetailItem label="Observações">{solicitacao.observacoes ?? '-'}</DetailItem>
        </div>
      ) : (
        <div className="mt-4 space-y-4">
          <div className="grid gap-4 md:grid-cols-2">
            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Médico solicitante</span>
              <button
                type="button"
                onClick={() => setMedicoModalAberto(true)}
                className={`${fieldClasses()} flex items-center justify-between text-left`}
                data-testid="solicitacao-modal-medico"
              >
                <span className={medicoSelecionado ? '' : 'text-slate-400'}>
                  {medicoSelecionado ? medicoSelecionado.nome : 'Buscar médico...'}
                </span>
                <span className="text-cyan-200">🔍</span>
              </button>

              <SelecionarMedicoModal
                open={medicoModalAberto}
                onClose={() => setMedicoModalAberto(false)}
                onSelecionar={(medico) => {
                  setMedicoSelecionado(medico)
                  setForm((current) => ({ ...current, medico_id: String(medico.id) }))
                }}
              />
            </label>
            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">CID</span>
              <CidsCampo
                value={form.cid_ids}
                onChange={(cidIds) => setForm((current) => ({ ...current, cid_ids: cidIds }))}
                testIdPrefix="solicitacao-modal-cid"
              />
            </label>
          </div>

          <label className="block space-y-2">
            <span className="text-corpo font-medium text-slate-200">Data da solicitação</span>
            <input
              type="date"
              value={form.solicitado_em}
              onChange={(event) => setForm((current) => ({ ...current, solicitado_em: event.target.value }))}
              className={fieldClasses()}
              data-testid="solicitacao-modal-data"
            />
          </label>

          <label className="block space-y-2">
            <span className="text-corpo font-medium text-slate-200">Observações</span>
            <textarea
              value={form.observacoes}
              onChange={(event) => setForm((current) => ({ ...current, observacoes: event.target.value }))}
              className={fieldClasses()}
              rows={3}
              data-testid="solicitacao-modal-observacoes"
            />
          </label>

          {erro ? (
            <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">{erro}</p>
          ) : null}

          <div className="flex gap-2">
            <Botao
              type="button"
              variante="primario"
              onClick={() => void salvar()}
              disabled={atualizar.isPending || form.cid_ids.length === 0}
              data-testid="solicitacao-modal-salvar"
            >
              {atualizar.isPending ? 'Salvando...' : 'Salvar alterações'}
            </Botao>
            <Botao
              type="button"
              variante="secundario"
              onClick={() => setEditando(false)}
              data-testid="solicitacao-modal-cancelar"
            >
              Cancelar
            </Botao>
          </div>
        </div>
      )}
    </section>
  )
}

/**
 * O conteúdo de UMA aba: a guia daquele item, ou o vazio quando ela ainda não
 * saiu.
 *
 * A busca do detalhe fica aqui dentro, e não no componente pai, para só a aba
 * visível consultar — um pedido com seis especialidades não deve disparar seis
 * requisições ao abrir o modal.
 */
function AbaDoItem({ item }: { item: SolicitacaoItem }) {
  const guiaQuery = useGuia(item.guia?.id ?? null)

  if (!item.guia) {
    return (
      <div
        className="rounded-3xl border border-amber-400/20 bg-amber-500/10 p-5 text-corpo text-amber-50"
        data-testid={`solicitacao-guia-empty-${item.id}`}
      >
        Aguardando geração da guia para {item.especialidade?.nome ?? 'esta especialidade'}.
      </div>
    )
  }

  if (guiaQuery.isLoading) {
    return (
      <div
        className="rounded-superficie border border-linha bg-fundo p-5 shadow-e1 text-corpo text-slate-300"
        data-testid="solicitacao-guia-loading"
      >
        Carregando detalhes da guia...
      </div>
    )
  }

  if (guiaQuery.isError || !guiaQuery.data) {
    return (
      <div
        className="space-y-2 rounded-3xl border border-rose-400/20 bg-rose-500/10 p-5 text-corpo text-rose-100"
        data-testid="solicitacao-guia-error"
      >
        <p>Não foi possível carregar a guia vinculada.</p>
        <p className="text-meta text-rose-100/80">
          {getHttpErrorMessage(guiaQuery.error, 'Confira o vínculo da solicitação e tente novamente.')}
        </p>
      </div>
    )
  }

  return (
    <div className="space-y-3" data-testid="solicitacao-guia-content">
      <Link
        to={`/guias/${item.guia.id}`}
        className="inline-flex items-center gap-2 text-corpo font-semibold text-acento underline underline-offset-4 transition hover:text-acento-intenso"
        data-testid={`solicitacao-guia-link-${item.id}`}
      >
        {item.guia.numero_operadora
          ? `Guia ${item.guia.numero_operadora}`
          : `Abrir guia #${item.guia.id}`}
        <span aria-hidden="true">→</span>
      </Link>
      <GuiaDetalheResumo guia={guiaQuery.data} />
    </div>
  )
}

/**
 * Rótulo da aba: especialidade e, quando houver, o número da operadora.
 *
 * Nunca o identificador interno e nunca o valor de preenchimento do convênio
 * manual — "Guia 1 / Guia 2" numeraria a posição na tela, que não é um dado do
 * mundo; quem opera procura pela especialidade e confere pelo número.
 */
function rotuloDaAba(item: SolicitacaoItem): string {
  const especialidade = item.especialidade?.nome ?? 'Especialidade'
  // A remessa entra ANTES do número: com duas abas da mesma especialidade, é
  // ela que diz qual é qual — e a segunda pode ainda nem ter número.
  const remessa = rotuloDaRemessa(item)
  const base = remessa ? `${especialidade} · ${remessa}` : especialidade

  return item.guia?.numero_operadora ? `${base} · Guia ${item.guia.numero_operadora}` : base
}

export function SolicitacaoGuiaModal({
  solicitacao,
  onClose,
  onAdicionarSessoes,
}: SolicitacaoGuiaModalProps) {
  const open = solicitacao !== null
  // A fonte são os ITENS. Havia um `solicitacao.guia` — um `hasOne` sem
  // ordenação, que devolvia uma guia qualquer —, removido em
  // `remover-relacao-guia-legada` (ADR-27).
  const itens = solicitacao?.itens ?? []

  return (
    <Dialog {...useFechamentoExplicito(open, onClose)} className="relative z-(--z-dialogo)">
      <DialogBackdrop className="fixed inset-0 bg-slate-950/75 backdrop-blur-sm" />
      <div className="fixed inset-0 overflow-y-auto p-4 sm:p-6">
        <div className="flex min-h-full items-center justify-center">
          <DialogPanel
            className="w-full max-w-6xl rounded-janela border border-white/10 bg-slate-950 p-6 text-white shadow-e3 shadow-black/60"
            data-testid="solicitacao-guia-modal"
          >
            <div className="flex items-start justify-between gap-4">
              <div>
                <DialogTitle className="text-titulo font-semibold">
                  Detalhes da solicitação{solicitacao ? ` #${solicitacao.id}` : ''}
                </DialogTitle>
                <p className="mt-1 text-corpo text-slate-300">
                  Dados da solicitação, anexos do pedido e da guia vinculada, sem sair da lista.
                </p>
              </div>
              <div className="flex shrink-0 items-center gap-2">
                {onAdicionarSessoes ? (
                  <button
                    type="button"
                    onClick={onAdicionarSessoes}
                    className="inline-flex items-center gap-2 rounded-full border border-acento/40 bg-acento-suave px-3 py-2 text-corpo font-semibold text-acento-intenso transition hover:bg-acento-suave/70"
                    data-testid="solicitacao-guia-modal-adicionar-sessoes"
                  >
                    <Plus className="size-4" aria-hidden="true" />
                    Adicionar sessões
                  </button>
                ) : null}
                <button
                  type="button"
                  onClick={onClose}
                  className="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-corpo font-semibold text-white transition hover:bg-white/10"
                >
                  Fechar
                </button>
              </div>
            </div>

            <div className="mt-6 space-y-6">
              {solicitacao ? <SolicitacaoDados solicitacao={solicitacao} /> : null}

              {solicitacao ? <SolicitacaoAnexos solicitacao={solicitacao} /> : null}

              {itens.length === 0 ? (
                /* Solicitação legada, anterior à multi-especialidade: não há
                   item para virar aba, então a mensagem antiga continua. */
                <div
                  className="rounded-3xl border border-amber-400/20 bg-amber-500/10 p-5 text-corpo text-amber-50"
                  data-testid="solicitacao-guia-empty"
                >
                  Esta solicitação ainda não possui uma guia vinculada.
                </div>
              ) : (
                <TabGroup data-testid="solicitacao-guias-abas">
                  {/* Uma aba por ITEM, inclusive sem guia: ver que faltam duas
                      de três especialidades é a informação mais importante
                      desta tela, e abas só para as guias existentes esconderiam
                      exatamente o que exige ação. */}
                  <TabList className="flex flex-wrap gap-2">
                    {itens.map((item) => (
                      <Tab
                        key={item.id}
                        className="rounded-pilula border border-linha px-4 py-2 text-corpo font-medium text-texto-suave transition data-[selected]:border-acento/40 data-[selected]:bg-acento-suave data-[selected]:text-acento-intenso"
                        data-testid={`solicitacao-guia-aba-${item.id}`}
                      >
                        {rotuloDaAba(item)}
                      </Tab>
                    ))}
                  </TabList>
                  <TabPanels className="mt-4">
                    {itens.map((item) => (
                      <TabPanel key={item.id}>
                        <AbaDoItem item={item} />
                      </TabPanel>
                    ))}
                  </TabPanels>
                </TabGroup>
              )}
            </div>
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  )
}
