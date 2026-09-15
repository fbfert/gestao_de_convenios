import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'

const BASE = '/configuracoes/convenios-credenciais'

/** Chave de cache própria: a tela antiga (`['configuracoes','unimed']`) segue viva por um ciclo. */
const CHAVE = ['configuracoes', 'convenios-credenciais']

export type CampoDoDriver = {
  chave: string
  rotulo: string
  tipo: 'text' | 'password' | 'url'
  obrigatorio: boolean
  dica?: string
}

export type CatalogoDoDriver = {
  driver: string | null
  rotulo: string | null
  implementado: boolean
  aviso: string | null
  campos: CampoDoDriver[]
}

/**
 * Campo secreto volta como `preenchido`, nunca o valor — vale para qualquer
 * driver, não só para a senha da Unimed.
 */
export type ValorDeCampo = { valor: string | null } | { preenchido: boolean }

export type CredencialDoConvenio = {
  id: number
  driver: string
  ativo: boolean
  pronta: boolean
  campos: Record<string, ValorDeCampo>
  automation_paused_at: string | null
  automation_paused_reason: string | null
  updated_at: string | null
}

export type ConvenioComCredencial = {
  convenio: {
    id: number
    nome: string
    connector_type: string | null
    /** O interruptor da automação — informação diferente de "tem credencial". */
    connector_driver: string | null
    ativo: boolean
  }
  driver: string | null
  catalogo: CatalogoDoDriver
  credencial: CredencialDoConvenio | null
}

export type ConvenioCredenciaisResposta = {
  data: ConvenioComCredencial[]
  meta: { drivers: CatalogoDoDriver[] }
}

export type CredencialForm = {
  driver: string
  credenciais: Record<string, string>
}

export function useConvenioCredenciais(options?: { enabled?: boolean }) {
  return useQuery({
    queryKey: CHAVE,
    queryFn: async () => {
      const { data } = await apiClient.get<ConvenioCredenciaisResposta>(BASE)
      return data
    },
    enabled: options?.enabled ?? true,
  })
}

export function useSalvarConvenioCredencial() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ convenioId, payload }: { convenioId: number; payload: CredencialForm }) => {
      const { data } = await apiClient.put<{ data: ConvenioComCredencial }>(
        `${BASE}/${convenioId}`,
        payload,
      )
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: CHAVE })
      // A tela antiga lê a mesma credencial por outro caminho; sem isto ela
      // mostraria o valor velho até alguém recarregar.
      await queryClient.invalidateQueries({ queryKey: ['configuracoes', 'unimed'] })
    },
  })
}

export function useReativarConvenio() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (convenioId: number) => {
      const { data } = await apiClient.post<{ data: ConvenioComCredencial }>(
        `${BASE}/${convenioId}/reativar`,
      )
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: CHAVE })
      await queryClient.invalidateQueries({ queryKey: ['configuracoes', 'unimed'] })
    },
  })
}

export function useConvenioWorkerHealth(convenioId: number | null) {
  return useQuery({
    queryKey: [...CHAVE, 'worker-health', convenioId ?? ''],
    enabled: false,
    retry: false,
    queryFn: async () => {
      const { data } = await apiClient.get<{
        data: {
          status: 'available' | 'unavailable' | 'not_applicable'
          worker: Record<string, unknown> | null
        }
      }>(`${BASE}/${convenioId}/worker-health`)
      return data.data
    },
  })
}

/** O valor de um campo não secreto, para preencher o formulário. */
export function valorDoCampo(campos: Record<string, ValorDeCampo> | undefined, chave: string) {
  const campo = campos?.[chave]

  return campo && 'valor' in campo ? (campo.valor ?? '') : ''
}

/** Se um campo secreto já tem valor gravado — o que permite salvar deixando-o em branco. */
export function segredoPreenchido(
  campos: Record<string, ValorDeCampo> | undefined,
  chave: string,
) {
  const campo = campos?.[chave]

  return campo !== undefined && 'preenchido' in campo && campo.preenchido
}

export { getHttpErrorMessage }
