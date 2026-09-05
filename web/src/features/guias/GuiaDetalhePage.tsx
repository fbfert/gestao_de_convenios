import { useMemo, useState } from 'react'
import { Link, useLocation, useParams } from 'react-router-dom'
import { GuiaDetalheResumo } from './GuiaDetalheResumo'
import { GuiaStatusActions } from './GuiaStatusActions'
import { getHttpErrorMessage, useBuscarSenhaValidadeGuiaUnimed, useConsultarGuiaUnimed, useGuia } from './useGuias'
import { guiaTemDadosADefinir } from './aDefinir'
import { useAuthStore } from '../../stores/authStore'
import { useLancamentoPrintTemplate } from '../lancamentos/useLancamentos'
import { buildGuiaTemplateData, renderLancamentoPrintTemplate } from '../lancamentos/printTemplate'
import { Botao } from '../../components/ui/Botao'
import { useConfirm } from '../../components/ui/ConfirmDialog'
import { AutomacaoProgressoModal } from '../automacoes/AutomacaoProgressoModal'
import { AutomacaoUnimedDesativadaModal } from '../configuracoes/AutomacaoUnimedDesativadaModal'
import { useAutomacaoUnimedGate } from '../configuracoes/useAutomacaoUnimedGate'
import { HtmlIsolado } from '../../components/ui/HtmlIsolado'

export function GuiaDetalhePage() {
  const { id } = useParams()
  const location = useLocation()
  const voltarPara = (location.state as { from?: string } | null)?.from ?? '/guias'
  const guiaId = id && /^\d+$/.test(id) ? Number(id) : null
  const guiaQuery = useGuia(guiaId)
  const consultarGuiaUnimed = useConsultarGuiaUnimed()
  const buscarSenhaValidadeUnimed = useBuscarSenhaValidadeGuiaUnimed()
  const confirmar = useConfirm()
  const [progresso, setProgresso] = useState<{ id: number; tipo: 'status' | 'senha' } | null>(null)
  const { tratarErroUnimed, modalProps: automacaoUnimedModalProps } = useAutomacaoUnimedGate()
  const printTemplateQuery = useLancamentoPrintTemplate()
  const clinica = useAuthStore((state) => state.tenant)?.nome ?? ''

  const printHtml = useMemo(() => {
    if (!guiaQuery.data || !printTemplateQuery.data?.html) {
      return ''
    }

    return renderLancamentoPrintTemplate(
      printTemplateQuery.data.html,
      buildGuiaTemplateData(guiaQuery.data, clinica),
    )
  }, [guiaQuery.data, printTemplateQuery.data?.html, clinica])

  if (guiaId === null) {
    return (
      <div className="rounded-3xl border border-rose-400/20 bg-rose-500/10 p-6 text-rose-100">
        Identificador de guia inválido.
      </div>
    )
  }

  if (guiaQuery.isLoading) {
    return <div className="rounded-superficie border border-linha bg-fundo p-6 shadow-e1 text-slate-300">Carregando guia...</div>
  }

  if (guiaQuery.isError || !guiaQuery.data) {
    return (
      <div className="space-y-4 rounded-3xl border border-rose-400/20 bg-rose-500/10 p-6 text-rose-100">
        <p>Não foi possível encontrar esta guia.</p>
        <p className="text-corpo text-rose-100/80">
          {getHttpErrorMessage(guiaQuery.error, 'Confira o número informado e tente novamente.')}
        </p>
        <Link to={voltarPara} className="inline-flex rounded-2xl border border-rose-200/30 px-4 py-2 text-corpo font-semibold">
          Voltar para guias
        </Link>
      </div>
    )
  }

  const guia = guiaQuery.data
  const isUnimed = guia.convenio?.connector_driver === 'unimed_rda'
  const temDadosADefinir = guiaTemDadosADefinir(guia)
  // Igualdade estrita a 'under_review', não "não está numa lista de status
  // terminais" — achado 03/09/2026: com a lista de exclusão, um status
  // historico_under_review passava disfarçado de elegível (nenhum dos 6
  // valores prefixados bate com o array), habilitando o botão pra uma guia
  // histórica. GuiasPage.tsx já usava igualdade nesse mesmo botão.
  const canConsultarUnimed = isUnimed && Boolean(guia.numero_guia) && guia.status === 'under_review' && !temDadosADefinir
  const canBuscarSenhaValidade = isUnimed && guia.status === 'approved' && Boolean(guia.numero_guia) && (!guia.senha || !guia.validade_senha) && !temDadosADefinir

  const executarConsultarUnimed = async () => {
    try {
      const execucao = await consultarGuiaUnimed.mutateAsync(guia.id)
      setProgresso({ id: execucao.id, tipo: 'status' })
    } catch (error) {
      tratarErroUnimed(error, 'Não foi possível consultar a Unimed.', executarConsultarUnimed)
    }
  }

  const handleConsultarUnimed = async () => {
    const ok = await confirmar({
      titulo: 'Verificar status na Unimed',
      descricao: 'Consulta o status atual desta guia no portal da Unimed agora, fora do agendamento automático. Confirma?',
      confirmarTexto: 'Verificar status',
      variante: 'primario',
    })

    if (!ok) {
      return
    }

    await executarConsultarUnimed()
  }

  const executarBuscarSenhaValidade = async () => {
    try {
      const execucao = await buscarSenhaValidadeUnimed.mutateAsync(guia.id)
      setProgresso({ id: execucao.id, tipo: 'senha' })
    } catch (error) {
      tratarErroUnimed(error, 'Não foi possível buscar senha e validade na Unimed.', executarBuscarSenhaValidade)
    }
  }

  const handleBuscarSenhaValidade = async () => {
    const ok = await confirmar({
      titulo: 'Buscar senha e validade na Unimed',
      descricao: 'Consulta o portal da Unimed para capturar a senha e a validade de autorização desta guia. Confirma?',
      confirmarTexto: 'Buscar senha/validade',
      variante: 'primario',
    })

    if (!ok) {
      return
    }

    await executarBuscarSenhaValidade()
  }

  return (
    <>
    <div className="space-y-8 print:hidden" data-testid="guia-detalhe-page">
      <section className="flex flex-col gap-4 sm:items-start lg:flex-row lg:items-start lg:justify-between">
        <div>
          <Link to={voltarPara} className="text-corpo font-semibold text-cyan-200 transition hover:text-cyan-100">
            ← Voltar para guias
          </Link>
        </div>
        <Botao
          variante="secundario"
          onClick={() => window.print()}
          data-testid="guia-imprimir"
          disabled={printTemplateQuery.isLoading || printHtml.trim() === ''}
        >
          {printTemplateQuery.isLoading ? 'Carregando modelo...' : 'Imprimir guia'}
        </Botao>
      </section>

      <GuiaDetalheResumo guia={guia} />

      <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
        <h3 className="text-subtitulo font-semibold text-white">Ações da guia</h3>
        <p className="mt-1 text-corpo text-slate-300">Finalize com senha e validade ou negue enquanto estiver em análise.</p>
        <div className="mt-4 flex flex-wrap gap-2">
          <GuiaStatusActions guia={guia} />
          <button
            type="button"
            onClick={() => void handleConsultarUnimed()}
            disabled={!canConsultarUnimed || consultarGuiaUnimed.isPending}
            className="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1.5 text-meta font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:cursor-not-allowed disabled:opacity-50"
            data-testid="guia-consultar-unimed"
          >
            {consultarGuiaUnimed.isPending ? 'Consultando...' : 'Consultar Unimed'}
          </button>
          <button
            type="button"
            onClick={() => void handleBuscarSenhaValidade()}
            disabled={!canBuscarSenhaValidade || buscarSenhaValidadeUnimed.isPending}
            className="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1.5 text-meta font-semibold text-emerald-100 transition hover:bg-emerald-400/20 disabled:cursor-not-allowed disabled:opacity-50"
            data-testid="guia-buscar-senha-validade-unimed"
          >
            {buscarSenhaValidadeUnimed.isPending ? 'Buscando...' : 'Buscar senha/validade'}
          </button>
        </div>
      </section>
    </div>

    <HtmlIsolado
      className="hidden print:block bg-white p-8 text-slate-950"
      html={printHtml}
    />

    <AutomacaoProgressoModal
      execucaoId={progresso?.id ?? null}
      onClose={() => setProgresso(null)}
      titulo={progresso?.tipo === 'senha' ? 'Buscando senha e validade na Unimed' : 'Verificando status na Unimed'}
      descricao={
        progresso?.tipo === 'senha'
          ? 'Acompanhe a captura de senha e validade desta guia no portal da Unimed.'
          : 'Acompanhe a consulta de status desta guia no portal da Unimed.'
      }
      mensagemExecutando={
        progresso?.tipo === 'senha'
          ? 'O robô está buscando a senha e a validade desta guia no portal da Unimed...'
          : 'O robô está consultando o status desta guia no portal da Unimed...'
      }
      queryKeysInvalidar={[['guias']]}
    />

    <AutomacaoUnimedDesativadaModal {...automacaoUnimedModalProps} />
    </>
  )
}
