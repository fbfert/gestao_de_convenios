export const SENHA_VENCENDO_EM_DIAS = 7

export function isSenhaVencendo(validadeSenha: string | null): boolean {
  if (!validadeSenha) {
    return false
  }

  const hoje = new Date()
  hoje.setHours(0, 0, 0, 0)

  const validade = new Date(`${validadeSenha}T00:00:00`)
  const limite = new Date(hoje)
  limite.setDate(limite.getDate() + SENHA_VENCENDO_EM_DIAS)

  return validade >= hoje && validade <= limite
}
