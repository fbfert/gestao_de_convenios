import { gestaoConveniosItems } from '../../routes/navigation'
import { GrupoPage } from './GrupoPage'

export function GestaoConveniosPage() {
  return (
    <GrupoPage
      testId="gestao-convenios-page"
      chapeu="Gestão de Convênios"
      titulo="O agora e o histórico"
      resumo="Duas perguntas diferentes, duas telas. O Painel responde o que precisa de você hoje: guias pendentes, senhas prestes a vencer, alertas abertos. Os Relatórios respondem como foi o período — se a taxa de negação piorou, qual convênio glosa mais, se a automação está mais lenta que no mês passado, e quem usa o sistema."
      itens={gestaoConveniosItems}
    />
  )
}
