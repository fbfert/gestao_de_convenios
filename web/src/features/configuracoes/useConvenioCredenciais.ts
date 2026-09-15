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



// ── De-para do convênio ──────────────────────────────────────────────────────
//
// O convênio agora vai no CAMINHO (`.../{convenio}/mapeamentos/*`), e não como
// campo do formulário: quem escolhe é o seletor do topo da tela, e um de-para
// não existe fora de um convênio. As rotas antigas, com `convenio_id` em query,
// continuam respondendo por um ciclo para a aba depreciada.

export type EspecialidadeMapeamento = {
  id: number
  convenio_id: number
  especialidade_id: number
  codigo_procedimento: string
  descricao_operadora: string | null
  quantidade_padrao: number
  usa_descricao_generica: boolean
  valor_generico: string | null
  ativo: boolean
  especialidade?: { id: number; nome: string }
}

export type ProfissionalMapeamento = {
  id: number
  convenio_id: number
  profissional_id: number
  codigo_operadora: string
  nome_operadora: string | null
  ativo: boolean
  profissional?: { id: number; nome: string }
}

export type EspecialidadeMapeamentoForm = {
  especialidade_id: string
  codigo_procedimento: string
  descricao_operadora: string
  quantidade_padrao: string
  usa_descricao_generica: boolean
  valor_generico: string
  ativo: boolean
}

export type ProfissionalMapeamentoForm = {
  profissional_id: string
  codigo_operadora: string
  nome_operadora: string
  ativo: boolean
}

const CHAVE_MAPEAMENTOS = [...CHAVE, 'mapeamentos']

export function useEspecialidadeMapeamentos(convenioId: number | null) {
  return useQuery({
    queryKey: [...CHAVE_MAPEAMENTOS, 'especialidades', convenioId ?? ''],
    enabled: convenioId !== null,
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: EspecialidadeMapeamento[] }>(
        `${BASE}/${convenioId}/mapeamentos/especialidades`,
      )
      return data.data
    },
  })
}

export function useSalvarEspecialidadeMapeamento() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      convenioId,
      id,
      payload,
    }: {
      convenioId: number
      id?: number
      payload: EspecialidadeMapeamentoForm
    }) => {
      const body = {
        // O convênio segue no corpo porque a request da API o valida; o do
        // caminho é quem manda, e os dois são sempre o mesmo.
        convenio_id: convenioId,
        especialidade_id: Number(payload.especialidade_id),
        codigo_procedimento: payload.codigo_procedimento,
        descricao_operadora: payload.descricao_operadora.trim() || null,
        quantidade_padrao: payload.quantidade_padrao ? Number(payload.quantidade_padrao) : 10,
        usa_descricao_generica: payload.usa_descricao_generica,
        valor_generico: payload.valor_generico.trim() || null,
        ativo: payload.ativo,
      }
      const base = `${BASE}/${convenioId}/mapeamentos/especialidades`
      const { data } = id
        ? await apiClient.patch<{ data: EspecialidadeMapeamento }>(`${base}/${id}`, body)
        : await apiClient.post<{ data: EspecialidadeMapeamento }>(base, body)

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: [...CHAVE_MAPEAMENTOS, 'especialidades'] })
      // O de-para decide se um item é elegível para automação: sem isto a tela
      // de Solicitações seguiria dizendo "mapeamento não configurado".
      await queryClient.invalidateQueries({ queryKey: ['solicitacoes'] })
      await queryClient.invalidateQueries({
        queryKey: ['configuracoes', 'unimed', 'mapeamentos', 'especialidades'],
      })
    },
  })
}

export function useProfissionalMapeamentos(convenioId: number | null) {
  return useQuery({
    queryKey: [...CHAVE_MAPEAMENTOS, 'profissionais', convenioId ?? ''],
    enabled: convenioId !== null,
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: ProfissionalMapeamento[] }>(
        `${BASE}/${convenioId}/mapeamentos/profissionais`,
      )
      return data.data
    },
  })
}

export function useSalvarProfissionalMapeamento() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      convenioId,
      id,
      payload,
    }: {
      convenioId: number
      id?: number
      payload: ProfissionalMapeamentoForm
    }) => {
      const body = {
        convenio_id: convenioId,
        profissional_id: Number(payload.profissional_id),
        codigo_operadora: payload.codigo_operadora,
        nome_operadora: payload.nome_operadora.trim() || null,
        ativo: payload.ativo,
      }
      const base = `${BASE}/${convenioId}/mapeamentos/profissionais`
      const { data } = id
        ? await apiClient.patch<{ data: ProfissionalMapeamento }>(`${base}/${id}`, body)
        : await apiClient.post<{ data: ProfissionalMapeamento }>(base, body)

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: [...CHAVE_MAPEAMENTOS, 'profissionais'] })
      await queryClient.invalidateQueries({
        queryKey: ['configuracoes', 'unimed', 'mapeamentos', 'profissionais'],
      })
    },
  })
}

export { getHttpErrorMessage }
