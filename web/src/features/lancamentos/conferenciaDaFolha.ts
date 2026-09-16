/**
 * Confere se a guia escolhida é mesmo do paciente da folha lida.
 *
 * Existe por causa de um risco criado pela escolha automática: um dígito lido
 * errado no número da guia não costuma cair no vazio — cai em OUTRA guia real,
 * de outro paciente. Aí a tela seleciona com confiança e as sessões entram na
 * cota de quem não foi atendido. Número ilegível é inofensivo, porque não
 * resolve para nada; o perigoso é o número plausível e errado.
 *
 * A folha traz dois identificadores independentes — o número da guia e o
 * paciente —, e é a discordância entre eles que denuncia o erro.
 *
 * ## Por que a comparação é tolerante
 *
 * Um aviso que dispara à toa é pior do que não ter aviso: ensina a clicar sem
 * ler, e deixa de proteger no dia em que a divergência é real. Então cada
 * regra aqui só acusa o que não tem explicação inocente, e na dúvida devolve
 * "confere".
 *
 * Fica em arquivo próprio, fora do componente, para poder ser lido e testado
 * sem montar a tela inteira.
 */

/** Mínimo de dígitos para um cartão valer como prova. Abaixo disso, coincidência é provável. */
const DIGITOS_MINIMOS_CARTAO = 6

export type ConferenciaDaFolha = {
  confere: boolean
  /** Qual dado decidiu. `null` quando não havia o que comparar. */
  campo: 'carteirinha' | 'nome' | null
  /** O que a folha dizia — para o aviso nomear os dois lados. */
  naFolha: string | null
  /** O que a guia diz. */
  naGuia: string | null
}

const CONFERE: ConferenciaDaFolha = { confere: true, campo: null, naFolha: null, naGuia: null }

function digitos(valor: string | null | undefined): string {
  return (valor ?? '').replace(/\D/g, '')
}

/**
 * Quantos pedaços de nome precisam coincidir para valer como confirmação.
 *
 * Dois, e não um: só o primeiro nome bater é fraco — "Maria Silva" e "Maria
 * Santos" são pessoas diferentes, e uma clínica tem muitas Marias. Dois
 * pedaços em comum já não acontece por acaso, e ainda aceita nome do meio
 * abreviado e sobrenome acrescentado, que é a variação de escrita real.
 */
const PEDACOS_DE_NOME_PARA_CONFIRMAR = 2

/** Minúsculas, sem acento e sem pontuação — "ANA P. RIBEIRO" e "Ana Paula Ribeiro" viram comparáveis. */
function normalizarNome(valor: string | null | undefined): string[] {
  return (valor ?? '')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z\s]/g, ' ')
    .split(/\s+/)
    .filter((parte) => parte.length > 1)
}

/** `null` = não havia como comparar; não é nem confirmação nem contradição. */
type Sinal = boolean | null

function conferirCartao(daFolha: string | null, daGuia: string | null | undefined): Sinal {
  const folha = digitos(daFolha)
  const guia = digitos(daGuia)

  // Com poucos dígitos, bater por acaso é comum e divergir não prova nada.
  if (folha.length < DIGITOS_MINIMOS_CARTAO || guia.length < DIGITOS_MINIMOS_CARTAO) {
    return null
  }

  // Um contendo o outro conta como conferido: a IA lê a folha em blocos e
  // perder um pedaço na ponta é comum — é leitura parcial, não contradição.
  return folha.includes(guia) || guia.includes(folha)
}

function conferirNome(daFolha: string | null, daGuia: string | null | undefined): Sinal {
  const folha = normalizarNome(daFolha)
  const guia = normalizarNome(daGuia)

  if (folha.length === 0 || guia.length === 0) {
    return null
  }

  const emComum = guia.filter((parte) => folha.includes(parte)).length
  const exigido = Math.min(PEDACOS_DE_NOME_PARA_CONFIRMAR, folha.length, guia.length)

  return emComum >= exigido
}

/**
 * O paciente da folha contradiz o da guia?
 *
 * **Qualquer confirmação basta.** Nome conferindo limpa um cartão que não
 * bate, e vice-versa. Parece frouxo e não é: o que se quer pegar é a guia de
 * OUTRA pessoa, e aí nenhum dos dois confirma. Um só discordando é quase
 * sempre qualidade de dado — carteirinha antiga, paciente migrado de outro
 * sistema, cartão reemitido, número guardado em formato diferente do impresso
 * na folha.
 *
 * Essa decisão tem um preço, e ele é consciente: dois pacientes de nome igual
 * e cartões diferentes deixam de ser acusados. É mais raro que o ruído de
 * cadastro, e a alternativa é pior — um aviso que dispara toda hora ensina a
 * clicar sem ler, e aí não protege no dia em que a divergência é real.
 *
 * Quando os dois discordam, o cartão é quem nomeia o aviso: é impresso, e erra
 * menos que nome manuscrito.
 */
export function conferirPacienteDaFolha(
  folha: { paciente: string | null; numero_cartao: string | null },
  guia: { paciente?: { nome: string; carteirinha: string | null } | null } | null,
): ConferenciaDaFolha {
  const pacienteDaGuia = guia?.paciente

  if (!pacienteDaGuia) {
    return CONFERE
  }

  const cartao = conferirCartao(folha.numero_cartao, pacienteDaGuia.carteirinha)
  const nome = conferirNome(folha.paciente, pacienteDaGuia.nome)

  if (cartao === true || nome === true) {
    return CONFERE
  }

  if (cartao === false) {
    return {
      confere: false,
      campo: 'carteirinha',
      naFolha: folha.numero_cartao,
      naGuia: pacienteDaGuia.carteirinha,
    }
  }

  if (nome === false) {
    return {
      confere: false,
      campo: 'nome',
      naFolha: folha.paciente,
      naGuia: pacienteDaGuia.nome,
    }
  }

  // Nada comparável dos dois lados: não há o que acusar.
  return CONFERE
}

/** Frase do aviso e da auditoria — uma só, para a tela e o registro contarem a mesma história. */
export function descreverDivergencia(conferencia: ConferenciaDaFolha): string {
  const rotulo = conferencia.campo === 'carteirinha' ? 'Número do cartão' : 'Nome do paciente'

  return `${rotulo}: a folha diz "${conferencia.naFolha ?? '—'}" e a guia é de "${conferencia.naGuia ?? '—'}"`
}
