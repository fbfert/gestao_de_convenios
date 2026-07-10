export function HomePage() {
  return (
    <div className="space-y-4">
      <div className="inline-flex rounded-full border border-cyan-300/20 bg-cyan-400/10 px-3 py-1 text-xs font-medium uppercase tracking-[0.25em] text-cyan-200">
        Base do frontend
      </div>
      <h2 className="text-2xl font-semibold text-white">
        Autenticação funcionando
      </h2>
      <p className="max-w-2xl text-sm leading-6 text-slate-300">
        O shell já está preparado para consumir os domínios da API no bloco
        seguinte. Por enquanto, o sistema só prova o caminho real de login,
        persistência do token e proteção de rota.
      </p>
    </div>
  )
}
