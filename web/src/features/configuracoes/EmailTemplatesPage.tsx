import { useMemo, useState, type FormEvent } from 'react'
import { Indicadores } from '../../components/ui/Indicadores'
import { Link } from 'react-router-dom'
import {
  getHttpErrorMessage,
  useAtualizarEmailTemplate,
  useCriarEmailTemplate,
  useEmailTemplates,
  useExcluirEmailTemplate,
  type EmailTemplateForm,
  type EmailTemplateSettings,
} from './useEmailSettings'

const emptyForm: EmailTemplateForm = {
  chave: '',
  nome: '',
  assunto: '',
  corpo: '',
  ativo: true,
}

function fieldClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function statusTone(ativo: boolean) {
  return ativo
    ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-100'
    : 'border-rose-400/30 bg-rose-400/10 text-rose-100'
}

function toForm(template: EmailTemplateSettings): EmailTemplateForm {
  return {
    chave: template.chave,
    nome: template.nome,
    assunto: template.assunto,
    corpo: template.corpo,
    ativo: template.ativo,
  }
}

export function EmailTemplatesPage() {
  const templatesQuery = useEmailTemplates()
  const criarTemplate = useCriarEmailTemplate()
  const atualizarTemplate = useAtualizarEmailTemplate()
  const excluirTemplate = useExcluirEmailTemplate()
  const [editingId, setEditingId] = useState<number | null>(null)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [form, setForm] = useState<EmailTemplateForm>(emptyForm)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const templates = useMemo(() => templatesQuery.data ?? [], [templatesQuery.data])
  const totalAtivos = useMemo(
    () => templates.filter((template) => template.ativo).length,
    [templates],
  )
  const totalInativos = templates.length - totalAtivos

  const handleNew = () => {
    setEditingId(null)
    setForm(emptyForm)
    setMessage(null)
    setError(null)
    setIsFormOpen(true)
  }

  const handleEdit = (template: EmailTemplateSettings) => {
    if (!template.id) {
      return
    }

    setEditingId(template.id)
    setForm(toForm(template))
    setMessage(null)
    setError(null)
    setIsFormOpen(true)
  }

  const handleCancel = () => {
    setEditingId(null)
    setForm(emptyForm)
    setError(null)
    setIsFormOpen(false)
  }

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setMessage(null)
    setError(null)

    try {
      const payload = {
        ...form,
        chave: form.chave.trim(),
        nome: form.nome.trim(),
        assunto: form.assunto.trim(),
      }

      if (editingId) {
        await atualizarTemplate.mutateAsync({ id: editingId, payload })
        setMessage('Template atualizado.')
      } else {
        await criarTemplate.mutateAsync(payload)
        setMessage('Template criado.')
      }

      setEditingId(null)
      setForm(emptyForm)
      setIsFormOpen(false)
    } catch (submitError) {
      setError(getHttpErrorMessage(submitError, 'Não foi possível salvar o template.'))
    }
  }

  const handleToggleAtivo = async (template: EmailTemplateSettings) => {
    if (!template.id) {
      return
    }

    setMessage(null)
    setError(null)

    try {
      await atualizarTemplate.mutateAsync({
        id: template.id,
        payload: {
          ...toForm(template),
          ativo: !template.ativo,
        },
      })
      setMessage(template.ativo ? 'Template inativado.' : 'Template ativado.')
    } catch (toggleError) {
      setError(getHttpErrorMessage(toggleError, 'Não foi possível atualizar o status do template.'))
    }
  }

  const handleDelete = async (template: EmailTemplateSettings) => {
    if (!template.id || !window.confirm(`Excluir o template "${template.nome}"?`)) {
      return
    }

    setMessage(null)
    setError(null)

    try {
      await excluirTemplate.mutateAsync(template.id)
      if (editingId === template.id) {
        handleCancel()
      }
      setMessage('Template excluído.')
    } catch (deleteError) {
      setError(getHttpErrorMessage(deleteError, 'Não foi possível excluir o template.'))
    }
  }

  return (
    <div className="space-y-8" data-testid="email-templates-page">
      <section className="space-y-4">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Configurações</p>
            <h2 className="mt-2 text-3xl font-semibold text-white">Templates de E-mails</h2>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <Link
              to="/configuracoes"
              className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
            >
              Voltar
            </Link>
            <button
              type="button"
              onClick={handleNew}
              className="rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300"
              data-testid="email-template-novo"
            >
              Novo template
            </button>
          </div>
        </div>

        <Indicadores
          itens={[
            { rotulo: 'Total', valor: templates.length },
            { rotulo: 'Ativos', valor: totalAtivos },
            { rotulo: 'Inativos', valor: totalInativos },
          ]}
        />
      </section>

      {isFormOpen ? (
        <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <form onSubmit={handleSubmit} className="space-y-4" data-testid="email-template-form">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="text-lg font-semibold text-white">
                  {editingId ? 'Editar template' : 'Novo template'}
                </h3>
              </div>
              {editingId ? (
                <span className="rounded-full border border-cyan-300/20 bg-cyan-400/10 px-3 py-1 text-xs font-semibold text-cyan-100">
                  Editando #{editingId}
                </span>
              ) : null}
            </div>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
              <label className="space-y-2">
                <span className="text-sm font-medium text-slate-200">Chave</span>
                <input
                  value={form.chave}
                  onChange={(event) =>
                    setForm((current) => ({ ...current, chave: event.target.value }))
                  }
                  className={fieldClasses()}
                  placeholder="guia_aprovada"
                  required
                  data-testid="email-template-chave"
                />
              </label>
              <label className="space-y-2">
                <span className="text-sm font-medium text-slate-200">Nome</span>
                <input
                  value={form.nome}
                  onChange={(event) =>
                    setForm((current) => ({ ...current, nome: event.target.value }))
                  }
                  className={fieldClasses()}
                  required
                  data-testid="email-template-nome"
                />
              </label>
              <label className="space-y-2 xl:col-span-2">
                <span className="text-sm font-medium text-slate-200">Assunto</span>
                <input
                  value={form.assunto}
                  onChange={(event) =>
                    setForm((current) => ({ ...current, assunto: event.target.value }))
                  }
                  className={fieldClasses()}
                  required
                  data-testid="email-template-assunto"
                />
              </label>
            </div>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Corpo</span>
              <textarea
                value={form.corpo}
                onChange={(event) =>
                  setForm((current) => ({ ...current, corpo: event.target.value }))
                }
                className={`${fieldClasses()} min-h-44 font-mono`}
                required
                data-testid="email-template-corpo"
              />
            </label>

            <label className="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
              <input
                type="checkbox"
                checked={form.ativo}
                onChange={(event) =>
                  setForm((current) => ({ ...current, ativo: event.target.checked }))
                }
                className="h-4 w-4 rounded border-white/20 bg-white/10 text-cyan-300 focus:ring-cyan-300/20"
                data-testid="email-template-ativo"
              />
              <span className="text-sm font-medium text-slate-200">Ativo</span>
            </label>

            {error ? (
              <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                {error}
              </p>
            ) : null}

            <div className="flex gap-3">
              <button
                type="submit"
                className="inline-flex flex-1 items-center justify-center rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60"
                disabled={criarTemplate.isPending || atualizarTemplate.isPending}
                data-testid="email-template-submit"
              >
                {editingId
                  ? atualizarTemplate.isPending
                    ? 'Salvando...'
                    : 'Salvar alterações'
                  : criarTemplate.isPending
                    ? 'Salvando...'
                    : 'Criar template'}
              </button>
              <button
                type="button"
                onClick={handleCancel}
                className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                data-testid="email-template-cancelar"
              >
                Cancelar
              </button>
            </div>
          </form>
        </section>
      ) : null}

      {message ? (
        <p className="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
          {message}
        </p>
      ) : null}

      {!isFormOpen && error ? (
        <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
          {error}
        </p>
      ) : null}

      <section className="space-y-4 rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">

        {templatesQuery.isLoading ? (
          <div className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
            Carregando templates...
          </div>
        ) : templatesQuery.isError ? (
          <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-sm text-rose-100">
            Não foi possível carregar os templates.
          </div>
        ) : (
          <div className="overflow-hidden rounded-3xl border border-white/10">
            <table className="w-full border-collapse text-left text-sm">
              <thead className="bg-white/5 text-xs uppercase tracking-[0.25em] text-slate-400">
                <tr>
                  <th className="px-4 py-3">Nome</th>
                  <th className="px-4 py-3">Assunto</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Ações</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/10 bg-slate-950/30">
                {templates.map((template) => (
                  <tr key={template.id ?? template.chave} data-testid={`email-template-row-${template.id}`}>
                    <td className="px-4 py-4 text-slate-100">
                      <div className="font-medium">{template.nome}</div>
                      <div className="text-xs text-slate-400">{template.chave}</div>
                    </td>
                    <td className="px-4 py-4 text-slate-200">{template.assunto}</td>
                    <td className="px-4 py-4">
                      <span
                        className={`inline-flex rounded-full border px-3 py-1 text-xs font-semibold ${statusTone(
                          template.ativo,
                        )}`}
                      >
                        {template.ativo ? 'Ativo' : 'Inativo'}
                      </span>
                    </td>
                    <td className="px-4 py-4">
                      <div className="flex flex-wrap gap-2">
                        <button
                          type="button"
                          onClick={() => handleEdit(template)}
                          className="rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1.5 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
                          data-testid={`email-template-editar-${template.id}`}
                        >
                          Editar
                        </button>
                        <button
                          type="button"
                          onClick={() => handleToggleAtivo(template)}
                          className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/10 disabled:opacity-60"
                          disabled={atualizarTemplate.isPending}
                          data-testid={`email-template-toggle-${template.id}`}
                        >
                          {template.ativo ? 'Inativar' : 'Ativar'}
                        </button>
                        <button
                          type="button"
                          onClick={() => void handleDelete(template)}
                          className="rounded-full border border-rose-300/30 bg-rose-400/10 px-3 py-1.5 text-xs font-semibold text-rose-100 transition hover:bg-rose-400/20 disabled:opacity-60"
                          disabled={excluirTemplate.isPending}
                          data-testid={`email-template-excluir-${template.id}`}
                        >
                          Excluir
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
                {templates.length === 0 ? (
                  <tr>
                    <td colSpan={4} className="px-4 py-8 text-center text-slate-300">
                      Nenhum template cadastrado.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  )
}
