import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { Botao } from '../../components/ui/Botao'
import {
  getHttpErrorMessage,
  useAtualizarLancamentoPrintTemplate,
  useLancamentoPrintTemplate,
} from './useLancamentos'
import {
  asPreviewDocument,
  renderLancamentoPrintTemplate,
  sampleTemplateData,
} from './printTemplate'
import type { LancamentoPrintTemplateForm } from './types'

const placeholders = [
  '{{guia_numero}}',
  '{{clinica}}',
  '{{paciente}}',
  '{{numero_cartao}}',
  '{{profissional_executante}}',
  '{{terapia_aplicada}}',
  '{{data_impressao}}',
  '{{#sessoes}}...{{/sessoes}}',
  '{{numero}}',
  '{{data_sessao}}',
  '{{hora_inicio}}',
  '{{hora_fim}}',
  '{{acompanhante}}',
  '{{resumo_atividades}}',
]

const emptyForm: LancamentoPrintTemplateForm = {
  nome: '',
  html: '',
  ativo: true,
}

function inputClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

export function LancamentoTemplatesPage() {
  const navigate = useNavigate()
  const templateQuery = useLancamentoPrintTemplate()
  const atualizarTemplate = useAtualizarLancamentoPrintTemplate()
  const [form, setForm] = useState<LancamentoPrintTemplateForm>(emptyForm)
  const [error, setError] = useState<string | null>(null)
  const [saved, setSaved] = useState(false)

  useEffect(() => {
    if (!templateQuery.data) {
      return
    }

    setForm({
      nome: templateQuery.data.nome,
      html: templateQuery.data.html,
      ativo: templateQuery.data.ativo,
    })
  }, [templateQuery.data])

  const previewHtml = useMemo(
    () => asPreviewDocument(renderLancamentoPrintTemplate(form.html, sampleTemplateData)),
    [form.html],
  )

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setError(null)
    setSaved(false)

    try {
      const template = await atualizarTemplate.mutateAsync(form)
      setForm({
        nome: template.nome,
        html: template.html,
        ativo: template.ativo,
      })
      setSaved(true)
    } catch (updateError) {
      setError(getHttpErrorMessage(updateError, 'Não foi possível salvar o template.'))
    }
  }

  return (
    <div className="space-y-8" data-testid="lancamento-templates-page">
      <section className="space-y-4">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Sessões</p>
            <h2 className="mt-2 text-3xl font-semibold text-white">Templates de impressão</h2>
          </div>
          <Botao variante="secundario" onClick={() => navigate('/lancamentos')}>
            Voltar
          </Botao>
        </div>
      </section>

      {templateQuery.isLoading ? (
        <div className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
          Carregando template...
        </div>
      ) : templateQuery.isError ? (
        <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-sm text-rose-100">
          {getHttpErrorMessage(templateQuery.error, 'Não foi possível carregar o template.')}
        </div>
      ) : (
        <section className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
          <form
            onSubmit={handleSubmit}
            className="space-y-4 rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6"
          >
            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Nome do template</span>
              <input
                value={form.nome}
                onChange={(event) => setForm((current) => ({ ...current, nome: event.target.value }))}
                className={inputClasses()}
                data-testid="lancamento-template-nome"
              />
            </label>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">HTML</span>
              <textarea
                value={form.html}
                onChange={(event) => setForm((current) => ({ ...current, html: event.target.value }))}
                className={`${inputClasses()} min-h-[560px] font-mono text-sm leading-6`}
                spellCheck={false}
                data-testid="lancamento-template-html"
              />
            </label>

            <label className="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-200">
              <input
                type="checkbox"
                checked={form.ativo}
                onChange={(event) => setForm((current) => ({ ...current, ativo: event.target.checked }))}
                className="h-4 w-4 rounded border-white/20 bg-slate-950 text-cyan-400"
              />
              Template ativo
            </label>

            {error ? (
              <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                {error}
              </p>
            ) : null}
            {saved ? (
              <p className="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                Template salvo.
              </p>
            ) : null}

            <Botao
              type="submit"
              variante="primario"
              className="w-full"
              disabled={atualizarTemplate.isPending || form.nome.trim() === '' || form.html.trim() === ''}
              data-testid="lancamento-template-salvar"
            >
              {atualizarTemplate.isPending ? 'Salvando...' : 'Salvar template'}
            </Botao>
          </form>

          <aside className="space-y-4">
            <div className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
              <h3 className="text-lg font-semibold text-white">Placeholders</h3>
              <div className="mt-4 flex flex-wrap gap-2">
                {placeholders.map((placeholder) => (
                  <code
                    key={placeholder}
                    className="rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1.5 text-xs text-cyan-50"
                  >
                    {placeholder}
                  </code>
                ))}
              </div>
            </div>

            <div className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-4">
              <div className="mb-3 flex items-center justify-between gap-3">
                <h3 className="text-lg font-semibold text-white">Preview</h3>
                <span className="text-xs text-slate-400">Dados de exemplo</span>
              </div>
              <iframe
                title="Preview do template"
                srcDoc={previewHtml}
                sandbox=""
                /* Branco literal: o preview imita a folha impressa, entao nao
                   pode acompanhar a inversao de --color-white do tema claro. */
                className="h-[680px] w-full rounded-2xl border border-white/10 bg-[#ffffff]"
                data-testid="lancamento-template-preview"
              />
            </div>
          </aside>
        </section>
      )}
    </div>
  )
}
