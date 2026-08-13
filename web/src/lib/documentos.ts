/**
 * Máscaras de CPF e telefone.
 *
 * A regra é a mesma dos dois: o estado guarda só dígitos e a máscara existe
 * para leitura. Guardar formatado quebraria busca e comparação — o banco
 * também recebe apenas dígitos.
 */

export function somenteDigitos(valor: string): string {
  return valor.replace(/\D+/g, '')
}

/** 000.000.000-00, aplicada conforme o operador digita. */
export function formatarCpf(valor: string): string {
  const digitos = somenteDigitos(valor).slice(0, 11)

  return digitos
    .replace(/^(\d{3})(\d)/, '$1.$2')
    .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4')
}

/** Mesmo dígito verificador conferido pela API — erra aqui, erra lá. */
export function cpfValido(valor: string): boolean {
  const digitos = somenteDigitos(valor)

  if (digitos === '') {
    return true
  }

  if (digitos.length !== 11 || /^(\d)\1{10}$/.test(digitos)) {
    return false
  }

  return [9, 10].every((posicao) => {
    let soma = 0

    for (let i = 0; i < posicao; i++) {
      soma += Number(digitos[i]) * (posicao + 1 - i)
    }

    const resto = soma % 11
    const verificador = resto < 2 ? 0 : 11 - resto

    return Number(digitos[posicao]) === verificador
  })
}

/** (00) 00000-0000 e (00) 0000-0000, conforme o tamanho. */
export function formatarTelefone(valor: string): string {
  const digitos = somenteDigitos(valor).slice(0, 11)

  if (digitos.length <= 2) {
    return digitos
  }

  const ddd = digitos.slice(0, 2)
  const resto = digitos.slice(2)

  if (resto.length <= 4) {
    return `(${ddd}) ${resto}`
  }

  const corte = resto.length > 8 ? 5 : 4

  return `(${ddd}) ${resto.slice(0, corte)}-${resto.slice(corte)}`
}
