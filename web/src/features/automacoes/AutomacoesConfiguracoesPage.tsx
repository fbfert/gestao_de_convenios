import { useEffect, useState, type FormEvent } from 'react'
import {
  getHttpErrorMessage,
  paraFormulario,
  useConfiguracoesGlobais,
  useSalvarConfiguracoesGlobais,
  type ConfiguracoesGlobaisForm,
} from '../configuracoes/useConfiguracoesGlobais'
import { Botao } from '../../components/ui/Botao'

function inputClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-corpo text-white outline-none transition placeholder:text-texto-suave focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

const formVazio: ConfiguracoesGlobaisForm = {
  sessao_minutos: '480',
  senha_alerta_dias: '7',
  sessoes_padrao: '10',
  itens_por_pagina: '15',
  auditoria_retencao_meses: '12',
  carteirinha_retencao_dias: '30',
  unimed_recheck_horas_sucesso: '24',
  unimed_recheck_horas_falha: '2',
  unimed_verificacao_incerta_intervalo_minutos: '60',
  unimed_verificacao_incerta_horario_inicio: '02:00',
  unimed_verificacao_incerta_horario_fim: '12:50',
}

/**
 * Edita só os dois campos de reagendamento Unimed, mas usa o mesmo endpoint
 * de Configurações Globais (PUT exige todos os campos) — por isso carrega e
 * reenvia o formulário inteiro, só a UI é que mostra um subconjunto.
 */
export function AutomacoesConfiguracoesPage() {
  const query = useConfiguracoesGlobais()
  const salvar = useSalvarConfiguracoesGlobais()
  const [form, setForm] = useState<ConfiguracoesGlobaisForm>(formVazio)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (query.data) {
      setForm(paraFormulario(query.data))
    }
  }, [query.data])

  const alterar = <C extends keyof ConfiguracoesGlobaisForm>(
    campo: C,
    valor: ConfiguracoesGlobaisForm[C],
  ) => {
    setForm((atual) => ({ ...atual, [campo]: valor }))
  }

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault()
    setMessage(null)
    setError(null)

    try {
      await salvar.mutateAsync(form)
      setMessage('Configurações de reconsulta Unimed salvas.')
    } catch (submitError) {
      setError(getHttpErrorMessage(submitError, 'Não foi possível salvar as configurações.'))
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-6" data-testid="automacoes-configuracoes-page">
      <section className="space-y-2">
        <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Automações</p>
        <h2 className="text-display font-semibold text-white">Configurações</h2>
        <p className="max-w-3xl text-corpo leading-6 text-slate-300">
          Controla de quanto em quanto tempo o sistema volta a consultar o status de uma guia no
          portal da Unimed (job que roda a cada 30 minutos, o dia inteiro). Os dois prazos abaixo
          só se aplicam a guias de convênio Unimed RDA ainda em análise.
        </p>
      </section>

      {query.isPending ? <p className="text-corpo text-slate-400">Carregando...</p> : null}

      {query.isError ? (
        <p className="text-corpo text-rose-300">
          {getHttpErrorMessage(query.error, 'Não foi possível carregar as configurações.')}
        </p>
      ) : null}

      <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
        <h3 className="text-subtitulo font-semibold text-white">Reconsulta de status</h3>
        <p className="mt-1 text-corpo text-slate-300">
          Quando a consulta falha por erro técnico (timeout de automação, portal fora do ar), o
          sistema tenta de novo bem antes do prazo normal — sem isso, uma falha pontual deixava a
          guia parada até 24h, mesmo com o job rodando a cada 30 minutos.
        </p>

        <div className="mt-5 grid gap-4 md:grid-cols-2">
          <label className="space-y-2">
            <span className="text-corpo font-medium text-slate-200">
              Prazo normal — consulta OK, sem novidade (horas)
            </span>
            <input
              type="number"
              min={1}
              max={168}
              value={form.unimed_recheck_horas_sucesso}
              onChange={(event) => alterar('unimed_recheck_horas_sucesso', event.target.value)}
              className={inputClasses()}
              required
              data-testid="automacoes-config-recheck-sucesso"
            />
            <span className="block text-meta text-slate-400">
              A consulta rodou normalmente, mas a guia ainda está em análise no portal. Padrão: 24h.
            </span>
          </label>

          <label className="space-y-2">
            <span className="text-corpo font-medium text-slate-200">
              Prazo após falha técnica (horas)
            </span>
            <input
              type="number"
              min={1}
              max={168}
              value={form.unimed_recheck_horas_falha}
              onChange={(event) => alterar('unimed_recheck_horas_falha', event.target.value)}
              className={inputClasses()}
              required
              data-testid="automacoes-config-recheck-falha"
            />
            <span className="block text-meta text-slate-400">
              A automação quebrou antes de conseguir consultar (timeout, portal indisponível etc.).
              Padrão: 2h.
            </span>
          </label>
        </div>
      </section>

      <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
        <h3 className="text-subtitulo font-semibold text-white">Confirmação de guia incerta</h3>
        <p className="mt-1 text-corpo text-slate-300">
          Quando o robô finaliza uma guia no portal mas não consegue confirmar o resultado
          (resposta ambígua, sem número de guia), o sistema não reenvia sozinho — em vez disso,
          confirma buscando pelo paciente em "Exames em aberto" dentro da janela de horário abaixo.
          O job que checa isso roda a cada 30 minutos, então um intervalo menor que 30 min não tem
          efeito prático.
        </p>

        <div className="mt-5 grid gap-4 md:grid-cols-3">
          <label className="space-y-2">
            <span className="text-corpo font-medium text-slate-200">Intervalo mínimo entre tentativas (minutos)</span>
            <input
              type="number"
              min={5}
              max={1440}
              value={form.unimed_verificacao_incerta_intervalo_minutos}
              onChange={(event) => alterar('unimed_verificacao_incerta_intervalo_minutos', event.target.value)}
              className={inputClasses()}
              required
              data-testid="automacoes-config-verificacao-intervalo"
            />
            <span className="block text-meta text-slate-400">Padrão: 60 min.</span>
          </label>

          <label className="space-y-2">
            <span className="text-corpo font-medium text-slate-200">Início da janela</span>
            <input
              type="time"
              value={form.unimed_verificacao_incerta_horario_inicio}
              onChange={(event) => alterar('unimed_verificacao_incerta_horario_inicio', event.target.value)}
              className={inputClasses()}
              required
              data-testid="automacoes-config-verificacao-horario-inicio"
            />
          </label>

          <label className="space-y-2">
            <span className="text-corpo font-medium text-slate-200">Fim da janela</span>
            <input
              type="time"
              value={form.unimed_verificacao_incerta_horario_fim}
              onChange={(event) => alterar('unimed_verificacao_incerta_horario_fim', event.target.value)}
              className={inputClasses()}
              required
              data-testid="automacoes-config-verificacao-horario-fim"
            />
          </label>
        </div>
      </section>

      {message ? (
        <p className="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-corpo text-emerald-100">
          {message}
        </p>
      ) : null}

      {error ? (
        <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
          {error}
        </p>
      ) : null}

      <Botao
        type="submit"
        carregando={salvar.isPending}
        className="w-full"
        data-testid="automacoes-config-salvar"
      >
        {salvar.isPending ? 'Salvando...' : 'Salvar configurações'}
      </Botao>
    </form>
  )
}
