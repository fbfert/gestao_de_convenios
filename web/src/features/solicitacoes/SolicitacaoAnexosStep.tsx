import { Botao } from '../../components/ui/Botao'
import { SolicitacaoAnexos } from './SolicitacaoAnexos'
import type { Solicitacao } from './types'

/**
 * Etapa final da criação (manual ou via leitura de pedido médico): a
 * solicitação já existe (id real), então dá pra usar o mesmo bloco de anexos
 * da edição — upload novo ou reaproveitar da pasta do paciente.
 */
export function SolicitacaoAnexosStep({
  solicitacao,
  onConcluir,
}: {
  solicitacao: Solicitacao
  onConcluir: () => void
}) {
  return (
    <section
      className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6 space-y-6"
      data-testid="solicitacao-anexos-step"
    >
      <div>
        <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">
          Solicitação #{solicitacao.id} criada
        </p>
        <h3 className="mt-1 text-subtitulo font-semibold text-white">Anexe os documentos</h3>
        <p className="mt-1 text-corpo text-slate-300">
          Pedido Médico e Laudo Médico valem para o pedido inteiro; Plano Individualizado e
          Relatório de Evolução são por especialidade. Envie um arquivo novo ou reaproveite um já
          cadastrado na pasta do paciente.
        </p>
      </div>

      <SolicitacaoAnexos solicitacao={solicitacao} />

      <div className="flex justify-end">
        <Botao variante="primario" onClick={onConcluir} data-testid="solicitacao-anexos-concluir">
          Concluir
        </Botao>
      </div>
    </section>
  )
}
