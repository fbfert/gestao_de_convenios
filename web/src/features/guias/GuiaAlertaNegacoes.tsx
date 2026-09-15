import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Botao } from '../../components/ui/Botao'
import {
  getHttpErrorMessage,
  useGuiasAlertaNegacao,
  useGuiasAlertaRestricao,
  useOcultarAlertaNegacaoGuia,
  useOcultarAlertaRestricaoGuia,
} from './useGuias'
import type { Guia } from './types'

function formatDate(value: string) {
  return new Intl.DateTimeFormat('pt-BR').format(new Date(value))
}

/**
 * O que muda entre o alerta de guias negadas e o de guias com restrição.
 *
 * Os dois seguem exatamente as mesmas regras — status, alerta não ocultado,
 * fora do rastro histórico — e oferecem as mesmas três ações. Só mudam os
 * textos e os testids, então vale um só componente parametrizado: duplicar o
 * arquivo faria as duas superfícies divergirem na primeira correção que
 * alguém fizesse em uma delas.
 */
type Variante = {
  testid: string
  /** "N guia(s) negada(s) precisa(m) de revisão" */
  titulo: (quantidade: number) => string
  /** Completa "… · <isto> 12/09/2025" na linha de cada guia. */
  desde: string
  vazio: string
  dialogo: {
    titulo: (guia: Guia) => string
    descricao: (guia: Guia) => string
  }
}

const NEGADAS: Variante = {
  testid: 'guia-alerta-negacao',
  titulo: (n) => (n === 1 ? '1 guia negada precisa de revisão' : `${n} guias negadas precisam de revisão`),
  desde: 'negada em',
  vazio: 'Nenhuma guia negada pendente de revisão.',
  dialogo: {
    titulo: (guia) => `Guia ${guia.numero_guia ?? ''} negada`,
    descricao: (guia) =>
      `${guia.paciente?.nome ?? `Paciente #${guia.paciente_id}`} precisa de uma nova solicitação para retomar o tratamento? Você pode abrir uma agora, avisar que já solicitou por fora, ou só ocultar este alerta.`,
  },
}

const RESTRICAO: Variante = {
  testid: 'guia-alerta-restricao',
  titulo: (n) =>
    n === 1 ? '1 guia com restrição precisa de verificação' : `${n} guias com restrição precisam de verificação`,
  desde: 'em restrição desde',
  vazio: 'Nenhuma guia com restrição pendente de verificação.',
  dialogo: {
    titulo: (guia) => `Guia ${guia.numero_guia ?? ''} com restrição`,
    descricao: (guia) =>
      `A operadora apontou uma restrição administrativa em ${guia.paciente?.nome ?? `Paciente #${guia.paciente_id}`}. Depois de verificar no portal, você pode abrir uma nova solicitação, avisar que já solicitou por fora, ou só ocultar este alerta.`,
  },
}

type EstadoDoAlerta = ReturnType<typeof useAlertaDeGuias>

/**
 * Estado e ações compartilhadas entre o banner e o modal: mesma fonte de
 * dados, mesma mutação de ocultar. Recebe os hooks por parâmetro para servir
 * às duas variantes sem duplicar a lógica.
 */
function useAlertaDeGuias(
  guiasQuery: ReturnType<typeof useGuiasAlertaNegacao>,
  ocultarAlerta: ReturnType<typeof useOcultarAlertaNegacaoGuia>,
) {
  const navigate = useNavigate()
  const [guiaModal, setGuiaModal] = useState<Guia | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)

  const guias = guiasQuery.data ?? []

  const ocultar = async (guia: Guia) => {
    setActionError(null)
    try {
      await ocultarAlerta.mutateAsync(guia.id)
      setGuiaModal(null)
    } catch (error) {
      setActionError(getHttpErrorMessage(error, 'Não foi possível ocultar o alerta.'))
    }
  }

  const novaSolicitacao = async (guia: Guia) => {
    setActionError(null)
    try {
      await ocultarAlerta.mutateAsync(guia.id)
      setGuiaModal(null)
      const params = new URLSearchParams({ paciente_id: String(guia.paciente_id) })
      if (guia.convenio_id) params.set('convenio_id', String(guia.convenio_id))
      if (guia.especialidade_id) params.set('especialidade_id', String(guia.especialidade_id))
      if (guia.profissional_id) params.set('profissional_id', String(guia.profissional_id))
      navigate(`/solicitacoes/nova?${params.toString()}`)
    } catch (error) {
      setActionError(getHttpErrorMessage(error, 'Não foi possível ocultar o alerta.'))
    }
  }

  return { guiasQuery, guias, ocultarAlerta, guiaModal, setGuiaModal, actionError, ocultar, novaSolicitacao }
}

/**
 * Lista de guias pendentes. O botão abre o diálogo de ação por guia — ele não
 * oculta direto, por isso chama-se "Ações" e não "Ocultar".
 */
function ListaDeGuias({
  guias,
  variante,
  onAcao,
}: {
  guias: Guia[]
  variante: Variante
  onAcao: (guia: Guia) => void
}) {
  return (
    <div className="space-y-2">
      {guias.map((guia) => (
        <div
          key={guia.id}
          className="flex flex-wrap items-center justify-between gap-3 rounded-superficie border border-linha bg-superficie p-4"
          data-testid={`${variante.testid}-${guia.id}`}
        >
          <div>
            <p className="font-semibold text-texto">
              {guia.numero_guia ?? 'Sem número'} · {guia.paciente?.nome ?? `Paciente #${guia.paciente_id}`}
            </p>
            <p className="mt-1 text-meta text-texto-suave">
              {guia.especialidade?.nome ?? 'Especialidade não informada'} ·{' '}
              {guia.convenio?.nome ?? 'Convênio não informado'} · {variante.desde}{' '}
              {formatDate(guia.updated_at)}
            </p>
          </div>
          <Botao
            variante="secundario"
            tamanho="sm"
            onClick={() => onAcao(guia)}
            data-testid={`${variante.testid}-ocultar-${guia.id}`}
          >
            Ações
          </Botao>
        </div>
      ))}
    </div>
  )
}

/** Diálogo de ação por guia: abrir nova solicitação, avisar que já solicitou, ou ocultar. */
function DialogoAcaoGuia({
  guia,
  variante,
  pending,
  onCancelar,
  onOcultar,
  onJaSolicitei,
  onNovaSolicitacao,
}: {
  guia: Guia
  variante: Variante
  pending: boolean
  onCancelar: () => void
  onOcultar: () => void
  onJaSolicitei: () => void
  onNovaSolicitacao: () => void
}) {
  return (
    <div
      className="fixed inset-0 z-(--z-dialogo) flex items-center justify-center bg-texto/40 p-4"
      role="alertdialog"
      aria-modal="true"
      aria-label={variante.dialogo.titulo(guia)}
      data-testid={`${variante.testid}-modal`}
    >
      <div className="w-full max-w-md space-y-4 rounded-janela border border-linha bg-superficie-elevada p-6 shadow-e3">
        <div>
          <h3 className="text-subtitulo font-semibold text-texto">{variante.dialogo.titulo(guia)}</h3>
          <p className="mt-2 text-corpo leading-6 text-texto-suave">{variante.dialogo.descricao(guia)}</p>
        </div>

        <div className="flex flex-wrap justify-end gap-3">
          <Botao type="button" variante="secundario" onClick={onCancelar} disabled={pending}>
            Cancelar
          </Botao>
          <Botao
            type="button"
            variante="secundario"
            onClick={onOcultar}
            disabled={pending}
            data-testid={`${variante.testid}-pode-ocultar`}
          >
            Pode ocultar
          </Botao>
          <Botao
            type="button"
            variante="secundario"
            onClick={onJaSolicitei}
            disabled={pending}
            data-testid={`${variante.testid}-ja-solicitei`}
          >
            Já solicitei
          </Botao>
          <Botao
            type="button"
            variante="primario"
            onClick={onNovaSolicitacao}
            disabled={pending}
            data-testid={`${variante.testid}-nova-solicitacao`}
          >
            Nova Solicitação
          </Botao>
        </div>
      </div>
    </div>
  )
}

/**
 * Banner da tela de Guias, recolhido por padrão.
 *
 * Com as duas listas abertas, o topo da tela virava uma parede de cartões e a
 * listagem de guias — que é o assunto da página — ficava abaixo da dobra. O
 * cabeçalho continua dizendo quantas são, então a informação não se perde ao
 * recolher; some só o detalhamento.
 *
 * Sempre começa recolhido: expandir vale para a visita, e não fica guardado.
 */
function BannerAlerta({ estado, variante }: { estado: EstadoDoAlerta; variante: Variante }) {
  const { guiasQuery, guias, ocultarAlerta, guiaModal, setGuiaModal, actionError, ocultar, novaSolicitacao } =
    estado
  const [aberto, setAberto] = useState(false)

  if (guiasQuery.isLoading || guiasQuery.isError || guias.length === 0) {
    return null
  }

  const painelId = `${variante.testid}-painel`

  return (
    <section
      className="space-y-3 rounded-janela border border-perigo/30 bg-perigo-suave p-5 shadow-e1"
      data-testid={`${variante.testid}es`}
    >
      <button
        type="button"
        onClick={() => setAberto((atual) => !atual)}
        aria-expanded={aberto}
        aria-controls={painelId}
        className="flex w-full items-center justify-between gap-4 text-left"
        data-testid={`${variante.testid}-alternar`}
      >
        <span>
          <span className="block text-meta font-semibold uppercase tracking-[0.2em] text-perigo-texto">
            Atenção
          </span>
          <span className="mt-1 block text-titulo font-semibold text-texto">
            {variante.titulo(guias.length)}
          </span>
        </span>
        <span className="shrink-0 text-corpo font-semibold text-perigo-texto">
          {aberto ? 'Recolher' : 'Ver guias'}
        </span>
      </button>

      {aberto ? (
        <div id={painelId} className="space-y-3">
          <ListaDeGuias guias={guias} variante={variante} onAcao={setGuiaModal} />

          {actionError ? (
            <p className="rounded-janela border border-perigo/30 bg-perigo-suave px-4 py-3 text-corpo text-perigo-texto">
              {actionError}
            </p>
          ) : null}
        </div>
      ) : null}

      {guiaModal ? (
        <DialogoAcaoGuia
          guia={guiaModal}
          variante={variante}
          pending={ocultarAlerta.isPending}
          onCancelar={() => setGuiaModal(null)}
          onOcultar={() => ocultar(guiaModal)}
          onJaSolicitei={() => ocultar(guiaModal)}
          onNovaSolicitacao={() => novaSolicitacao(guiaModal)}
        />
      ) : null}
    </section>
  )
}

/**
 * Alerta de guias negadas — Guias e Dashboard mostram o mesmo componente,
 * cada um buscando direto de GET /guias?alerta_negacao_pendente=1 (sem
 * depender do bloco resumido do Dashboard). Ocultar é por guia, permanente
 * e vale pra qualquer usuário do tenant (grava alerta_negacao_ocultado_em
 * no banco) — não existe "ocultar só pra mim" em nenhum outro lugar do
 * gescon, então não criamos um padrão novo aqui.
 */
export function GuiaAlertaNegacoes() {
  const estado = useAlertaDeGuias(useGuiasAlertaNegacao(), useOcultarAlertaNegacaoGuia())

  return <BannerAlerta estado={estado} variante={NEGADAS} />
}

/** Mesmo alerta, para guias em "Verificar Restrição" (`needs_verification`). */
export function GuiaAlertaRestricoes() {
  const estado = useAlertaDeGuias(useGuiasAlertaRestricao(), useOcultarAlertaRestricaoGuia())

  return <BannerAlerta estado={estado} variante={RESTRICAO} />
}

/**
 * Mesmo alerta, em modal — aberto a partir do resumo na Saúde do sistema
 * (`SaudeCard`). Mesma fonte de dados e mesma lógica do banner, só muda o
 * invólucro visual. Aqui não há recolher: o modal já foi aberto de propósito.
 */
export function GuiaAlertaNegacoesModal({ onFechar }: { onFechar: () => void }) {
  const { guias, ocultarAlerta, guiaModal, setGuiaModal, actionError, ocultar, novaSolicitacao } =
    useAlertaDeGuias(useGuiasAlertaNegacao(), useOcultarAlertaNegacaoGuia())

  return (
    <div
      className="fixed inset-0 z-(--z-dialogo) flex items-center justify-center bg-texto/40 p-4"
      role="dialog"
      aria-modal="true"
      aria-label="Guias negadas"
      data-testid="guia-alerta-negacoes-modal"
    >
      <div className="w-full max-w-lg space-y-4 rounded-janela border border-linha bg-superficie-elevada p-6 shadow-e3">
        <div className="flex items-start justify-between gap-4">
          <h3 className="text-subtitulo font-semibold text-texto">{NEGADAS.titulo(guias.length)}</h3>
          <Botao
            type="button"
            variante="secundario"
            tamanho="sm"
            onClick={onFechar}
            data-testid="guia-alerta-negacoes-modal-fechar"
          >
            Fechar
          </Botao>
        </div>

        {guias.length === 0 ? (
          <p className="text-corpo text-texto-suave">{NEGADAS.vazio}</p>
        ) : (
          <ListaDeGuias guias={guias} variante={NEGADAS} onAcao={setGuiaModal} />
        )}

        {actionError ? (
          <p className="rounded-janela border border-perigo/30 bg-perigo-suave px-4 py-3 text-corpo text-perigo-texto">
            {actionError}
          </p>
        ) : null}
      </div>

      {guiaModal ? (
        <DialogoAcaoGuia
          guia={guiaModal}
          variante={NEGADAS}
          pending={ocultarAlerta.isPending}
          onCancelar={() => setGuiaModal(null)}
          onOcultar={() => ocultar(guiaModal)}
          onJaSolicitei={() => ocultar(guiaModal)}
          onNovaSolicitacao={() => novaSolicitacao(guiaModal)}
        />
      ) : null}
    </div>
  )
}
