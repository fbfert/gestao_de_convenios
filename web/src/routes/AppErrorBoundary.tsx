import { ErrorBoundary, type FallbackProps } from 'react-error-boundary'
import { useLocation, useNavigate } from 'react-router-dom'
import type { ReactNode } from 'react'
import { Botao } from '../components/ui/Botao'
import { codigoDoRelato, reportClientError } from '../lib/reportClientError'

/**
 * A rede que impede a tela branca.
 *
 * Até esta change, qualquer exceção durante uma renderização desmontava o
 * `#root`: a clínica via a página esvaziar no meio de um lançamento, sem
 * mensagem e sem recarregar. E como nada era registrado, quando ela ligava não
 * havia um único log para consultar.
 *
 * Fica DENTRO do `BrowserRouter` de propósito, para que "Voltar ao início"
 * possa chamar `navigate` e recuperar sem recarregar a aplicação inteira —
 * recarregar já é o outro botão.
 *
 * `resetKeys={[pathname]}` rearma o boundary a cada mudança de rota. Sem isso a
 * tela de erro ficaria colada mesmo depois de navegar para outro lugar.
 *
 * **Limite conhecido:** fica abaixo de `QueryClientProvider` e
 * `ConfirmDialogProvider`, então um erro dentro deles ainda derruba o app.
 * Cobri-los exigiria um segundo boundary acima do router, sem acesso a
 * `navigate` — e esses dois são providers estáveis. Quando acontecer, os
 * listeners globais de `main.tsx` ao menos registram.
 */

function TelaDeErro({ error, resetErrorBoundary }: FallbackProps) {
  const navigate = useNavigate()

  /*
   * O código vem de `codigoDoRelato`, e não de um cálculo próprio: ele aplica os
   * mesmos cortes de tamanho que o envio aplica, então o número da tela é
   * exatamente o que o servidor grava. Calculando aqui sobre a pilha inteira, um
   * erro de React com pilha acima de 4000 caracteres mostraria um código que o
   * log não contém.
   */
  const codigo = codigoDoRelato({
    message: error instanceof Error ? error.message : String(error),
    stack: error instanceof Error ? error.stack : null,
  })

  const voltarAoInicio = () => {
    // Rearma antes de navegar: sem isto o boundary continuaria mostrando o erro
    // enquanto a rota nova tenta renderizar por baixo.
    resetErrorBoundary()
    navigate('/dashboard')
  }

  return (
    <div
      className="mx-auto max-w-2xl rounded-janela border border-linha bg-superficie p-8 shadow-e2"
      role="alert"
      data-testid="app-erro"
    >
      <h1 className="text-titulo font-semibold text-texto">Algo deu errado nesta tela</h1>

      <p className="mt-4 text-corpo text-texto-suave">
        A tela parou de funcionar, mas <strong className="text-texto">os dados no servidor não
        foram perdidos</strong> — o que já tinha sido salvo continua salvo.
      </p>

      <p className="mt-3 text-corpo text-texto-suave">
        Tente recarregar a página. Se acontecer de novo, informe o código abaixo ao suporte: é por
        ele que encontramos o que houve.
      </p>

      <p className="mt-4 text-rotulo uppercase tracking-[0.25em] text-texto-suave">Código do erro</p>
      <p className="mt-1 font-mono text-subtitulo font-semibold text-texto" data-testid="app-erro-codigo">
        {codigo}
      </p>

      <div className="mt-6 flex flex-wrap gap-3">
        <Botao variante="primario" onClick={() => window.location.reload()} data-testid="app-erro-recarregar">
          Recarregar a página
        </Botao>
        <Botao variante="secundario" onClick={voltarAoInicio} data-testid="app-erro-inicio">
          Voltar ao início
        </Botao>
      </div>
    </div>
  )
}

export function AppErrorBoundary({ children }: { children: ReactNode }) {
  const { pathname } = useLocation()

  return (
    <ErrorBoundary
      FallbackComponent={TelaDeErro}
      resetKeys={[pathname]}
      onError={(error, info) => {
        reportClientError({
          message: error instanceof Error ? error.message : String(error),
          stack: error instanceof Error ? error.stack : null,
          componentStack: info.componentStack,
        })
      }}
    >
      {children}
    </ErrorBoundary>
  )
}
