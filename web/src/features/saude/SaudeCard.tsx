import { useSaudeComponentes } from './useSaude'
import type { ComponenteSaude, EstadoSaude } from './types'

/**
 * Card de saúde do sistema no Dashboard.
 *
 * Saúde não é alerta: alerta é acumulado e pede ação de quem opera ("10 negadas
 * pendentes"); saúde é estado agora e não tem ação do lado da clínica ("o worker
 * respondeu há 2 minutos"). Por isso é um card separado, e por isso ele não
 * oferece botão nenhum.
 *
 * Sem "Reiniciar": um botão de reinício falha justamente quando seria
 * necessário — quando o componente está morto — e empurra execução remota de
 * comando para a mão de usuário de clínica. A recuperação é do supervisor de
 * container (ADR-25).
 */

/**
 * Cor NUNCA é o único sinal. O tema de alto contraste existe por requisito de
 * acessibilidade de um profissional com deficiência de visão de cor (ADR-23),
 * então cada estado carrega também um rótulo em texto e um glifo. Os pares
 * `bg-*-suave` ainda ganham borda automática naquele tema (index.css §
 * data-theme='contraste'), o que dá contorno ao ponto de graça.
 */
const ESTADOS: Record<EstadoSaude, { rotulo: string; glifo: string; ponto: string; texto: string }> = {
  healthy: {
    rotulo: 'Respondendo',
    glifo: '✓',
    ponto: 'bg-sucesso-suave text-sucesso-texto',
    texto: 'text-sucesso-texto',
  },
  warning: {
    rotulo: 'Atrasado',
    glifo: '!',
    ponto: 'bg-alerta-suave text-alerta-texto',
    texto: 'text-alerta-texto',
  },
  down: {
    rotulo: 'Sem resposta',
    glifo: '×',
    ponto: 'bg-perigo-suave text-perigo-texto',
    texto: 'text-perigo-texto',
  },
}

function horaDe(iso: string) {
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso))
}

function tempoDesde(iso: string) {
  const segundos = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000))

  if (segundos < 60) return `${segundos} s`
  const minutos = Math.round(segundos / 60)
  if (minutos < 60) return `${minutos} min`
  const horas = Math.round(minutos / 60)
  if (horas < 24) return `${horas} h`

  return `${Math.round(horas / 24)} d`
}

/** A frase da direita muda com o estado: quem está fora precisa do desde quando. */
function detalheDe(componente: ComponenteSaude) {
  if (!componente.ultimo_heartbeat_em) {
    return 'nunca respondeu'
  }

  if (componente.estado === 'down') {
    return `sem resposta desde ${horaDe(componente.ultimo_heartbeat_em)}`
  }

  return `respondeu há ${tempoDesde(componente.ultimo_heartbeat_em)}`
}

function PontoDeEstado({ estado }: { estado: EstadoSaude }) {
  const { glifo, ponto } = ESTADOS[estado]

  return (
    <span
      aria-hidden="true"
      className={`inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-meta font-semibold ${ponto}`}
    >
      {glifo}
    </span>
  )
}

export function SaudeCard() {
  const saudeQuery = useSaudeComponentes()

  // Enquanto carrega, nada: um card que pisca a cada 30s de polling seria ruído
  // permanente numa tela que as pessoas deixam aberta.
  if (saudeQuery.isLoading) {
    return null
  }

  if (saudeQuery.isError) {
    return (
      <section className="rounded-janela border border-linha bg-superficie p-5 shadow-e1" data-testid="saude-card">
        <p className="text-corpo text-texto-suave">Não foi possível ler a saúde do sistema agora.</p>
      </section>
    )
  }

  const componentes = saudeQuery.data ?? []

  // Some só quando o tenant não tem componente nenhum. Com componentes, o card
  // fica — inclusive com tudo verde, porque "não apareceu nada" e "está tudo
  // bem" precisam ser distinguíveis.
  if (componentes.length === 0) {
    return null
  }

  const comProblema = componentes.filter((c) => c.estado !== 'healthy')
  const tudoSaudavel = comProblema.length === 0

  return (
    <section
      className="rounded-janela border border-linha bg-superficie p-5 shadow-e1"
      aria-label="Saúde do sistema"
      data-testid="saude-card"
      data-estado={tudoSaudavel ? 'saudavel' : 'com-problema'}
    >
      <p className="text-meta font-semibold uppercase tracking-[0.2em] text-acento">Saúde do sistema</p>

      {tudoSaudavel ? (
        // Discreto de propósito: uma linha. Ocupar meia tela para dizer "está
        // tudo bem" é o caminho mais curto para as pessoas pararem de olhar.
        <div className="mt-3 flex items-center gap-3" data-testid="saude-resumo">
          <PontoDeEstado estado="healthy" />
          <p className="text-corpo text-texto">
            {componentes.length === 1
              ? '1 componente respondendo normalmente'
              : `${componentes.length} componentes respondendo normalmente`}
          </p>
        </div>
      ) : (
        <ul className="mt-3 space-y-2">
          {componentes.map((componente) => (
            <li
              key={componente.id}
              className="flex flex-wrap items-center justify-between gap-3 rounded-superficie border border-linha bg-fundo px-4 py-3"
              data-testid={`saude-componente-${componente.chave}`}
            >
              <div className="flex min-w-0 items-center gap-3">
                <PontoDeEstado estado={componente.estado} />
                <div className="min-w-0">
                  <p className="truncate text-corpo font-medium text-texto">{componente.nome}</p>
                  {/* O rótulo em texto é o que sobrevive ao daltonismo: sem ele,
                      "Respondendo" e "Sem resposta" seriam só duas cores. */}
                  <p className={`text-meta font-semibold ${ESTADOS[componente.estado].texto}`}>
                    {ESTADOS[componente.estado].rotulo}
                  </p>
                </div>
              </div>
              <p className="text-meta text-texto-suave">{detalheDe(componente)}</p>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
