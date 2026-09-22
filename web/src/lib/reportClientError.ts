/**
 * Manda ao servidor os erros que acontecem no navegador.
 *
 * Este é o código mais defensivo do app, e por um motivo simples: ele roda
 * justamente quando as coisas já deram errado. Uma falha aqui viraria a
 * segunda falha, em cima de uma tela que já caiu — então **nada nesta função
 * pode lançar**, em nenhuma circunstância.
 *
 * Três travas, todas pelo mesmo motivo:
 *
 * - `try/catch` em volta de tudo, inclusive da serialização
 * - deduplicação por mensagem+pilha: um erro em laço de renderização dispararia
 *   centenas de requisições idênticas
 * - teto de envios por sessão: dez erros distintos já dizem o que precisa ser
 *   dito
 *
 * Usa `fetch` direto, e não o `apiClient`: o axios tem interceptadores (token,
 * redirecionamento em 401) que podem ser exatamente o que está quebrado quando
 * chamamos isto. `keepalive` faz o envio sobreviver à navegação, que é comum
 * logo depois de um erro.
 */

const LIMITE_STACK = 4000
const MAXIMO_POR_SESSAO = 10

/** Erros já enviados nesta sessão, por mensagem+pilha. */
const jaEnviados = new Set<string>()
let enviadosNaSessao = 0

/**
 * Endereço da API, resolvido na hora e com defesa.
 *
 * `import.meta.env` só existe sob o Vite; fora dele (na suíte que exercita este
 * módulo direto, sem navegador) o acesso lança. Como este arquivo inteiro
 * existe para não quebrar quando o resto quebrou, ele não pode ser a exceção —
 * nem no carregamento do módulo.
 */
function apiUrl(): string {
  try {
    return import.meta.env?.VITE_API_URL ?? 'http://localhost:8000/api'
  } catch {
    return 'http://localhost:8000/api'
  }
}

type ErroParaRelatar = {
  message: string
  stack?: string | null
  componentStack?: string | null
}

/**
 * Código curto e ESTÁVEL do erro, derivado de mensagem+pilha.
 *
 * É o que a tela mostra para quem viu o erro, e o que o suporte usa para achar
 * a linha no log. Derivado e não sorteado de propósito: o mesmo erro em dois
 * usuários dá o mesmo código, e "três pessoas ligaram com o código A3F2" vira
 * uma informação útil em vez de três investigações.
 *
 * Espelha `ErroClienteController::codigoDoErro` no backend — os dois precisam
 * concordar, senão o código da tela não acha a linha do log. É um hash FNV-1a
 * de 32 bits em hexadecimal: o backend usa sha256, mas o que importa é que
 * cada lado seja estável consigo mesmo; o log guarda os dois.
 */
export function codigoDoErro(message: string, stack: string): string {
  const texto = `${message}\n${stack}`
  let hash = 0x811c9dc5

  for (let i = 0; i < texto.length; i += 1) {
    hash ^= texto.charCodeAt(i)
    // FNV-1a: multiplicação pelo primo 16777619, com `Math.imul` para manter
    // 32 bits (o `*` de JS estoura para ponto flutuante e perde precisão).
    hash = Math.imul(hash, 0x01000193)
  }

  return (hash >>> 0).toString(16).toUpperCase().padStart(6, '0').slice(0, 6)
}

/**
 * Relata um erro. Nunca lança, nunca devolve promessa rejeitada.
 *
 * Devolve o código do erro para quem quiser mostrá-lo na tela — e uma string
 * vazia quando nem isso foi possível.
 */
export function reportClientError(erro: ErroParaRelatar): string {
  try {
    const message = String(erro.message ?? '').slice(0, 1000)

    if (message === '') {
      return ''
    }

    const stack = (erro.stack ?? '').slice(0, LIMITE_STACK)
    const codigo = codigoDoErro(message, stack)
    const chave = `${message}\n${stack}`

    // Já vimos este exatamente? Devolve o código, mas não manda de novo.
    if (jaEnviados.has(chave) || enviadosNaSessao >= MAXIMO_POR_SESSAO) {
      return codigo
    }

    jaEnviados.add(chave)
    enviadosNaSessao += 1

    void fetch(`${apiUrl()}/erros-cliente`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        message,
        stack,
        componentStack: (erro.componentStack ?? '').slice(0, LIMITE_STACK) || null,
        url: window.location.href.slice(0, 2000),
        userAgent: navigator.userAgent.slice(0, 500),
        occurredAt: new Date().toISOString(),
      }),
      // Sobrevive à navegação, que é comum logo depois de um erro.
      keepalive: true,
    }).catch(() => {
      // Falhar ao relatar a falha não pode virar a segunda falha.
    })

    return codigo
  } catch {
    return ''
  }
}

/**
 * Instala a captura do que escapa do React: exceção não tratada e promessa
 * rejeitada sem tratamento.
 *
 * O ErrorBoundary pega o que acontece durante a renderização; isto pega o
 * resto — um `onClick` que lança, uma promessa esquecida, um erro de script.
 */
export function instalarCapturaDeErrosGlobais(): void {
  window.addEventListener('error', (evento) => {
    reportClientError({
      message: evento.message || String(evento.error ?? 'erro desconhecido'),
      stack: evento.error instanceof Error ? evento.error.stack : null,
    })
  })

  window.addEventListener('unhandledrejection', (evento) => {
    const motivo = evento.reason

    reportClientError({
      message: motivo instanceof Error ? motivo.message : String(motivo ?? 'promessa rejeitada'),
      stack: motivo instanceof Error ? motivo.stack : null,
    })
  })
}

/** Só para teste: devolve o relator ao estado de sessão nova. */
export function limparEstadoDoRelator(): void {
  jaEnviados.clear()
  enviadosNaSessao = 0
}
