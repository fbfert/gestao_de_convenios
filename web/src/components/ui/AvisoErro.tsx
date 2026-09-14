/**
 * Aviso de erro na própria tela, dispensável.
 *
 * Existe para substituir `window.alert` nas ações que falham: a caixa nativa
 * trava a aba inteira até alguém clicar e some sem deixar rastro, então quem
 * fecha perde a mensagem e não tem como reler o motivo da falha.
 */
export function AvisoErro({
  mensagem,
  onFechar,
  testId,
}: {
  mensagem: string | null
  onFechar: () => void
  testId?: string
}) {
  if (!mensagem) {
    return null
  }

  return (
    <div
      role="alert"
      className="flex items-start justify-between gap-4 rounded-janela border border-perigo/30 bg-perigo-suave px-4 py-3 text-corpo text-perigo-texto"
      data-testid={testId ?? 'aviso-erro'}
    >
      <p>{mensagem}</p>
      <button
        type="button"
        onClick={onFechar}
        className="inline-flex min-h-6 shrink-0 items-center font-semibold underline underline-offset-2"
        aria-label="Fechar aviso"
      >
        Fechar
      </button>
    </div>
  )
}
