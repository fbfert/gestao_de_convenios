export type RoleRef = {
  id: number
  name: string
}

export type PermissionRef = {
  name: string
  domain: string
}

export type RolePermissionsResponse = {
  data: {
    role: RoleRef
    permissions: PermissionRef[]
  }
}
