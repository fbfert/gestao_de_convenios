type PlaceholderPageProps = {
  title: string
  description: string
}

export function PlaceholderPage({ title, description }: PlaceholderPageProps) {
  return (
    <div className="space-y-4">
      <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">
        Em construção
      </p>
      <h2 className="text-display font-semibold text-white">{title}</h2>
      <p className="max-w-2xl text-corpo leading-6 text-slate-300">{description}</p>
    </div>
  )
}
