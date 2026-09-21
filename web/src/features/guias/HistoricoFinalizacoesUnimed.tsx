import { useQuery } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { Badge } from '../../components/ui/Badge'
import type { AutomacaoExecucao, PaginatedResponse } from '../automacoes/types'

/**
 * As tentativas de finalizar esta guia na Unimed.
 *
 * Existe porque a finalização pode falhar por muitos motivos diferentes no
 * portal, e a pergunta que alguém faz depois nunca é "falhou?", e sim "falhou
 * por quê, e quando". Sem este histórico o operador só veria o botão de novo,
 * sem saber se a tentativa anterior chegou a anexar as folhas.
 *
 * Execução em simulação aparece marcada de forma inconfundível: ela parece um
 * sucesso em tudo — status `succeeded`, nenhum erro —, e confundir as duas
 * faria alguém dar por finalizada uma guia que o portal continua esperando.
 */

function useFinalizacoesDaGuia(guiaId: number) {
  return useQuery({
    queryKey: ['automacoes', 'finalizar_guia', guiaId],
    queryFn: async () => {
      const { data } = await apiClient.get<PaginatedResponse<AutomacaoExecucao>>('/automacoes', {
        params: { guia_id: guiaId, operacao: 'finalizar_guia', per_page: 20 },
      })

      return data.data
    },
  })
}

function foiSimulacao(execucao: AutomacaoExecucao): boolean {
  return Boolean((execucao.resultado as { simulado?: boolean } | null)?.simulado)
}

function tomDoStatus(execucao: AutomacaoExecucao): 'sucesso' | 'perigo' | 'alerta' | 'neutro' {
  if (execucao.status === 'succeeded') {
    return foiSimulacao(execucao) ? 'alerta' : 'sucesso'
  }

  if (execucao.status === 'failed' || execucao.status === 'needs_attention') {
    return 'perigo'
  }

  return 'neutro'
}

function rotuloDoStatus(execucao: AutomacaoExecucao): string {
  if (execucao.status === 'succeeded') {
    return foiSimulacao(execucao) ? 'Simulação' : 'Finalizada'
  }

  if (execucao.status === 'queued') {
    return 'Na fila'
  }

  if (execucao.status === 'running') {
    return 'Em execução'
  }

  return 'Falhou'
}

function quando(execucao: AutomacaoExecucao): string {
  const iso = execucao.finished_at ?? execucao.started_at ?? execucao.queued_at ?? execucao.created_at

  return iso ? new Date(iso).toLocaleString('pt-BR') : '—'
}

export function HistoricoFinalizacoesUnimed({ guiaId }: { guiaId: number }) {
  const execucoesQuery = useFinalizacoesDaGuia(guiaId)
  const execucoes = execucoesQuery.data ?? []

  if (execucoesQuery.isLoading || execucoes.length === 0) {
    return null
  }

  return (
    <section
      className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6"
      data-testid="guia-historico-finalizacoes"
    >
      <h3 className="text-subtitulo font-semibold text-white">Finalizações na Unimed</h3>
      <p className="mt-1 text-corpo text-slate-300">
        Cada tentativa de finalizar esta guia no portal, com o que a operadora respondeu.
      </p>

      <ul className="mt-4 space-y-3">
        {execucoes.map((execucao) => (
          <li
            key={execucao.id}
            className="rounded-superficie border border-linha bg-fundo px-4 py-3"
            data-testid={`guia-finalizacao-${execucao.id}`}
          >
            <div className="flex flex-wrap items-center gap-3">
              <Badge tone={tomDoStatus(execucao)}>{rotuloDoStatus(execucao)}</Badge>
              <span className="text-corpo text-slate-300">{quando(execucao)}</span>
              <span className="text-meta text-slate-500">#{execucao.id}</span>
            </div>

            {foiSimulacao(execucao) ? (
              <p
                className="mt-2 text-corpo text-amber-200"
                data-testid={`guia-finalizacao-simulada-${execucao.id}`}
              >
                Rodou em simulação: a guia foi preenchida no portal, mas <strong>não</strong> foi
                gravada nem finalizada.
              </p>
            ) : null}

            {execucao.erro_mensagem ? (
              <p className="mt-2 text-corpo text-rose-200">
                {execucao.erro_codigo ? <strong>{execucao.erro_codigo}: </strong> : null}
                {execucao.erro_mensagem}
              </p>
            ) : null}
          </li>
        ))}
      </ul>
    </section>
  )
}
