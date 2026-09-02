import { useState, type FormEvent } from 'react'
import { useAuthStore } from '../../stores/authStore'
import {
  getHttpErrorMessage,
  sugerirSlug,
  tenantVazio,
  useAtualizarTenant,
  useCriarTenant,
  useTenants,
  type Tenant,
  type TenantEdicaoForm,
  type TenantForm,
} from './useTenants'
import { Botao } from '../../components/ui/Botao'

function inputClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-corpo text-white outline-none transition placeholder:text-texto-suave focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

/** `null` = nada aberto; `'nova'` = criando; número = editando aquele id. */
type Edicao = null | 'nova' | number

export function TenantsPage() {
  const usuarioAtual = useAuthStore((state) => state.user)
  const tenantAtual = useAuthStore((state) => state.tenant)
  const tenantsQuery = useTenants()
  const criar = useCriarTenant()
  const atualizar = useAtualizarTenant()

  const [edicao, setEdicao] = useState<Edicao>(null)
  const [form, setForm] = useState<TenantForm>(tenantVazio)
  const [edicaoForm, setEdicaoForm] = useState<TenantEdicaoForm>({
    nome: '',
    slug: '',
    cnpj: '',
    ativo: true,
  })
  const [slugTocado, setSlugTocado] = useState(false)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const tenants = tenantsQuery.data ?? []

  const abrirNova = () => {
    setEdicao('nova')
    setForm(tenantVazio)
    setSlugTocado(false)
    setMessage(null)
    setError(null)
  }

  const abrirEdicao = (tenant: Tenant) => {
    setEdicao(tenant.id)
    setEdicaoForm({ nome: tenant.nome, slug: tenant.slug, cnpj: tenant.cnpj ?? '', ativo: tenant.ativo })
    setMessage(null)
    setError(null)
  }

  const fechar = () => {
    setEdicao(null)
    setForm(tenantVazio)
    setSlugTocado(false)
  }

  // Enquanto o operador não editar o slug à mão, ele acompanha o nome.
  const alterarNome = (nome: string) => {
    setForm((atual) => ({
      ...atual,
      nome,
      slug: slugTocado ? atual.slug : sugerirSlug(nome),
    }))
  }

  const handleCriar = async (event: FormEvent) => {
    event.preventDefault()
    setMessage(null)
    setError(null)

    try {
      const criada = await criar.mutateAsync(form)
      setMessage(
        `Clínica "${criada.nome}" criada com os papéis padrão e o administrador ${form.admin.email}.`,
      )
      fechar()
    } catch (submitError) {
      setError(getHttpErrorMessage(submitError, 'Não foi possível criar a clínica.'))
    }
  }

  const handleAtualizar = async (event: FormEvent, id: number) => {
    event.preventDefault()
    setMessage(null)
    setError(null)

    try {
      await atualizar.mutateAsync({ id, form: edicaoForm })
      setMessage(`Clínica "${edicaoForm.nome}" salva.`)
      fechar()
    } catch (submitError) {
      setError(getHttpErrorMessage(submitError, 'Não foi possível salvar a clínica.'))
    }
  }

  if (!usuarioAtual?.super_admin) {
    // Guarda de cortesia: a rota é alcançável por URL. A restrição de verdade
    // está no middleware `super-admin`, que devolve 403 em toda chamada.
    return (
      <div className="rounded-janela border border-perigo/30 bg-perigo-suave p-6 text-corpo text-perigo-texto">
        Esta área é restrita à administração do sistema.
      </div>
    )
  }

  return (
    <div className="space-y-6" data-testid="tenants-page">
      <section className="space-y-2">
        <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Administração</p>
        <h2 className="text-display font-semibold text-white">Clínicas</h2>
        <p className="max-w-3xl text-corpo leading-6 text-slate-300">
          Cada clínica é um tenant: os dados de uma nunca aparecem na outra. Um usuário pertence a
          uma única clínica — para atender duas, a pessoa precisa de duas contas com e-mails
          diferentes.
        </p>
      </section>

      <div className="flex flex-wrap items-center gap-3">
        <Botao variante="primario" onClick={abrirNova} data-testid="tenant-nova">
          Nova clínica
        </Botao>
        <span className="text-meta text-slate-400">
          {tenants.length} {tenants.length === 1 ? 'clínica cadastrada' : 'clínicas cadastradas'}
        </span>
      </div>

      {message ? (
        <p className="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-corpo text-emerald-100">
          {message}
        </p>
      ) : null}

      {error ? (
        <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
          {error}
        </p>
      ) : null}

      {edicao === 'nova' ? (
        <form
          onSubmit={handleCriar}
          className="rounded-janela border border-acento/30 bg-superficie-elevada shadow-e2 p-6"
          data-testid="tenant-form"
        >
          <h3 className="text-subtitulo font-semibold text-white">Nova clínica</h3>
          <p className="mt-1 text-corpo text-slate-300">
            A clínica nasce com os papéis <strong>admin</strong>, <strong>funcionário</strong> e{' '}
            <strong>profissional</strong> já criados, com as permissões padrão.
          </p>

          <div className="mt-5 grid gap-4 md:grid-cols-2">
            <label className="space-y-2">
              <span className="text-corpo font-medium text-slate-200">Nome</span>
              <input
                value={form.nome}
                onChange={(event) => alterarNome(event.target.value)}
                className={inputClasses()}
                placeholder="Clínica São Jorge"
                required
                data-testid="tenant-nome"
              />
            </label>
            <label className="space-y-2">
              <span className="text-corpo font-medium text-slate-200">Identificador</span>
              <input
                value={form.slug}
                onChange={(event) => {
                  setSlugTocado(true)
                  setForm((atual) => ({ ...atual, slug: event.target.value }))
                }}
                className={inputClasses()}
                placeholder="clinica-sao-jorge"
                required
                data-testid="tenant-slug"
              />
              <span className="block text-meta text-slate-400">
                Minúsculas, números e hífen. Pode ser alterado depois em Editar.
              </span>
            </label>
            <label className="space-y-2">
              <span className="text-corpo font-medium text-slate-200">CNPJ</span>
              <input
                value={form.cnpj}
                onChange={(event) => setForm((atual) => ({ ...atual, cnpj: event.target.value }))}
                className={inputClasses()}
                placeholder="00.000.000/0001-00"
                data-testid="tenant-cnpj"
              />
            </label>
            <label className="flex min-h-6 items-center gap-2 text-corpo font-medium text-slate-200 md:mt-7">
              <input
                type="checkbox"
                checked={form.ativo}
                onChange={(event) => setForm((atual) => ({ ...atual, ativo: event.target.checked }))}
                className="size-4 rounded border-white/20 bg-white/10"
                data-testid="tenant-ativo"
              />
              Clínica ativa
            </label>
          </div>

          <div className="mt-6 rounded-superficie border border-linha bg-fundo p-4 shadow-e1">
            <h4 className="text-corpo font-semibold text-white">Administrador inicial</h4>
            <p className="mt-1 text-meta text-slate-400">
              Obrigatório: sem uma conta, ninguém consegue entrar na clínica nova, e a tela de
              Usuários só cria pessoas na clínica de quem está logado.
            </p>

            <div className="mt-4 grid gap-4 md:grid-cols-3">
              <label className="space-y-2">
                <span className="text-corpo font-medium text-slate-200">Nome</span>
                <input
                  value={form.admin.name}
                  onChange={(event) =>
                    setForm((atual) => ({ ...atual, admin: { ...atual.admin, name: event.target.value } }))
                  }
                  className={inputClasses()}
                  required
                  data-testid="tenant-admin-nome"
                />
              </label>
              <label className="space-y-2">
                <span className="text-corpo font-medium text-slate-200">E-mail</span>
                <input
                  type="email"
                  value={form.admin.email}
                  onChange={(event) =>
                    setForm((atual) => ({ ...atual, admin: { ...atual.admin, email: event.target.value } }))
                  }
                  className={inputClasses()}
                  required
                  data-testid="tenant-admin-email"
                />
                <span className="block text-meta text-slate-400">
                  Único entre todas as clínicas.
                </span>
              </label>
              <label className="space-y-2">
                <span className="text-corpo font-medium text-slate-200">Senha</span>
                <input
                  type="password"
                  value={form.admin.password}
                  onChange={(event) =>
                    setForm((atual) => ({
                      ...atual,
                      admin: { ...atual.admin, password: event.target.value },
                    }))
                  }
                  className={inputClasses()}
                  minLength={8}
                  required
                  data-testid="tenant-admin-senha"
                />
                <span className="block text-meta text-slate-400">Mínimo de 8 caracteres.</span>
              </label>
            </div>
          </div>

          <div className="mt-5 flex flex-wrap gap-3">
            <Botao type="submit" variante="primario" disabled={criar.isPending} data-testid="tenant-salvar">
              {criar.isPending ? 'Criando...' : 'Criar clínica'}
            </Botao>
            <Botao type="button" variante="secundario" onClick={fechar}>
              Cancelar
            </Botao>
          </div>
        </form>
      ) : null}

      {tenantsQuery.isPending ? <p className="text-corpo text-slate-400">Carregando clínicas...</p> : null}

      {tenantsQuery.isError ? (
        <p className="text-corpo text-rose-300">
          {getHttpErrorMessage(tenantsQuery.error, 'Não foi possível carregar as clínicas.')}
        </p>
      ) : null}

      <div className="space-y-4">
        {tenants.map((tenant) => (
          <article
            key={tenant.id}
            className="rounded-superficie border border-linha bg-fundo p-5 shadow-e1"
            data-testid={`tenant-item-${tenant.slug}`}
          >
            {edicao === tenant.id ? (
              <form onSubmit={(event) => void handleAtualizar(event, tenant.id)} className="space-y-4">
                <div className="grid gap-4 md:grid-cols-2">
                  <label className="space-y-2">
                    <span className="text-corpo font-medium text-slate-200">Nome</span>
                    <input
                      value={edicaoForm.nome}
                      onChange={(event) =>
                        setEdicaoForm((atual) => ({ ...atual, nome: event.target.value }))
                      }
                      className={inputClasses()}
                      required
                    />
                  </label>
                  <label className="space-y-2">
                    <span className="text-corpo font-medium text-slate-200">Identificador</span>
                    <input
                      value={edicaoForm.slug}
                      onChange={(event) =>
                        setEdicaoForm((atual) => ({ ...atual, slug: event.target.value }))
                      }
                      className={inputClasses()}
                      required
                      data-testid="tenant-edicao-slug"
                    />
                    <span className="block text-meta text-slate-400">
                      Minúsculas, números e hífen, começando por letra.
                    </span>
                  </label>
                  <label className="space-y-2">
                    <span className="text-corpo font-medium text-slate-200">CNPJ</span>
                    <input
                      value={edicaoForm.cnpj}
                      onChange={(event) =>
                        setEdicaoForm((atual) => ({ ...atual, cnpj: event.target.value }))
                      }
                      className={inputClasses()}
                    />
                  </label>
                </div>

                <label className="inline-flex min-h-6 items-center gap-2 text-corpo font-medium text-slate-200">
                  <input
                    type="checkbox"
                    checked={edicaoForm.ativo}
                    onChange={(event) =>
                      setEdicaoForm((atual) => ({ ...atual, ativo: event.target.checked }))
                    }
                    disabled={tenant.id === tenantAtual?.id}
                    className="size-4 rounded border-white/20 bg-white/10 disabled:opacity-50"
                  />
                  Clínica ativa
                  {tenant.id === tenantAtual?.id ? (
                    <span className="text-meta font-normal text-slate-400">
                      — é a sua própria clínica, não pode ser desativada por aqui
                    </span>
                  ) : null}
                </label>

                <div className="flex flex-wrap gap-3">
                  <Botao type="submit" variante="primario" disabled={atualizar.isPending}>
                    {atualizar.isPending ? 'Salvando...' : 'Salvar'}
                  </Botao>
                  <Botao type="button" variante="secundario" onClick={fechar}>
                    Cancelar
                  </Botao>
                </div>
              </form>
            ) : (
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="text-corpo-lg font-semibold text-white">{tenant.nome}</p>
                    {tenant.id === tenantAtual?.id ? (
                      <span className="rounded-full border border-cyan-300/30 bg-cyan-400/10 px-2 py-0.5 text-meta font-semibold uppercase tracking-wide text-cyan-100">
                        Sua clínica
                      </span>
                    ) : null}
                    {tenant.ativo ? null : (
                      <span className="rounded-full border border-white/15 bg-white/5 px-2 py-0.5 text-meta font-semibold uppercase tracking-wide text-slate-300">
                        Inativa
                      </span>
                    )}
                  </div>
                  <p className="font-mono text-meta text-slate-400">{tenant.slug}</p>
                  <p className="inline-flex min-h-6 items-center text-corpo text-slate-300">{tenant.cnpj || 'CNPJ não informado'}</p>
                  <p className="text-meta text-slate-400">
                    {tenant.usuarios_count}{' '}
                    {tenant.usuarios_count === 1 ? 'usuário' : 'usuários'}
                  </p>
                </div>

                <Botao
                  type="button"
                  variante="secundario"
                  tamanho="sm"
                  onClick={() => abrirEdicao(tenant)}
                  data-testid={`tenant-editar-${tenant.slug}`}
                >
                  Editar
                </Botao>
              </div>
            )}
          </article>
        ))}
      </div>

      <p className="rounded-superficie border border-linha bg-fundo p-5 shadow-e1 text-corpo leading-6 text-slate-300">
        <strong className="text-white">Não há exclusão.</strong> Apagar uma clínica levaria junto
        pacientes, guias e lançamentos, ou os deixaria apontando para um tenant inexistente.
        Desativar já impede o login de todos os seus usuários.
      </p>
    </div>
  )
}
