import { useEffect, useState, type FormEvent, type ReactNode } from 'react'
import {
  getHttpErrorMessage,
  paraFormulario,
  useConfiguracoesGlobais,
  useSalvarConfiguracoesGlobais,
  type ConfiguracoesGlobaisForm,
} from '../configuracoes/useConfiguracoesGlobais'
import { Botao } from '../../components/ui/Botao'
import { Select } from '../../components/ui/Select'

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
  automacao_sincronizacao_clinica_intervalo_minutos: '5',
  automacao_expurgo_auditoria_ativo: true,
  automacao_expurgo_carteirinhas_ativo: true,
  automacao_verificacao_guias_diaria_ativo: true,
}

type SecaoAutomacaoProps = {
  titulo: string
  descricao: string
  ativo: boolean
  onAlterarAtivo: (valor: boolean) => void
  testIdAtivo: string
  children?: ReactNode
}

/**
 * Cada automação vira uma seção com o mesmo formato: título, descrição, um
 * switch de liga/desliga sempre visível no cabeçalho, e — quando a automação
 * tem algum prazo configurável — os campos de intervalo logo abaixo,
 * desabilitados enquanto ela estiver desligada (não faz sentido configurar
 * o ritmo de algo que não está rodando).
 */
function SecaoAutomacao({ titulo, descricao, ativo, onAlterarAtivo, testIdAtivo, children }: SecaoAutomacaoProps) {
  return (
    <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h3 className="text-subtitulo font-semibold text-white">{titulo}</h3>
          <p className="mt-1 text-corpo text-slate-300">{descricao}</p>
        </div>
        <label className="inline-flex min-h-6 shrink-0 items-center gap-2 text-corpo font-medium text-slate-200">
          <input
            type="checkbox"
            checked={ativo}
            onChange={(event) => onAlterarAtivo(event.target.checked)}
            className="size-4 rounded border-white/20 bg-white/10"
            data-testid={testIdAtivo}
          />
          Ativa
        </label>
      </div>

      {children ? (
        <div aria-disabled={!ativo} className={ativo ? undefined : 'pointer-events-none opacity-50'}>
          {children}
        </div>
      ) : null}
    </section>
  )
}

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
      setMessage('Configurações de automações salvas.')
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
          Liga, desliga e ajusta o ritmo de cada automação de fundo do sistema. Desligar uma
          automação não afeta as ações manuais equivalentes (ex.: "Buscar senha/validade" numa
          guia, ou "Sincronizar Agora") — elas continuam disponíveis normalmente.
        </p>
      </section>

      {query.isPending ? <p className="text-corpo text-slate-400">Carregando...</p> : null}

      {query.isError ? (
        <p className="text-corpo text-rose-300">
          {getHttpErrorMessage(query.error, 'Não foi possível carregar as configurações.')}
        </p>
      ) : null}

      <SecaoAutomacao
        titulo="Reconsulta de status Unimed"
        descricao="A cada 30 minutos, o sistema volta a consultar no portal da Unimed o status das guias RDA ainda em análise."
        ativo={form.automacao_reconsulta_status_ativo}
        onAlterarAtivo={(valor) => alterar('automacao_reconsulta_status_ativo', valor)}
        testIdAtivo="automacoes-config-reconsulta-ativo"
      >
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
      </SecaoAutomacao>

      <SecaoAutomacao
        titulo="Busca de senha e validade Unimed"
        descricao="Busca a senha e a validade de guias Unimed já aprovadas que ainda não têm um dos dois. O job de fila continua checando a cada 30 minutos, mas cada guia só é reprocessada depois do intervalo escolhido abaixo."
        ativo={form.automacao_captura_senha_validade_ativo}
        onAlterarAtivo={(valor) => alterar('automacao_captura_senha_validade_ativo', valor)}
        testIdAtivo="automacoes-config-captura-senha-validade-ativo"
      >
        <div className="mt-5 grid gap-4 md:grid-cols-2">
          <label className="space-y-2">
            <span className="text-corpo font-medium text-slate-200">Intervalo entre tentativas por guia</span>
            <Select
              value={form.unimed_captura_senha_validade_intervalo_horas}
              onChange={(event) => alterar('unimed_captura_senha_validade_intervalo_horas', event.target.value)}
              className={inputClasses()}
              data-testid="automacoes-config-captura-senha-validade-intervalo"
            >
              <option value="1">1 hora</option>
              <option value="6">6 horas</option>
              <option value="12">12 horas</option>
              <option value="24">24 horas</option>
            </Select>
            <span className="block text-meta text-slate-400">Padrão: 6 horas.</span>
          </label>
        </div>
      </SecaoAutomacao>

      <SecaoAutomacao
        titulo="Confirmação de guia incerta"
        descricao={'Quando o robô finaliza uma guia no portal mas não consegue confirmar o resultado (resposta ambígua, sem número de guia), confirma buscando pelo paciente em "Exames em aberto" dentro da janela de horário abaixo. O job que checa isso roda a cada 30 minutos, então um intervalo menor que 30 min não tem efeito prático.'}
        ativo={form.automacao_verificacao_incerta_ativo}
        onAlterarAtivo={(valor) => alterar('automacao_verificacao_incerta_ativo', valor)}
        testIdAtivo="automacoes-config-verificacao-ativo"
      >
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
      </SecaoAutomacao>

      <SecaoAutomacao
        titulo="Sincronização com a clínica"
        descricao={'Sincroniza profissionais e pacientes com clinica.gestaonossa.com.br. O botão "Sincronizar Agora" continua funcionando mesmo com a automação desligada.'}
        ativo={form.automacao_sincronizacao_clinica_ativo}
        onAlterarAtivo={(valor) => alterar('automacao_sincronizacao_clinica_ativo', valor)}
        testIdAtivo="automacoes-config-sync-clinica-ativo"
      >
        <div className="mt-5 grid gap-4 md:grid-cols-2">
          <label className="space-y-2">
            <span className="text-corpo font-medium text-slate-200">Intervalo entre sincronizações (minutos)</span>
            <input
              type="number"
              min={5}
              max={1440}
              value={form.automacao_sincronizacao_clinica_intervalo_minutos}
              onChange={(event) => alterar('automacao_sincronizacao_clinica_intervalo_minutos', event.target.value)}
              className={inputClasses()}
              required
              data-testid="automacoes-config-sync-clinica-intervalo"
            />
            <span className="block text-meta text-slate-400">
              O sistema checa a cada 5 minutos se já passou esse tempo. Padrão: 5 min.
            </span>
          </label>
        </div>
      </SecaoAutomacao>

      <SecaoAutomacao
        titulo="Expurgo de auditoria"
        descricao="Apaga diariamente (03:30) os registros de auditoria vencidos, depois de exportá-los em CSV. O prazo de retenção é o mesmo da tela de Configurações gerais."
        ativo={form.automacao_expurgo_auditoria_ativo}
        onAlterarAtivo={(valor) => alterar('automacao_expurgo_auditoria_ativo', valor)}
        testIdAtivo="automacoes-config-expurgo-auditoria-ativo"
      />

      <SecaoAutomacao
        titulo="Expurgo de carteirinhas"
        descricao="Apaga diariamente (03:45) as imagens de carteirinha vencidas. O prazo de retenção é o mesmo da tela de Configurações gerais."
        ativo={form.automacao_expurgo_carteirinhas_ativo}
        onAlterarAtivo={(valor) => alterar('automacao_expurgo_carteirinhas_ativo', valor)}
        testIdAtivo="automacoes-config-expurgo-carteirinhas-ativo"
      />

      <SecaoAutomacao
        titulo="Verificação diária de guias (outros convênios)"
        descricao="Checa diariamente (02:00) o status de guias em análise de convênios que não são Unimed RDA, usando o conector de cada convênio."
        ativo={form.automacao_verificacao_guias_diaria_ativo}
        onAlterarAtivo={(valor) => alterar('automacao_verificacao_guias_diaria_ativo', valor)}
        testIdAtivo="automacoes-config-verificacao-guias-diaria-ativo"
      />

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
