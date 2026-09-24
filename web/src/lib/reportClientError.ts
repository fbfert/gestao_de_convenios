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

import { authStorageKey } from '../stores/authStorageKey'

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
 * FNV-1a de 32 bits em hexadecimal, e `ErroClienteController::codigoDoErro`
 * faz O MESMO — o PHP percorre unidades UTF-16 justamente para bater com o
 * `charCodeAt` daqui em mensagens acentuadas.
 *
 * ISTO JÁ ESTEVE ERRADO, e o comentário anterior dizia o contrário: o backend
 * usava sha256 e o texto aqui afirmava que "o log guarda os dois". Não
 * guardava. Em 24/09/2026 a clínica leu `836920` na tela e o log tinha
 * `5F6052` para o mesmo erro — o código não achava nada, que é a única coisa
 * que ele precisa fazer.
 *
 * Quem for mostrar o código na tela usa `codigoDoRelato`, não esta função
 * direto: é ela que garante que o hash saia sobre os mesmos valores que o
 * relato envia.
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
 * Mensagem e pilha exatamente como vão no relato.
 *
 * Um lugar só decide o que entra no hash. O corte acontecia apenas no envio, e
 * a tela hasheava o valor inteiro — então uma pilha acima de 4000 caracteres
 * (comum em erro de React) já daria códigos diferentes mesmo com o mesmo
 * algoritmo dos dois lados.
 */
function normalizar(erro: ErroParaRelatar): { message: string; stack: string } {
  return {
    message: String(erro.message ?? '').slice(0, 1000),
    stack: (erro.stack ?? '').slice(0, LIMITE_STACK),
  }
}

/**
 * O código do erro como o servidor vai gravá-lo.
 *
 * É esta a função que a tela de erro usa. Chamar `codigoDoErro` direto com o
 * erro cru daria outro número quando a pilha passa do limite de envio.
 */
export function codigoDoRelato(erro: ErroParaRelatar): string {
  try {
    const { message, stack } = normalizar(erro)

    return message === '' ? '' : codigoDoErro(message, stack)
  } catch {
    return ''
  }
}

/**
 * A credencial da sessão, quando houver.
 *
 * Lida do localStorage, e não do `authStore`: importar o store traria zustand e
 * seu grafo de módulos para dentro do caminho que roda justamente quando algo
 * já quebrou — é a mesma razão de aqui se usar `fetch` e não o axios.
 *
 * SEM ISSO TODO RELATO CHEGAVA ANÔNIMO. Os três erros registrados em 24/09/2026
 * vieram de telas com usuário logado e gravaram `tenant_id: null` — porque o
 * token nunca era anexado, e o servidor não tinha o que ler.
 *
 * Qualquer falha na leitura é ignorada: relato sem token é muito melhor do que
 * relato nenhum.
 */
function tokenDaSessao(): string | null {
  try {
    const bruto = window.localStorage.getItem(authStorageKey)

    if (!bruto) {
      return null
    }

    const token = (JSON.parse(bruto) as { state?: { token?: unknown } })?.state?.token

    return typeof token === 'string' && token !== '' ? token : null
  } catch {
    return null
  }
}

/**
 * Relata um erro. Nunca lança, nunca devolve promessa rejeitada.
 *
 * Devolve o código do erro para quem quiser mostrá-lo na tela — e uma string
 * vazia quando nem isso foi possível.
 */
export function reportClientError(erro: ErroParaRelatar): string {
  try {
    const { message, stack } = normalizar(erro)

    if (message === '') {
      return ''
    }

    const codigo = codigoDoErro(message, stack)
    const chave = `${message}\n${stack}`

    // Já vimos este exatamente? Devolve o código, mas não manda de novo.
    if (jaEnviados.has(chave) || enviadosNaSessao >= MAXIMO_POR_SESSAO) {
      return codigo
    }

    jaEnviados.add(chave)
    enviadosNaSessao += 1

    const token = tokenDaSessao()

    void fetch(`${apiUrl()}/erros-cliente`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        // A rota é pública de propósito (erro na tela de login também precisa
        // chegar), mas quando há sessão o token é o que diz de qual clínica e de
        // qual pessoa veio o erro.
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
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
