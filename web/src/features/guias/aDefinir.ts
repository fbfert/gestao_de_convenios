export const NOME_A_DEFINIR = 'A DEFINIR'

type GuiaComReferencias = {
  especialidade?: { nome?: string | null } | null
  profissional?: { nome?: string | null } | null
}

export function guiaTemDadosADefinir(guia: GuiaComReferencias): boolean {
  return (
    guia.especialidade?.nome?.toUpperCase() === NOME_A_DEFINIR ||
    guia.profissional?.nome?.toUpperCase() === NOME_A_DEFINIR
  )
}
