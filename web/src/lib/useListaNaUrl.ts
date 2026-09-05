import { useCallback, useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'

/**
 * Guarda página + filtros de uma lista na query string da URL, em vez de
 * `useState` local — sobrevive a F5, ao botão Voltar do navegador, e dá pra
 * favoritar/compartilhar o link já filtrado. Aplicar um novo filtro sempre
 * volta pra página 1.
 */
export function useListaNaUrl<F extends Record<string, string>>(defaults: F) {
  const [searchParams, setSearchParams] = useSearchParams()

  const page = Math.max(1, Number(searchParams.get('page')) || 1)

  const filters = useMemo(() => {
    const result = { ...defaults }
    for (const key of Object.keys(defaults) as (keyof F)[]) {
      const value = searchParams.get(String(key))
      if (value !== null) {
        result[key] = value as F[keyof F]
      }
    }
    return result
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams])

  const setFilters = useCallback(
    (next: F) => {
      setSearchParams((prev) => {
        const params = new URLSearchParams(prev)
        for (const [key, value] of Object.entries(next)) {
          if (value) {
            params.set(key, String(value))
          } else {
            params.delete(key)
          }
        }
        params.set('page', '1')
        return params
        // replace: true — trocar filtro/página não deve empilhar uma entrada
        // nova no histórico do navegador a cada tecla/clique, senão o botão
        // Voltar do navegador vira inútil nessas telas (precisaria de um
        // clique por filtro trocado só para sair da lista).
      }, { replace: true })
    },
    [setSearchParams],
  )

  const setPage = useCallback(
    (next: number) => {
      setSearchParams((prev) => {
        const params = new URLSearchParams(prev)
        params.set('page', String(next))
        return params
      }, { replace: true })
    },
    [setSearchParams],
  )

  return { page, filters, setFilters, setPage, searchParams }
}
