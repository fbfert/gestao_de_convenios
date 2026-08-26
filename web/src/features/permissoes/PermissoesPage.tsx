import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import { Link, useMatch, useNavigate } from 'react-router-dom'
import { Select } from '../../components/ui/Select'
import {
  getHttpErrorMessage,
  useCriarPapel,
  useExcluirPapel,
  usePermissions,
  useRenomearPapel,
  useRolePermissions,
  useRoles,
  useUpdateRolePermissions,
} from './usePermissoes'
import type { PermissionRef, RoleRef } from './types'
import { Botao } from '../../components/ui/Botao'

const card = 'rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6'
const campo =
  'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'

const domainLabels: Record<string, string> = {
  dashboard: 'Acesso às telas',
  solicitacoes: 'Solicitações',
  guias: 'Guias',
  antecipacoes: 'Antecipações',
  lancamentos: 'Sessões',
  conciliacoes: 'Conciliações',
  profissionais: 'Profissionais',
  especialidades: 'Especialidades',
  medicos: 'Médicos',
  usuarios: 'Usuários',
  convenios: 'Convênios',
  permissoes: 'Permissões',
  manual: 'Manual',
  configuracoes: 'Configurações',
}

function agrupar(permissions: PermissionRef[]) {
  return permissions.reduce<Record<string, PermissionRef[]>>((acc, permission) => {
    acc[permission.domain] ??= []
    acc[permission.domain].push(permission)
    return acc
  }, {})
}

function Erro({ mensagem }: { mensagem: string | null }) {
  if (!mensagem) {
    return null
  }

  return (
    <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
      {mensagem}
    </p>
  )
}

/**
 * Perfis e permissões.
 *
 * Listagem, criação e edição em telas separadas, como manda a spec
 * `crud-lista-formulario-separados`. A edição é a tela mais densa do sistema —
 * são 35 permissões — e não cabia dividir espaço com a lista de papéis.
 */
export function PermissoesPage() {
  const isCreateRoute = useMatch('/permissoes/novo') !== null
  const editRouteMatch = useMatch('/permissoes/:name/editar')
  const papelEditado = editRouteMatch?.params.name ?? null

  if (isCreateRoute) {
    return <NovoPapel />
  }

  if (papelEditado) {
    return <EditarPapel nome={decodeURIComponent(papelEditado)} />
  }

  return <ListaDePapeis />
}

function ListaDePapeis() {
  const navigate = useNavigate()
  const rolesQuery = useRoles()
  const papeis = useMemo(() => rolesQuery.data ?? [], [rolesQuery.data])

  return (
    <div className="space-y-8" data-testid="permissoes-page">
      <section className="flex flex-col gap-4 sm:items-start lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Perfis e Permissões</p>
          <h2 className="mt-2 text-display font-semibold text-white">Papéis da clínica</h2>
        </div>

        <Botao variante="primario" onClick={() => navigate('/permissoes/novo')} data-testid="papel-novo">
          Novo perfil
        </Botao>
      </section>

      {rolesQuery.isLoading ? (
        <div className={card}>Carregando papéis...</div>
      ) : rolesQuery.isError ? (
        <Erro mensagem="Não foi possível carregar os papéis." />
      ) : (
        <div className="grid gap-4 md:grid-cols-2">
          {papeis.map((papel) => (
            <Link
              key={papel.id}
              to={`/permissoes/${encodeURIComponent(papel.name)}/editar`}
              className="group block rounded-janela border border-linha bg-fundo p-5 transition hover:border-acento/40 hover:bg-superficie"
              data-testid={`papel-${papel.name}`}
            >
              <div className="flex items-center justify-between gap-3">
                <p className="text-corpo-lg font-semibold text-white group-hover:text-cyan-50">
                  {papel.name}
                </p>
                {papel.sistema ? (
                  <span className="rounded-full border border-white/15 bg-white/5 px-3 py-1 text-meta text-slate-300">
                    Do sistema
                  </span>
                ) : null}
              </div>
              <p className="mt-2 text-corpo text-slate-300">
                {papel.permissions_count ?? 0} permissões · {papel.users_count ?? 0} usuários
              </p>
            </Link>
          ))}
        </div>
      )}
    </div>
  )
}

function NovoPapel() {
  const navigate = useNavigate()
  const [nome, setNome] = useState('')
  const [copiarDe, setCopiarDe] = useState('')
  const [erro, setErro] = useState<string | null>(null)

  const rolesQuery = useRoles()
  const criar = useCriarPapel()
  const papeis = useMemo(() => rolesQuery.data ?? [], [rolesQuery.data])

  const salvar = async (event: FormEvent) => {
    event.preventDefault()
    setErro(null)

    try {
      const papel = await criar.mutateAsync({
        name: nome.trim(),
        copiar_de: copiarDe || undefined,
      })

      // Vai direto para a edição: papel sem permissão nenhuma não serve para
      // nada, e é aqui que a pessoa espera continuar.
      navigate(`/permissoes/${encodeURIComponent(papel.name)}/editar`)
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível criar o perfil.'))
    }
  }

  return (
    <div className="space-y-6" data-testid="permissoes-page">
      <div>
        <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Perfis e Permissões</p>
        <h2 className="mt-2 text-display font-semibold text-white">Novo perfil</h2>
      </div>

      <section className={card}>
        <form onSubmit={salvar} className="space-y-4" data-testid="papel-form">
          <label className="block space-y-2">
            <span className="text-corpo font-medium text-slate-200">Nome do perfil</span>
            <input
              required
              value={nome}
              onChange={(event) => setNome(event.target.value)}
              placeholder="recepcao"
              className={campo}
              data-testid="papel-nome"
            />
            <span className="block text-meta text-slate-400">
              Letras minúsculas, números e hífen. O nome aparece no cadastro de usuários.
            </span>
          </label>

          <label className="block space-y-2">
            <span className="text-corpo font-medium text-slate-200">Copiar permissões de</span>
            <Select value={copiarDe} onChange={(event) => setCopiarDe(event.target.value)}>
              <option value="">Começar sem nenhuma permissão</option>
              {papeis.map((papel) => (
                <option key={papel.id} value={papel.name}>
                  {papel.name}
                </option>
              ))}
            </Select>
          </label>

          <Erro mensagem={erro} />

          <div className="flex gap-3">
            <Botao variante="primario" disabled={criar.isPending}>
              {criar.isPending ? 'Criando...' : 'Criar perfil'}
            </Botao>
            <button
              type="button"
              onClick={() => navigate('/permissoes')}
              className="inline-flex min-h-6 items-center text-corpo text-slate-300"
              data-testid="papel-fechar"
            >
              Cancelar
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

function EditarPapel({ nome }: { nome: string }) {
  const navigate = useNavigate()
  const [selecionadas, setSelecionadas] = useState<string[]>([])
  const [novoNome, setNovoNome] = useState(nome)
  const [erro, setErro] = useState<string | null>(null)
  const carregadoRef = useRef<string | null>(null)

  const permissionsQuery = usePermissions()
  const rolePermissionsQuery = useRolePermissions(nome)
  const rolesQuery = useRoles()
  const atualizar = useUpdateRolePermissions()
  const renomear = useRenomearPapel()
  const excluir = useExcluirPapel()

  const permissions = useMemo(() => permissionsQuery.data ?? [], [permissionsQuery.data])
  const agrupadas = useMemo(() => agrupar(permissions), [permissions])
  const papel: RoleRef | undefined = useMemo(
    () => (rolesQuery.data ?? []).find((item) => item.name === nome),
    [rolesQuery.data, nome],
  )

  // Carrega uma vez por papel: um refetch em background não pode desmarcar o
  // que o administrador acabou de mexer e ainda não salvou.
  useEffect(() => {
    if (!rolePermissionsQuery.data || carregadoRef.current === nome) {
      return
    }

    carregadoRef.current = nome
    setSelecionadas(rolePermissionsQuery.data.permissions.map((permission) => permission.name))
    setNovoNome(nome)
  }, [rolePermissionsQuery.data, nome])

  const alternar = (permissao: string) => {
    setSelecionadas((atual) =>
      atual.includes(permissao)
        ? atual.filter((item) => item !== permissao)
        : [...atual, permissao],
    )
  }

  const salvar = async () => {
    setErro(null)

    try {
      if (papel && !papel.sistema && novoNome.trim() !== nome) {
        const renomeado = await renomear.mutateAsync({ roleName: nome, name: novoNome.trim() })
        await atualizar.mutateAsync({ roleName: renomeado.name, permissions: selecionadas })
        carregadoRef.current = null
        navigate(`/permissoes/${encodeURIComponent(renomeado.name)}/editar`, { replace: true })
        return
      }

      await atualizar.mutateAsync({ roleName: nome, permissions: selecionadas })
      navigate('/permissoes')
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível salvar o perfil.'))
    }
  }

  const remover = async () => {
    setErro(null)

    if (!window.confirm(`Excluir o perfil "${nome}"? Isso não pode ser desfeito.`)) {
      return
    }

    try {
      await excluir.mutateAsync(nome)
      navigate('/permissoes')
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível excluir o perfil.'))
    }
  }

  const salvando = atualizar.isPending || renomear.isPending

  return (
    <div className="space-y-6" data-testid="permissoes-page">
      <div className="flex flex-col gap-4 sm:items-start lg:flex-row lg:items-start lg:justify-between">
        <div>
          <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Perfis e Permissões</p>
          <h2 className="mt-2 text-display font-semibold text-white">Editar perfil</h2>
          <p className="mt-2 max-w-3xl text-corpo leading-6 text-slate-300">
            {papel?.sistema
              ? 'Este é um perfil do sistema: as permissões são editáveis, mas o nome não muda e ele não pode ser excluído.'
              : 'Marque o que este perfil pode fazer. As permissões de acesso definem também o que aparece no menu.'}
          </p>
        </div>

        <Link to="/permissoes" className="inline-flex min-h-6 items-center text-corpo text-cyan-200" data-testid="papel-fechar">
          ← Voltar aos perfis
        </Link>
      </div>

      <section className={card}>
        <label className="block max-w-md space-y-2">
          <span className="text-corpo font-medium text-slate-200">Nome do perfil</span>
          <input
            value={novoNome}
            onChange={(event) => setNovoNome(event.target.value)}
            disabled={papel?.sistema ?? true}
            className={`${campo} disabled:opacity-60`}
            data-testid="papel-nome"
          />
        </label>
      </section>

      {rolePermissionsQuery.isError ? (
        <Erro mensagem="Não foi possível carregar as permissões do perfil." />
      ) : null}

      <section className={`${card} space-y-4`}>
        <div>
          <h3 className="text-subtitulo font-semibold text-white">Permissões</h3>
          <p className="inline-flex min-h-6 items-center text-corpo text-slate-300">
            {rolePermissionsQuery.isLoading
              ? 'Carregando permissões do perfil...'
              : `${selecionadas.length} de ${permissions.length} marcadas.`}
          </p>
        </div>

        <div className="grid gap-4 lg:grid-cols-2">
          {Object.entries(agrupadas).map(([domain, items]) => (
            <div key={domain} className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1">
              <h4 className="text-corpo font-semibold uppercase tracking-[0.25em] text-cyan-100">
                {domainLabels[domain] ?? domain}
              </h4>
              <div className="mt-4 space-y-2">
                {items.map((permission) => (
                  <label
                    key={permission.name}
                    className="flex items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3"
                  >
                    <span>
                      <span className="block text-corpo text-white">{permission.label}</span>
                      <span className="block text-meta text-slate-400">{permission.name}</span>
                    </span>
                    <input
                      type="checkbox"
                      checked={selecionadas.includes(permission.name)}
                      onChange={() => alternar(permission.name)}
                      className="h-4 w-4 rounded border-white/20 bg-white/10 text-cyan-300 focus:ring-cyan-300/20"
                      data-testid={`permissao-${permission.name}`}
                    />
                  </label>
                ))}
              </div>
            </div>
          ))}
        </div>

        <Erro mensagem={erro} />

        <div className="flex flex-wrap items-center gap-3">
          <Botao
            type="button"
            variante="primario"
            onClick={salvar}
            disabled={salvando}
            data-testid="permissoes-salvar"
          >
            {salvando ? 'Salvando...' : 'Salvar perfil'}
          </Botao>

          {papel && !papel.sistema ? (
            <button
              type="button"
              onClick={remover}
              className="inline-flex items-center justify-center rounded-2xl border border-rose-400/30 h-10 px-4 text-corpo font-semibold text-rose-100 transition hover:bg-rose-500/10 disabled:opacity-60"
              disabled={excluir.isPending}
              data-testid="papel-excluir"
            >
              {excluir.isPending ? 'Excluindo...' : 'Excluir perfil'}
            </button>
          ) : null}
        </div>
      </section>
    </div>
  )
}
