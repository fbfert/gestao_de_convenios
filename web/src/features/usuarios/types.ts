import type { RoleRef } from '../permissoes/types'
import type { ProfissionalRef } from '../../lib/queries/useReferenceData'

export type Usuario = {
  id: number
  tenant_id: number
  name: string
  email: string
  ativo: boolean
  role: string
  profissional_id: number | null
  profissional?: Pick<ProfissionalRef, 'id' | 'nome' | 'especialidade_id'>
}

export type UsuarioForm = {
  name: string
  email: string
  password: string
  role: string
  profissional_id: string
  ativo: boolean
}

export type UsuarioFilters = {
  busca: string
}

export type PaginatedResponse<T> = {
  data: T[]
  meta?: {
    current_page?: number
    last_page?: number
    total?: number
  }
}

export type UsuarioRoleRef = RoleRef
