export type RoleRef = {
  id: number
  name: string
  /** Papel de sistema: permissões editáveis, mas sem renomear nem excluir. */
  sistema: boolean
  permissions_count?: number
  users_count?: number
}

export type PermissionRef = {
  name: string
  /** Texto legível, vindo do PermissionCatalog da API. */
  label: string
  domain: string
}

export type RolePermissionsResponse = {
  data: {
    role: RoleRef
    permissions: PermissionRef[]
  }
}
