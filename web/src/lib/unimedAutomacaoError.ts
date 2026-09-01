import axios from 'axios'

// Mensagem devolvida por ConsultarStatusUnimedService, CapturarSenhaValidadeUnimedService,
// GerarGuiaUnimedService e ConfirmarGuiaIncertaUnimedService (todas em
// api/app/Services/Automation/) quando não existe credencial Unimed ativa —
// seja porque nunca foi configurada, seja porque foi pausada automaticamente
// pelo circuit breaker. Usada para trocar o alert cru por um modal oferecendo
// ativar a automação.
const MENSAGEM_CREDENCIAL_INATIVA = 'A credencial Unimed ativa não está configurada.'

export function isUnimedCredencialInativaError(error: unknown): boolean {
  if (!axios.isAxiosError(error)) {
    return false
  }

  const data = error.response?.data as { errors?: Record<string, string[]> } | undefined

  return Object.values(data?.errors ?? {}).flat().includes(MENSAGEM_CREDENCIAL_INATIVA)
}
