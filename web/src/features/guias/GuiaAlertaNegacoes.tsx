import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Botao } from '../../components/ui/Botao'
import { getHttpErrorMessage, useGuiasAlertaNegacao, useOcultarAlertaNegacaoGuia } from './useGuias'
import type { Guia } from './types'

function formatDate(value: string) {
  return new Intl.DateTimeFormat('pt-BR').format(new Date(value))
}

/**
 * Estado e ações compartilhadas entre o banner (`GuiaAlertaNegacoes`) e o
 * modal (`GuiaAlertaNegacoesModal`): mesma fonte de dados
 * (GET /guias?alerta_negacao_pendente=1), mesma mutação de ocultar. Extraído
 * pra não duplicar a lógica entre as duas superfícies.
 */
function useAlertaNegacaoEstado() {
  const alertaQuery = useGuiasAlertaNegacao()
  const ocultarAlerta = useOcultarAlertaNegacaoGuia()
  const navigate = useNavigate()
  const [guiaModal, setGuiaModal] = useState<Guia | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)

  const guias = alertaQuery.data ?? []

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

  return { alertaQuery, guias, ocultarAlerta, guiaModal, setGuiaModal, actionError, ocultar, novaSolicitacao }
}

/** Título "N guia(s) negada(s) precisa(m) de revisão" — mesmo texto no banner, no modal e na Saúde do sistema. */
function tituloAlerta(quantidade: number) {
  return quantidade === 1 ? '1 guia negada precisa de revisão' : `${quantidade} guias negadas precisam de revisão`
}

/**
 * Lista de guias negadas pendentes. O botão abre o diálogo de ação por guia —
 * ele não oculta direto, por isso chama-se "Ações" e não "Ocultar".
 */
function ListaDeGuiasNegadas({ guias, onAcao }: { guias: Guia[]; onAcao: (guia: Guia) => void }) {
  return (
    <div className="space-y-2">
      {guias.map((guia) => (
        <div
          key={guia.id}
          className="flex flex-wrap items-center justify-between gap-3 rounded-superficie border border-linha bg-superficie p-4"
          data-testid={`guia-alerta-negacao-${guia.id}`}
        >
          <div>
            <p className="font-semibold text-texto">
              {guia.numero_guia ?? 'Sem número'} · {guia.paciente?.nome ?? `Paciente #${guia.paciente_id}`}
            </p>
            <p className="mt-1 text-meta text-texto-suave">
              {guia.especialidade?.nome ?? 'Especialidade não informada'} ·{' '}
              {guia.convenio?.nome ?? 'Convênio não informado'} · negada em {formatDate(guia.updated_at)}
            </p>
          </div>
          <Botao
            variante="secundario"
            tamanho="sm"
            onClick={() => onAcao(guia)}
            data-testid={`guia-alerta-negacao-ocultar-${guia.id}`}
          >
            Ações
          </Botao>
        </div>
      ))}
    </div>
  )
}

/** Diálogo de ação por guia: abrir nova solicitação, avisar que já solicitou, ou ocultar. */
function DialogoAcaoGuiaNegada({
  guia,
  pending,
  onCancelar,
  onOcultar,
  onJaSolicitei,
  onNovaSolicitacao,
}: {
  guia: Guia
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
      aria-label="Guia negada"
      data-testid="guia-alerta-negacao-modal"
    >
      <div className="w-full max-w-md space-y-4 rounded-janela border border-linha bg-superficie-elevada p-6 shadow-e3">
        <div>
          <h3 className="text-subtitulo font-semibold text-texto">Guia {guia.numero_guia ?? ''} negada</h3>
          <p className="mt-2 text-corpo leading-6 text-texto-suave">
            {guia.paciente?.nome ?? `Paciente #${guia.paciente_id}`} precisa de uma nova solicitação para
            retomar o tratamento? Você pode abrir uma agora, avisar que já solicitou por fora, ou só ocultar
            este alerta.
          </p>
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
            data-testid="guia-alerta-negacao-pode-ocultar"
          >
            Pode ocultar
          </Botao>
          <Botao
            type="button"
            variante="secundario"
            onClick={onJaSolicitei}
            disabled={pending}
            data-testid="guia-alerta-negacao-ja-solicitei"
          >
            Já solicitei
          </Botao>
          <Botao
            type="button"
            variante="primario"
            onClick={onNovaSolicitacao}
            disabled={pending}
            data-testid="guia-alerta-negacao-nova-solicitacao"
          >
            Nova Solicitação
          </Botao>
        </div>
      </div>
    </div>
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
  const { alertaQuery, guias, ocultarAlerta, guiaModal, setGuiaModal, actionError, ocultar, novaSolicitacao } =
    useAlertaNegacaoEstado()

  if (alertaQuery.isLoading || alertaQuery.isError || guias.length === 0) {
    return null
  }

  return (
    <section
      className="space-y-3 rounded-janela border border-perigo/30 bg-perigo-suave p-5 shadow-e1"
      data-testid="guia-alerta-negacoes"
    >
      <div>
        <p className="text-meta font-semibold uppercase tracking-[0.2em] text-perigo-texto">Atenção</p>
        <h3 className="mt-1 text-titulo font-semibold text-texto">{tituloAlerta(guias.length)}</h3>
      </div>

      <ListaDeGuiasNegadas guias={guias} onAcao={setGuiaModal} />

      {actionError ? (
        <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
          {actionError}
        </p>
      ) : null}

      {guiaModal ? (
        <DialogoAcaoGuiaNegada
          guia={guiaModal}
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
 * Mesmo alerta, em modal — aberto a partir do resumo na Saúde do sistema
 * (`SaudeCard`). Mesma fonte de dados e mesma lógica do banner, só muda o
 * invólucro visual.
 */
export function GuiaAlertaNegacoesModal({ onFechar }: { onFechar: () => void }) {
  const { guias, ocultarAlerta, guiaModal, setGuiaModal, actionError, ocultar, novaSolicitacao } =
    useAlertaNegacaoEstado()

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
          <h3 className="text-subtitulo font-semibold text-texto">{tituloAlerta(guias.length)}</h3>
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
          <p className="text-corpo text-texto-suave">Nenhuma guia negada pendente de revisão.</p>
        ) : (
          <ListaDeGuiasNegadas guias={guias} onAcao={setGuiaModal} />
        )}

        {actionError ? (
          <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
            {actionError}
          </p>
        ) : null}
      </div>

      {guiaModal ? (
        <DialogoAcaoGuiaNegada
          guia={guiaModal}
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
