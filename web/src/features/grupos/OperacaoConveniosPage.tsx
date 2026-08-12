import { operacaoItems } from '../../routes/navigation'
import { GrupoPage } from './GrupoPage'

export function OperacaoConveniosPage() {
  return (
    <GrupoPage
      testId="operacao-convenios-page"
      chapeu="Operação Convênios"
      titulo="Do pedido médico ao pagamento"
      resumo="O caminho normal é seguir os cartões na ordem: confira o paciente, registre a solicitação com o pedido médico, gere a guia e a senha no convênio, baixe as sessões executadas, agrupe o que for antecipado ao profissional, importe o analítico da operadora e feche na conciliação. Nem todo caso passa por todas as etapas, mas a sequência é essa."
      itens={operacaoItems}
      ordenado
    />
  )
}
