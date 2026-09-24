/**
 * Onde a sessão fica no localStorage.
 *
 * Mora num módulo próprio, sem importar nada, porque quem precisa dela não é só
 * o `authStore`: `lib/reportClientError.ts` também lê a credencial daí, e ele
 * roda justamente quando algo já quebrou. Importar o store para pegar uma string
 * traria zustand e seu grafo de módulos para dentro desse caminho.
 */
export const authStorageKey = 'gestao-convenios-auth'
