import { useEffect, useState, type FormEvent } from 'react'
import {
  descreverMinutos,
  getHttpErrorMessage,
  paraFormulario,
  useConfiguracoesGlobais,
  useSalvarConfiguracoesGlobais,
  type ConfiguracoesGlobaisForm,
} from './useConfiguracoesGlobais'
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
  automacao_reconsulta_status_ativo: true,
  automacao_captura_senha_validade_ativo: true,
  unimed_captura_senha_validade_intervalo_horas: '6',
  automacao_verificacao_incerta_ativo: true,
  automacao_sincronizacao_clinica_ativo: true,
  automacao_sincronizacao_clinica_diurno_horario_inicio: '08:00',
  automacao_sincronizacao_clinica_diurno_horario_fim: '18:00',
  automacao_sincronizacao_clinica_diurno_intervalo_minutos: '10',
  automacao_sincronizacao_clinica_noturno_horario_inicio: '18:00',
  automacao_sincronizacao_clinica_noturno_horario_fim: '22:00',
  automacao_sincronizacao_clinica_noturno_intervalo_minutos: '30',
  automacao_sincronizacao_clinica_madrugada_horario_inicio: '22:00',
  automacao_sincronizacao_clinica_madrugada_horario_fim: '07:59',
  automacao_sincronizacao_clinica_madrugada_intervalo_minutos: '60',
  automacao_expurgo_auditoria_ativo: true,
  automacao_expurgo_carteirinhas_ativo: true,
  automacao_verificacao_guias_diaria_ativo: true,
}

/** Atalhos de tempo de sessão, para não ter que calcular minutos na mão. */
const atalhosSessao = [
  { rotulo: '30 min', valor: '30' },
  { rotulo: '2 h', valor: '120' },
  { rotulo: '8 h', valor: '480' },
  { rotulo: '24 h', valor: '1440' },
  { rotulo: 'Sem expirar', valor: '0' },
]

export function ConfiguracoesGlobaisPage() {
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
      setMessage('Configurações globais salvas.')
    } catch (submitError) {
      setError(getHttpErrorMessage(submitError, 'Não foi possível salvar as configurações.'))
    }
  }

  const minutos = Number(form.sessao_minutos)

  return (
    <form onSubmit={handleSubmit} className="space-y-6" data-testid="configuracoes-globais-page">
      <section className="space-y-2">
        <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Configurações</p>
        <h2 className="text-display font-semibold text-white">Globais</h2>
        <p className="max-w-3xl text-corpo leading-6 text-slate-300">
          Parâmetros de comportamento do sistema, válidos para toda a clínica. Regras de convênio
          — validade de senha, quantidade autorizada, valores — continuam em Convênios, por
          operadora.
        </p>
      </section>

      {query.isPending ? <p className="text-corpo text-slate-400">Carregando...</p> : null}

      {query.isError ? (
        <p className="text-corpo text-rose-300">
          {getHttpErrorMessage(query.error, 'Não foi possível carregar as configurações.')}
        </p>
      ) : null}

      <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
        <h3 className="text-subtitulo font-semibold text-white">Sessão</h3>
        <p className="mt-1 text-corpo text-slate-300">
          Quanto tempo um login vale. O prazo conta a partir da entrada, não do último clique:
          passado o tempo, é preciso entrar de novo, mesmo com o sistema em uso.
        </p>

        <div className="mt-5 max-w-md space-y-2">
          <label className="space-y-2">
            <span className="text-corpo font-medium text-slate-200">Tempo de sessão (minutos)</span>
            <input
              type="number"
              min={0}
              max={43200}
              value={form.sessao_minutos}
              onChange={(event) => alterar('sessao_minutos', event.target.value)}
              className={inputClasses()}
              required
              data-testid="globais-sessao-minutos"
            />
          </label>

          <div className="flex flex-wrap gap-2">
            {atalhosSessao.map((atalho) => (
              <button
                key={atalho.valor}
                type="button"
                onClick={() => alterar('sessao_minutos', atalho.valor)}
                className={`rounded-full border px-3 py-1.5 text-meta font-semibold transition ${
                  form.sessao_minutos === atalho.valor
                    ? 'border-cyan-300/70 bg-cyan-400/25 text-cyan-50'
                    : 'border-cyan-400/20 bg-cyan-400/10 text-cyan-100 hover:bg-cyan-400/20'
                }`}
              >
                {atalho.rotulo}
              </button>
            ))}
          </div>

          <p className="text-meta text-slate-400">
            Equivale a <strong className="text-slate-200">{descreverMinutos(minutos)}</strong>.
            {minutos === 0 ? ' A sessão só termina quando o usuário sai.' : ''}
          </p>
        </div>
      </section>

      <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
        <h3 className="text-subtitulo font-semibold text-white">Operação</h3>
        <p className="mt-1 text-corpo text-slate-300">
          Padrões que as telas usam quando você não informa outra coisa.
        </p>

        <div className="mt-5 grid gap-4 md:grid-cols-3">
          <label className="space-y-2">
            <span className="text-corpo font-medium text-slate-200">Aviso de senha vencendo</span>
            <input
              type="number"
              min={1}
              max={180}
              value={form.senha_alerta_dias}
              onChange={(event) => alterar('senha_alerta_dias', event.target.value)}
              className={inputClasses()}
              required
              data-testid="globais-senha-alerta-dias"
            />
            <span className="block text-meta text-slate-400">
              Dias de antecedência com que a guia é marcada como prestes a vencer.
            </span>
          </label>

          <label className="space-y-2">
            <span className="text-corpo font-medium text-slate-200">Sessões por especialidade</span>
            <input
              type="number"
              min={1}
              max={999}
              value={form.sessoes_padrao}
              onChange={(event) => alterar('sessoes_padrao', event.target.value)}
              className={inputClasses()}
              required
              data-testid="globais-sessoes-padrao"
            />
            <span className="block text-meta text-slate-400">
              Quantidade sugerida ao acrescentar uma especialidade na solicitação.
            </span>
          </label>

          <label className="space-y-2">
            <span className="text-corpo font-medium text-slate-200">Itens por página</span>
            <input
              type="number"
              min={5}
              max={200}
              value={form.itens_por_pagina}
              onChange={(event) => alterar('itens_por_pagina', event.target.value)}
              className={inputClasses()}
              required
              data-testid="globais-itens-por-pagina"
            />
            <span className="block text-meta text-slate-400">
              Tamanho padrão das listagens paginadas.
            </span>
          </label>

          <label className="space-y-2">
            <span className="text-corpo font-medium text-slate-200">Retenção da auditoria (meses)</span>
            <input
              type="number"
              min={3}
              max={120}
              value={form.auditoria_retencao_meses}
              onChange={(event) => alterar('auditoria_retencao_meses', event.target.value)}
              className={inputClasses()}
              required
              data-testid="globais-auditoria-retencao"
            />
            <span className="block text-meta text-slate-400">
              Todo dia, o que passa deste prazo é exportado em CSV no servidor e depois removido da
              trilha. Mínimo de 3 meses.
            </span>
          </label>

          <label className="space-y-2">
            <span className="text-corpo font-medium text-slate-200">Imagem da carteirinha (dias)</span>
            <input
              type="number"
              min={1}
              max={365}
              value={form.carteirinha_retencao_dias}
              onChange={(event) => alterar('carteirinha_retencao_dias', event.target.value)}
              className={inputClasses()}
              required
              data-testid="globais-carteirinha-retencao"
            />
            <span className="block text-meta text-slate-400">
              Quanto tempo a foto da carteirinha lida pela IA fica guardada. Passado o prazo, a
              imagem é apagada — o cadastro do paciente não muda.
            </span>
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
        data-testid="globais-salvar"
      >
        {salvar.isPending ? 'Salvando...' : 'Salvar configurações globais'}
      </Botao>
    </form>
  )
}
