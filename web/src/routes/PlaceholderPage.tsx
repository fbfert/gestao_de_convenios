type PlaceholderPageProps = {
  title: string
  description: string
}

export function PlaceholderPage({ title, description }: PlaceholderPageProps) {
  return (
    <div className="space-y-4">
      <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">
        Em construção
      </p>
      <h2 className="text-3xl font-semibold text-white">{title}</h2>
      <p className="max-w-2xl text-sm leading-6 text-slate-300">{description}</p>
    </div>
  )
}
