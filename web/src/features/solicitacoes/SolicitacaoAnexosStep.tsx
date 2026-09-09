import { Botao } from '../../components/ui/Botao'
import { SolicitacaoAnexos } from './SolicitacaoAnexos'
import { useSolicitacao } from './useSolicitacoes'
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
  // A `solicitacao` que chega por prop é o corpo do POST guardado num
  // `useState` da página — um retrato do instante da criação, quando ainda não
  // havia anexo nenhum. Anexar invalida `['solicitacoes']`, mas invalidação não
  // alcança estado local: o upload terminava, o slot voltava a dizer "Nenhum
  // arquivo anexado" e a tela parecia ter perdido o arquivo.
  //
  // Lendo pela query, o refetch da invalidação chega até aqui. O retrato segue
  // como valor inicial, para a etapa aparecer preenchida já no primeiro quadro.
  const solicitacaoQuery = useSolicitacao(solicitacao.id)
  const atual = solicitacaoQuery.data ?? solicitacao

  return (
    <section
      className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6 space-y-6"
      data-testid="solicitacao-anexos-step"
    >
      <div>
        <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">
          Solicitação #{atual.id} criada
        </p>
        <h3 className="mt-1 text-subtitulo font-semibold text-white">Anexe os documentos</h3>
        <p className="mt-1 text-corpo text-slate-300">
          Pedido Médico e Laudo Médico valem para o pedido inteiro; Plano Individualizado e
          Relatório de Evolução são por especialidade. Envie um arquivo novo ou reaproveite um já
          cadastrado na pasta do paciente.
        </p>
      </div>

      <SolicitacaoAnexos solicitacao={atual} />

      <div className="flex justify-end">
        <Botao variante="primario" onClick={onConcluir} data-testid="solicitacao-anexos-concluir">
          Concluir
        </Botao>
      </div>
    </section>
  )
}
