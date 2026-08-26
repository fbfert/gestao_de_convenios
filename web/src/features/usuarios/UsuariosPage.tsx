import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import { ColunaOrdenavel } from '../../components/ui/ColunaOrdenavel'
import { useOrdenacao } from '../../lib/useOrdenacao'
import { useMatch, useNavigate } from 'react-router-dom'
import { Select } from '../../components/ui/Select'
import {
  getHttpErrorMessage,
  useAtualizarUsuario,
  useCriarUsuario,
  useProfissionaisDoTenant,
  useRolesDoTenant,
  useUsuarios,
} from './useUsuarios'
import type { Usuario, UsuarioForm } from './types'
import { Indicadores } from '../../components/ui/Indicadores'
import { Botao } from '../../components/ui/Botao'
import { Badge } from '../../components/ui/Badge'

const emptyForm: UsuarioForm = {
  name: '',
  email: '',
  password: '',
  role: '',
  profissional_id: '',
  ativo: true,
}

function selectClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function toForm(usuario: Usuario): UsuarioForm {
  return {
    name: usuario.name,
    email: usuario.email,
    password: '',
    role: usuario.role,
    profissional_id: usuario.profissional_id ? String(usuario.profissional_id) : '',
    ativo: usuario.ativo,
  }
}

export function UsuariosPage() {
  const navigate = useNavigate()
  const isCreateRoute = useMatch('/usuarios/novo') !== null
  const editRouteMatch = useMatch('/usuarios/:id/editar')
  const routeEditingId = editRouteMatch ? Number(editRouteMatch.params.id) : null
  const isEditRoute = routeEditingId !== null && Number.isInteger(routeEditingId)
  // Criar e editar acontecem em tela propria: com a lista junto, o formulario
  // ficava espremido e a pagina rolava de volta ao topo a cada acao.
  const isFormRoute = isCreateRoute || isEditRoute
  const [filters, setFilters] = useState({ busca: '' })
  const [draftFilters, setDraftFilters] = useState({ busca: '' })
  const [page, setPage] = useState(1)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState<UsuarioForm>(emptyForm)
  const [formError, setFormError] = useState<string | null>(null)

  const carregadoRef = useRef<number | null>(null)

  const { ordenacao, ordenarPor } = useOrdenacao({
    ordenar_por: 'nome',
    direcao: 'asc',
  })

  const usuariosQuery = useUsuarios({ ...filters, ...ordenacao }, page)
  const rolesQuery = useRolesDoTenant()
  const profissionaisQuery = useProfissionaisDoTenant()
  const criarUsuario = useCriarUsuario()
  const atualizarUsuario = useAtualizarUsuario()

  const usuarios = useMemo(() => usuariosQuery.data?.data ?? [], [usuariosQuery.data])
  const roles = useMemo(() => rolesQuery.data ?? [], [rolesQuery.data])
  const profissionais = useMemo(() => profissionaisQuery.data ?? [], [profissionaisQuery.data])
  const totalPages = usuariosQuery.data?.meta?.last_page ?? 1
  const totalAtivos = usuarios.filter((usuario) => usuario.ativo).length
  const totalProfissionais = usuarios.filter((usuario) => usuario.role === 'profissional').length

  useEffect(() => {
    if (roles.length === 0) {
      return
    }

    setForm((current) =>
      current.role
        ? current
        : {
            ...current,
            role: roles[0].name,
          },
    )
  }, [roles])

  useEffect(() => {
    if (profissionais.length === 0) {
      return
    }

    setForm((current) => {
      if (current.role !== 'profissional') {
        return current.profissional_id ? { ...current, profissional_id: '' } : current
      }

      const hasSelected = profissionais.some(
        (profissional) => String(profissional.id) === current.profissional_id,
      )

      return hasSelected
        ? current
        : {
            ...current,
            profissional_id: String(profissionais[0].id),
          }
    })
  }, [profissionais])

  const usuarioEmEdicao = useMemo(
    () => (isEditRoute ? usuarios.find((usuario) => usuario.id === routeEditingId) ?? null : null),
    [isEditRoute, routeEditingId, usuarios],
  )

  /*
    Preenche o formulario quando a tela de edicao e aberta direto pela URL ou
    recarregada — nesses casos handleEdit nunca rodou. O ref evita sobrescrever
    o que o operador ja digitou a cada refetch da lista.
  */
  useEffect(() => {
    if (!isEditRoute) {
      carregadoRef.current = null
      return
    }

    if (!usuarioEmEdicao || carregadoRef.current === usuarioEmEdicao.id) {
      return
    }

    carregadoRef.current = usuarioEmEdicao.id
    setEditingId(usuarioEmEdicao.id)
    setForm(toForm(usuarioEmEdicao))
    setFormError(null)
  }, [isEditRoute, usuarioEmEdicao])

  const handleFilterSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setPage(1)
    setFilters(draftFilters)
  }

  const handleNew = () => {
    navigate('/usuarios/novo')
    setEditingId(null)
    setForm(emptyForm)
    setFormError(null)
  }

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFormError(null)

    try {
      if (editingId) {
        const payload: Partial<UsuarioForm> = {
          name: form.name,
          email: form.email,
          role: form.role,
          ativo: form.ativo,
        }

        if (form.password.trim() !== '') {
          payload.password = form.password
        }

        if (form.role === 'profissional') {
          payload.profissional_id = form.profissional_id
        } else {
          payload.profissional_id = ''
        }

        await atualizarUsuario.mutateAsync({
          id: editingId,
          payload,
        })
      } else {
        await criarUsuario.mutateAsync(form)
      }

      setEditingId(null)
      setForm(emptyForm)
      navigate('/usuarios')
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível salvar o usuário.'))
    }
  }

  const handleEdit = (usuario: Usuario) => {
    setEditingId(usuario.id)
    setForm(toForm(usuario))
    setFormError(null)
    navigate(`/usuarios/${usuario.id}/editar`)
  }

  const handleToggleAtivo = async (usuario: Usuario) => {
    setFormError(null)

    try {
      await atualizarUsuario.mutateAsync({
        id: usuario.id,
        payload: {
          ativo: !usuario.ativo,
        },
      })
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível atualizar o status do usuário.'))
    }
  }

  const handleCancel = () => {
    setEditingId(null)
    setForm(emptyForm)
    setFormError(null)
    navigate('/usuarios')
  }

  const handleRoleChange = (value: string) => {
    setForm((current) => ({
      ...current,
      role: value,
      profissional_id: value === 'profissional' ? current.profissional_id : '',
    }))
  }

  const formIsComplete =
    form.name.trim() !== '' &&
    form.email.trim() !== '' &&
    form.role !== '' &&
    (editingId
      ? form.password.trim() === '' || form.password.trim().length >= 8
      : form.password.trim().length >= 8) &&
    (form.role !== 'profissional' || form.profissional_id !== '')

  return (
    <div className="space-y-8" data-testid="usuarios-page">
      {!isFormRoute ? (
      <section className="space-y-4">
        <div className="flex flex-col gap-4 sm:items-start lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Usuários</p>
            <h2 className="mt-2 text-display font-semibold text-white">Cadastro e vínculo de acesso</h2>
          </div>

          <div className="flex items-center gap-3">
            <Botao variante="primario" onClick={handleNew} data-testid="usuario-novo">
              Novo
            </Botao>
          </div>
        </div>

        <Indicadores
          itens={[
            { rotulo: 'Total', valor: usuarios.length },
            { rotulo: 'Ativos', valor: totalAtivos },
            { rotulo: 'Profissionais', valor: totalProfissionais },
          ]}
        />
      </section>
      ) : null}

      {isFormRoute ? (
        <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
          <form onSubmit={handleSubmit} className="space-y-4" data-testid="usuario-form">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="text-subtitulo font-semibold text-white">
                  {editingId ? 'Editar usuário' : 'Novo usuário'}
                </h3>
              </div>
              {editingId ? (
                <span className="rounded-full border border-cyan-300/20 bg-cyan-400/10 px-3 py-1 text-meta font-semibold text-cyan-100">
                  Editando #{editingId}
                </span>
              ) : null}
            </div>

            {rolesQuery.isLoading || profissionaisQuery.isLoading ? (
              <div className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1 text-corpo text-slate-300">
                Carregando dados de referência...
              </div>
            ) : null}

            {rolesQuery.isError ? (
              <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-corpo text-rose-100">
                Não foi possível carregar os papéis do tenant.
              </div>
            ) : null}

            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Nome</span>
              <input
                value={form.name}
                onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))}
                className={selectClasses()}
                data-testid="usuario-nome"
              />
            </label>

            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">E-mail</span>
              <input
                type="email"
                value={form.email}
                onChange={(event) => setForm((current) => ({ ...current, email: event.target.value }))}
                className={selectClasses()}
                data-testid="usuario-email"
              />
            </label>

            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">
                {editingId ? 'Senha nova' : 'Senha'}
              </span>
              <input
                type="password"
                value={form.password}
                onChange={(event) =>
                  setForm((current) => ({ ...current, password: event.target.value }))
                }
                className={selectClasses()}
                placeholder={editingId ? 'Deixe em branco para manter a senha atual' : 'Senha inicial'}
                data-testid="usuario-senha"
              />
            </label>

            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Papel</span>
              <Select
                value={form.role}
                onChange={(event) => handleRoleChange(event.target.value)}
                className={selectClasses()}
                data-testid="usuario-role"
                disabled={rolesQuery.isLoading || roles.length === 0}
              >
                <option value="" disabled>
                  Selecione
                </option>
                {roles.map((role) => (
                  <option key={role.id} value={role.name}>
                    {role.name}
                  </option>
                ))}
              </Select>
            </label>

            {form.role === 'profissional' ? (
              <label className="block space-y-2">
                <span className="text-corpo font-medium text-slate-200">Profissional vinculado</span>
                <Select
                  value={form.profissional_id}
                  onChange={(event) =>
                    setForm((current) => ({ ...current, profissional_id: event.target.value }))
                  }
                  className={selectClasses()}
                  data-testid="usuario-profissional"
                  disabled={profissionaisQuery.isLoading || profissionais.length === 0}
                >
                  <option value="" disabled>
                    Selecione
                  </option>
                  {profissionais.map((profissional) => (
                    <option key={profissional.id} value={profissional.id}>
                      {profissional.nome}
                      {profissional.especialidade?.nome ? ` · ${profissional.especialidade.nome}` : ''}
                    </option>
                  ))}
                </Select>
              </label>
            ) : null}

            <label className="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
              <input
                type="checkbox"
                checked={form.ativo}
                onChange={(event) =>
                  setForm((current) => ({ ...current, ativo: event.target.checked }))
                }
                className="h-4 w-4 rounded border-white/20 bg-white/10 text-cyan-300 focus:ring-cyan-300/20"
                data-testid="usuario-ativo"
              />
              <span className="text-corpo font-medium text-slate-200">Ativo</span>
            </label>

            {formError ? (
              <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
                {formError}
              </p>
            ) : null}

            <div className="flex gap-3">
              <Botao
                type="submit"
                variante="primario"
                className="flex-1"
                disabled={
                  criarUsuario.isPending ||
                  atualizarUsuario.isPending ||
                  !formIsComplete ||
                  rolesQuery.isLoading ||
                  profissionaisQuery.isLoading
                }
                data-testid="usuario-submit"
              >
                {editingId
                  ? atualizarUsuario.isPending
                    ? 'Salvando...'
                    : 'Salvar alterações'
                  : criarUsuario.isPending
                    ? 'Salvando...'
                    : 'Criar usuário'}
              </Botao>
              <Botao
                type="button"
                variante="secundario"
                onClick={handleCancel}
                data-testid={editingId ? 'usuario-cancelar' : 'usuario-fechar'}
              >
                {editingId ? 'Cancelar' : 'Fechar'}
              </Botao>
            </div>
          </form>
        </section>
      ) : null}

      {!isFormRoute ? (
      <section className="space-y-4 rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
        <div className="flex flex-col gap-4 sm:items-start lg:flex-row lg:items-end lg:justify-between">

          <form className="flex gap-3" onSubmit={handleFilterSubmit}>
            <label className="min-w-56 space-y-2">
              <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Busca</span>
              <input
                value={draftFilters.busca}
                onChange={(event) =>
                  setDraftFilters((current) => ({ ...current, busca: event.target.value }))
                }
                className={selectClasses()}
                placeholder="Nome ou e-mail"
                data-testid="usuario-busca"
              />
            </label>
            <Botao type="submit" variante="secundario" data-testid="usuario-busca-submit">
              Filtrar
            </Botao>
          </form>
        </div>

        {usuariosQuery.isLoading ? (
          <div className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1 text-corpo text-slate-300">
            Carregando usuários...
          </div>
        ) : usuariosQuery.isError ? (
          <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-corpo text-rose-100">
            Não foi possível carregar os usuários.
          </div>
        ) : (
          <div className="overflow-x-auto rounded-superficie border border-linha">
            <table className="w-full border-collapse text-left text-corpo" data-cartoes="lg">
              <thead className="bg-fundo text-meta uppercase tracking-[0.25em] text-texto-suave">
                <tr>
                  <ColunaOrdenavel
                    titulo="Nome"
                    coluna="nome"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                    className="w-full px-4 py-3"
                  />
                  <ColunaOrdenavel
                    titulo="E-mail"
                    coluna="email"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                  <ColunaOrdenavel titulo="Papel" />
                  <ColunaOrdenavel titulo="Profissional" />
                  <ColunaOrdenavel
                    titulo="Status"
                    coluna="status"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                  <ColunaOrdenavel titulo="Ações" className="w-px px-4 py-3" />
                </tr>
              </thead>
              <tbody className="divide-y divide-linha bg-superficie">
                {usuarios.map((usuario) => (
                  <tr key={usuario.id} data-testid={`usuario-row-${usuario.id}`}>
                    <td data-rotulo="Nome" className="px-4 py-4 text-slate-100">
                      <div className="font-medium">{usuario.name}</div>
                      <div className="text-meta text-slate-400">#{usuario.id}</div>
                    </td>
                    <td data-rotulo="E-mail" className="px-4 py-4 text-slate-200">{usuario.email}</td>
                    <td data-rotulo="Papel" className="px-4 py-4 text-slate-200">{usuario.role}</td>
                    <td data-rotulo="Profissional" className="px-4 py-4 text-slate-200">{usuario.profissional?.nome ?? '-'}</td>
                    <td data-rotulo="Status" className="px-4 py-4">
                      <Badge
                        tone={usuario.ativo ? 'sucesso' : 'perigo'}
                        data-testid={`usuario-status-${usuario.id}`}
                      >
                        {usuario.ativo ? 'Ativo' : 'Inativo'}
                      </Badge>
                    </td>
                    <td data-rotulo="Ações" data-rotulo-bloco className="w-px whitespace-nowrap px-4 py-4">
                      <div className="flex flex-nowrap gap-2">
                        <button
                          type="button"
                          onClick={() => handleEdit(usuario)}
                          className="rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1.5 text-meta font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
                          data-testid={`usuario-editar-${usuario.id}`}
                        >
                          Editar
                        </button>
                        <button
                          type="button"
                          onClick={() =>
                            navigate(`/permissoes/${encodeURIComponent(usuario.role)}/editar`)
                          }
                          className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-meta font-semibold text-white transition hover:bg-white/10"
                          data-testid={`usuario-permissoes-${usuario.id}`}
                        >
                          Permissões
                        </button>
                        <button
                          type="button"
                          onClick={() => handleToggleAtivo(usuario)}
                          className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-meta font-semibold text-white transition hover:bg-white/10 disabled:opacity-60"
                          disabled={atualizarUsuario.isPending}
                          data-testid={`usuario-toggle-${usuario.id}`}
                        >
                          {usuario.ativo ? 'Desativar' : 'Ativar'}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
                {usuarios.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-slate-300">
                      Nenhum usuário encontrado.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        )}

        <div className="flex items-center justify-between gap-3">
          <Botao
            type="button"
            variante="secundario"
            tamanho="sm"
            onClick={() => setPage((current) => Math.max(1, current - 1))}
            disabled={page <= 1 || usuariosQuery.isFetching}
          >
            Anterior
          </Botao>

          <p className="inline-flex min-h-6 items-center text-corpo text-slate-300">
            Página {page} de {totalPages}
          </p>

          <Botao
            type="button"
            variante="secundario"
            tamanho="sm"
            onClick={() => setPage((current) => Math.min(totalPages, current + 1))}
            disabled={page >= totalPages || usuariosQuery.isFetching}
          >
            Próxima
          </Botao>
        </div>
      </section>
      ) : null}
    </div>
  )
}
