import axios from 'axios'

export function getHttpErrorMessage(error: unknown, fallback = 'Não foi possível concluir a operação.') {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as
      | { message?: string; errors?: Record<string, string[]> }
      | undefined

    if (data?.message) {
      return data.message
    }

    if (data?.errors) {
      return Object.values(data.errors).flat().join(' ')
    }
  }

  return fallback
}
