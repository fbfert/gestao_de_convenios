import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { Botao } from '../../components/ui/Botao'
import { formatar } from './formato'
import type { ColunaDeTabela, LinhaDeTabela, TabelaDeRelatorio } from './tipos'

/**
 * Uma tabela do relatório: ordenável no clique e exportável em CSV ou XLSX.
 *
 * A ordenação é em memória, ao contrário das listagens do sistema. O motivo é o
 * tamanho: uma tabela de relatório é agregada — dezenas de linhas, não milhares
 * —, já veio inteira na resposta, e ordenar no servidor custaria uma consulta a
 * cada clique de cabeçalho. Ausência (`null`) vai sempre para o fim, nos dois
 * sentidos: travessão no topo de uma coluna ordenada por valor não é
 * informação, é ruído.
 */
export function TabelaRelatorio({
  tabela,
  onExportar,
  exportando = false,
}: {
  tabela: TabelaDeRelatorio
  onExportar: (formato: 'csv' | 'xlsx') => void
  exportando?: boolean
}) {
  const [ordenacao, setOrdenacao] = useState<{ coluna: string; direcao: 'asc' | 'desc' } | null>(null)

  const linhas = useMemo(() => ordenar(tabela.linhas, ordenacao), [tabela.linhas, ordenacao])

  const alternar = (coluna: string) => {
    setOrdenacao((atual) =>
      atual?.coluna === coluna
        ? { coluna, direcao: atual.direcao === 'asc' ? 'desc' : 'asc' }
        : // Primeiro clique em coluna numérica desce: quem ordena "por valor"
          // quer ver o maior, não o menor.
          { coluna, direcao: ehNumerica(tabela.colunas, coluna) ? 'desc' : 'asc' },
    )
  }

  return (
    <section
      className="rounded-janela border border-linha bg-superficie shadow-e1"
      data-testid={`tabela-${tabela.key}`}
    >
      <header className="flex flex-wrap items-center justify-between gap-3 border-b border-linha px-4 py-3">
        <h3 className="text-subtitulo font-semibold text-texto">{tabela.label}</h3>

        <div className="flex items-center gap-2">
          <Botao
            variante="secundario"
            tamanho="sm"
            onClick={() => onExportar('csv')}
            disabled={exportando || linhas.length === 0}
            data-testid={`exportar-${tabela.key}-csv`}
          >
            Exportar CSV
          </Botao>
          <Botao
            variante="secundario"
            tamanho="sm"
            onClick={() => onExportar('xlsx')}
            disabled={exportando || linhas.length === 0}
            data-testid={`exportar-${tabela.key}-xlsx`}
          >
            Exportar XLSX
          </Botao>
        </div>
      </header>

      {linhas.length === 0 ? (
        <p className="px-4 py-8 text-center text-corpo text-texto-suave">Sem dados no período</p>
      ) : (
        // Rolagem no wrapper, e não na página: em tela estreita a tabela rola
        // sozinha e o resto do relatório fica parado.
        <div className="overflow-x-auto">
          <table className="w-full text-corpo">
            <caption className="sr-only">
              {tabela.label} — {linhas.length} linha(s)
            </caption>
            <thead>
              <tr className="text-meta uppercase tracking-[0.2em] text-texto-suave">
                {tabela.colunas.map((coluna) => (
                  <th key={coluna.key} className={`px-4 py-3 ${alinhamento(coluna)}`}>
                    <button
                      type="button"
                      onClick={() => alternar(coluna.key)}
                      className={`-my-3 flex w-full items-center gap-1 py-3 transition hover:text-texto ${
                        coluna.formato === 'texto' ? 'text-left' : 'justify-end'
                      }`}
                      data-testid={`ordenar-${tabela.key}-${coluna.key}`}
                    >
                      {coluna.label}
                      <Marcador ativa={ordenacao?.coluna === coluna.key} direcao={ordenacao?.direcao} />
                    </button>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-linha">
              {linhas.map((linha, indice) => (
                <tr key={indice}>
                  {tabela.colunas.map((coluna) => (
                    <td
                      key={coluna.key}
                      className={`px-4 py-3 ${alinhamento(coluna)} ${
                        coluna.formato === 'texto' ? 'text-texto' : 'tabular-nums text-texto'
                      }`}
                    >
                      <Celula linha={linha} coluna={coluna} />
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}

/**
 * A célula. Quando a linha traz `href`, a coluna de texto vira link para a
 * listagem já recortada — é o caminho de "vi o número, quero ver as linhas".
 */
function Celula({ linha, coluna }: { linha: LinhaDeTabela; coluna: ColunaDeTabela }) {
  const texto = formatar(linha[coluna.key], coluna.formato)
  const destino = linha.href

  if (coluna.formato === 'texto' && typeof destino === 'string') {
    return (
      <Link to={destino} className="text-acento underline underline-offset-2 hover:text-acento-intenso">
        {texto}
      </Link>
    )
  }

  return <>{texto}</>
}

function Marcador({ ativa, direcao }: { ativa: boolean; direcao?: 'asc' | 'desc' }) {
  return (
    <span className={ativa ? 'text-acento' : 'text-texto-desativado'} aria-hidden="true">
      {ativa ? (direcao === 'asc' ? '▲' : '▼') : '↕'}
    </span>
  )
}

function alinhamento(coluna: ColunaDeTabela): string {
  return coluna.formato === 'texto' ? 'text-left' : 'text-right'
}

function ehNumerica(colunas: ColunaDeTabela[], chave: string): boolean {
  return colunas.find((coluna) => coluna.key === chave)?.formato !== 'texto'
}

function ordenar(
  linhas: LinhaDeTabela[],
  ordenacao: { coluna: string; direcao: 'asc' | 'desc' } | null,
): LinhaDeTabela[] {
  if (!ordenacao) {
    return linhas
  }

  const sinal = ordenacao.direcao === 'asc' ? 1 : -1

  return [...linhas].sort((a, b) => {
    const x = a[ordenacao.coluna]
    const y = b[ordenacao.coluna]

    // Ausente sempre no fim, independente do sentido.
    if (x === null || x === undefined) return 1
    if (y === null || y === undefined) return -1

    if (typeof x === 'number' && typeof y === 'number') {
      return (x - y) * sinal
    }

    return String(x).localeCompare(String(y), 'pt-BR') * sinal
  })
}
