import { useEffect, useMemo, useState } from 'react'
import {
  getHttpErrorMessage,
  usePermissions,
  useRolePermissions,
  useRoles,
  useUpdateRolePermissions,
} from './usePermissoes'
import type { PermissionRef } from './types'

function selectClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function groupPermissions(permissions: PermissionRef[]) {
  return permissions.reduce<Record<string, PermissionRef[]>>((acc, permission) => {
    const group = permission.domain
    acc[group] ??= []
    acc[group].push(permission)
    return acc
  }, {})
}

const domainLabels: Record<string, string> = {
  solicitacoes: 'Solicitações',
  guias: 'Guias',
  antecipacoes: 'Antecipações',
  lancamentos: 'Lançamentos',
  conciliacoes: 'Conciliações',
  medicos: 'Médicos',
  usuarios: 'Usuários',
  convenios: 'Convênios',
  permissoes: 'Permissões',
}

export function PermissoesPage() {
  const [selectedRole, setSelectedRole] = useState('')
  const [selectedPermissions, setSelectedPermissions] = useState<string[]>([])
  const [formError, setFormError] = useState<string | null>(null)

  const rolesQuery = useRoles()
  const permissionsQuery = usePermissions()
  const rolePermissionsQuery = useRolePermissions(selectedRole)
  const updateRolePermissions = useUpdateRolePermissions()

  const roles = useMemo(() => rolesQuery.data ?? [], [rolesQuery.data])
  const permissions = useMemo(() => permissionsQuery.data ?? [], [permissionsQuery.data])
  const groupedPermissions = useMemo(() => groupPermissions(permissions), [permissions])

  useEffect(() => {
    if (roles.length === 0) {
      return
    }

    setSelectedRole((current) => current || roles[0].name)
  }, [roles])

  useEffect(() => {
    if (!rolePermissionsQuery.data) {
      return
    }

    setSelectedPermissions(rolePermissionsQuery.data.permissions.map((permission) => permission.name))
  }, [rolePermissionsQuery.data])

  const togglePermission = (permissionName: string) => {
    setSelectedPermissions((current) =>
      current.includes(permissionName)
        ? current.filter((item) => item !== permissionName)
        : [...current, permissionName],
    )
  }

  const handleSave = async () => {
    setFormError(null)

    try {
      await updateRolePermissions.mutateAsync({
        roleName: selectedRole,
        permissions: selectedPermissions,
      })
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível salvar as permissões do papel.'))
    }
  }

  return (
    <div className="space-y-8" data-testid="permissoes-page">
      <section className="space-y-4">
        <div>
          <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Permissões</p>
          <h2 className="mt-2 text-3xl font-semibold text-white">Atribuição de permissões por papel</h2>
          <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
            O catálogo é fixo e o admin só liga ou desliga permissões para cada papel do tenant.
          </p>
        </div>

        <div className="grid gap-4 md:grid-cols-3">
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Papéis</p>
            <p className="mt-2 text-2xl font-semibold text-white">{roles.length}</p>
          </article>
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Permissões</p>
            <p className="mt-2 text-2xl font-semibold text-white">{permissions.length}</p>
          </article>
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Papel ativo</p>
            <p className="mt-2 text-2xl font-semibold text-white">{selectedRole || 'Nenhum'}</p>
          </article>
        </div>
      </section>

      <section className="grid gap-6 xl:grid-cols-[280px_1fr]">
        <aside className="space-y-4 rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <div>
            <h3 className="text-lg font-semibold text-white">Papéis</h3>
            <p className="text-sm text-slate-300">Selecione um papel para editar as permissões.</p>
          </div>

          {rolesQuery.isLoading ? (
            <div className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
              Carregando papéis...
            </div>
          ) : rolesQuery.isError ? (
            <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-sm text-rose-100">
              Não foi possível carregar os papéis.
            </div>
          ) : (
            <div className="space-y-2">
              {roles.map((role) => (
                <button
                  key={role.id}
                  type="button"
                  onClick={() => setSelectedRole(role.name)}
                  className={[
                    'w-full rounded-2xl border px-4 py-3 text-left text-sm font-medium transition',
                    selectedRole === role.name
                      ? 'border-cyan-300/40 bg-cyan-400/15 text-cyan-50'
                      : 'border-white/10 bg-white/5 text-slate-200 hover:bg-white/10',
                  ].join(' ')}
                  data-testid={`papel-${role.name}`}
                >
                  {role.name}
                </button>
              ))}
            </div>
          )}
        </aside>

        <section className="space-y-4 rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <h3 className="text-lg font-semibold text-white">Checklist de permissões</h3>
              <p className="text-sm text-slate-300">
                {rolePermissionsQuery.isLoading
                  ? 'Carregando permissões do papel...'
                  : 'As permissões abaixo vêm do catálogo fixo do sistema.'}
              </p>
            </div>

            <div className="min-w-72">
              <label className="block space-y-2">
                <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Papel</span>
                <select
                  value={selectedRole}
                  onChange={(event) => setSelectedRole(event.target.value)}
                  className={selectClasses()}
                  data-testid="papel-selecionado"
                >
                  {roles.map((role) => (
                    <option key={role.id} value={role.name}>
                      {role.name}
                    </option>
                  ))}
                </select>
              </label>
            </div>
          </div>

          {rolePermissionsQuery.isError ? (
            <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-sm text-rose-100">
              Não foi possível carregar as permissões do papel.
            </div>
          ) : null}

          <div className="grid gap-4 lg:grid-cols-2">
            {Object.entries(groupedPermissions).map(([domain, items]) => (
              <div key={domain} className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
                <h4 className="text-sm font-semibold uppercase tracking-[0.25em] text-cyan-100">
                  {domainLabels[domain] ?? domain}
                </h4>
                <div className="mt-4 space-y-2">
                  {items.map((permission) => (
                    <label
                      key={permission.name}
                      className="flex items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3"
                    >
                      <span className="text-sm text-white">{permission.name}</span>
                      <input
                        type="checkbox"
                        checked={selectedPermissions.includes(permission.name)}
                        onChange={() => togglePermission(permission.name)}
                        className="h-4 w-4 rounded border-white/20 bg-white/10 text-cyan-300 focus:ring-cyan-300/20"
                        data-testid={`permissao-${permission.name}`}
                      />
                    </label>
                  ))}
                </div>
              </div>
            ))}
          </div>

          {formError ? (
            <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
              {formError}
            </p>
          ) : null}

          <button
            type="button"
            onClick={handleSave}
            className="inline-flex items-center justify-center rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60"
            disabled={updateRolePermissions.isPending || !selectedRole}
            data-testid="permissoes-salvar"
          >
            {updateRolePermissions.isPending ? 'Salvando...' : 'Salvar permissões'}
          </button>
        </section>
      </section>
    </div>
  )
}
