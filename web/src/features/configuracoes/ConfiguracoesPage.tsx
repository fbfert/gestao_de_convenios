export function ConfiguracoesPage() {
  return (
    <div className="space-y-6" data-testid="configuracoes-page">
      <section className="space-y-4">
        <div>
          <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Configurações</p>
          <h2 className="mt-2 text-3xl font-semibold text-white">Ajustes do sistema</h2>
          <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
            Esta área está reservada para parâmetros operacionais e personalizações futuras do
            tenant.
          </p>
        </div>

        <div className="grid gap-4 md:grid-cols-2">
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Status</p>
            <p className="mt-2 text-lg font-semibold text-white">Em construção</p>
          </article>
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Escopo</p>
            <p className="mt-2 text-lg font-semibold text-white">Somente rótulo por enquanto</p>
          </article>
        </div>
      </section>
    </div>
  )
}
