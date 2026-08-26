import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import {
  getHttpErrorMessage,
  useAntecipacao,
  useAtualizarAntecipacao,
  type AntecipacaoEditForm,
} from './useAntecipacoes'
import { Botao } from '../../components/ui/Botao'
import { useAuthStore } from '../../stores/authStore'
import { useLancamentoPrintTemplate } from '../lancamentos/useLancamentos'
import { buildFilledTemplateData, renderLancamentoPrintTemplate } from '../lancamentos/printTemplate'
import { HtmlIsolado } from '../../components/ui/HtmlIsolado'

function fieldClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

const formVazio: AntecipacaoEditForm = {
  qtd_autorizada: '',
  ciclo_inicio: '',
  ciclo_fim: '',
}

export function AntecipacaoEditarPage() {
  const { id } = useParams()
  const antecipacaoId = id && /^\d+$/.test(id) ? Number(id) : null
  const navigate = useNavigate()

  const antecipacaoQuery = useAntecipacao(antecipacaoId)
  const atualizar = useAtualizarAntecipacao()
  const printTemplateQuery = useLancamentoPrintTemplate()
  const clinica = useAuthStore((state) => state.tenant)?.nome ?? ''

  const [form, setForm] = useState<AntecipacaoEditForm>(formVazio)
  const [erro, setErro] = useState<string | null>(null)

  useEffect(() => {
    const antecipacao = antecipacaoQuery.data
    if (!antecipacao) {
      return
    }

    setForm({
      qtd_autorizada: String(antecipacao.qtd_autorizada),
      ciclo_inicio: antecipacao.ciclo_inicio.slice(0, 10),
      ciclo_fim: antecipacao.ciclo_fim.slice(0, 10),
    })
  }, [antecipacaoQuery.data])

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!antecipacaoId) {
      return
    }

    setErro(null)

    try {
      await atualizar.mutateAsync({ id: antecipacaoId, payload: form })
      navigate('/antecipacoes')
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível salvar as alterações.'))
    }
  }

  const antecipacao = antecipacaoQuery.data

  const printHtml = useMemo(() => {
    if (!antecipacao || !printTemplateQuery.data?.html) {
      return ''
    }

    return renderLancamentoPrintTemplate(
      printTemplateQuery.data.html,
      buildFilledTemplateData(antecipacao, clinica),
    )
  }, [antecipacao, printTemplateQuery.data?.html, clinica])

  return (
    <>
    <div className="space-y-6 print:hidden" data-testid="antecipacao-editar-page">
      <Link to="/antecipacoes" className="inline-flex min-h-6 items-center text-corpo font-semibold text-cyan-200 hover:text-cyan-100">
        ← Voltar para antecipações
      </Link>

      <div className="flex flex-col gap-4 sm:items-start lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Antecipações</p>
          <h2 className="mt-2 text-display font-semibold text-white">
            Editar antecipação {antecipacaoId ? `#${antecipacaoId}` : ''}
          </h2>
          <p className="mt-2 text-corpo text-slate-400">
            Toda alteração fica registrada nos Logs de Auditoria, com o valor anterior e o novo.
          </p>
        </div>

        {antecipacao ? (
          <Botao
            variante="secundario"
            onClick={() => window.print()}
            data-testid="antecipacao-imprimir-preenchido"
            disabled={printTemplateQuery.isLoading || printHtml.trim() === ''}
          >
            {printTemplateQuery.isLoading ? 'Carregando modelo...' : 'Imprimir preenchido'}
          </Botao>
        ) : null}
      </div>

      {antecipacaoQuery.isLoading ? (
        <div className="rounded-superficie border border-linha bg-fundo p-6 shadow-e1 text-slate-300">Carregando...</div>
      ) : antecipacaoQuery.isError || !antecipacao ? (
        <div className="rounded-3xl border border-rose-400/20 bg-rose-500/10 p-6 text-rose-100">
          Antecipação não encontrada.
        </div>
      ) : (
        <form onSubmit={handleSubmit} className="space-y-4 rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Paciente</span>
              <p className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white">
                {antecipacao.paciente?.nome ?? antecipacao.paciente_id}
              </p>
            </div>
            <div className="space-y-2">
              <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Cota já utilizada</span>
              <p className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white">
                {antecipacao.qtd_utilizada} sessões
              </p>
            </div>
          </div>
          <p className="text-meta text-slate-400">
            Paciente, convênio, quantidade utilizada e status (aberta/fechada) não são editáveis aqui: são
            controlados automaticamente pelas sessões lançadas em Sessões. Aumentar a quantidade autorizada reabre a
            antecipação sozinha, se ela já tiver fechado.
          </p>

          <label className="block space-y-2">
            <span className="text-corpo font-medium text-slate-200">Quantidade autorizada por ciclo</span>
            <input
              type="number"
              min="1"
              value={form.qtd_autorizada}
              onChange={(event) => setForm((current) => ({ ...current, qtd_autorizada: event.target.value }))}
              className={fieldClasses()}
              data-testid="antecipacao-editar-qtd-autorizada"
            />
          </label>

          <div className="grid gap-4 md:grid-cols-2">
            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Ciclo início</span>
              <input
                type="date"
                value={form.ciclo_inicio}
                onChange={(event) => setForm((current) => ({ ...current, ciclo_inicio: event.target.value }))}
                className={fieldClasses()}
                data-testid="antecipacao-editar-ciclo-inicio"
              />
            </label>
            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Ciclo fim</span>
              <input
                type="date"
                value={form.ciclo_fim}
                onChange={(event) => setForm((current) => ({ ...current, ciclo_fim: event.target.value }))}
                className={fieldClasses()}
                data-testid="antecipacao-editar-ciclo-fim"
              />
            </label>
          </div>

          {erro ? (
            <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">{erro}</p>
          ) : null}

          <div className="flex gap-2">
            <Botao type="submit" carregando={atualizar.isPending} data-testid="antecipacao-editar-salvar">
              {atualizar.isPending ? 'Salvando...' : 'Salvar alterações'}
            </Botao>
            <Botao type="button" variante="secundario" onClick={() => navigate('/antecipacoes')}>
              Cancelar
            </Botao>
          </div>
        </form>
      )}
    </div>

    <HtmlIsolado
      className="hidden print:block bg-white p-8 text-slate-950"
      html={printHtml}
    />
    </>
  )
}
