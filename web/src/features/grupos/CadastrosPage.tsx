import { cadastrosItems } from '../../routes/navigation'
import { GrupoPage } from './GrupoPage'

export function CadastrosPage() {
  return (
    <GrupoPage
      testId="cadastros-page"
      chapeu="Cadastros"
      titulo="Bases do sistema"
      resumo="São os dados que mudam pouco e que todas as telas de operação consultam. Um pedido só pode ser aberto se o paciente, o profissional, a especialidade, o médico e o convênio já existirem aqui — por isso vale manter esta área em dia antes de operar."
      itens={cadastrosItems}
    />
  )
}
