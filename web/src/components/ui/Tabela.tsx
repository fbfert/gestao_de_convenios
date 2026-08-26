import { createContext, useContext } from 'react'
import type { HTMLAttributes, TableHTMLAttributes, TdHTMLAttributes, ThHTMLAttributes } from 'react'

import { cn } from '../../lib/cn'

interface ConfiguracaoTabela {
  densidade: 'compacta' | 'confortavel'
  cabecalhoFixo: boolean
}

const ContextoTabela = createContext<ConfiguracaoTabela>({ densidade: 'confortavel', cabecalhoFixo: false })

export interface TabelaProps extends TableHTMLAttributes<HTMLTableElement> {
  /** compacta: linha 36px (grades/tabelas grandes) · confortavel: 48px */
  densidade?: 'compacta' | 'confortavel'
  /** Cabeçalho sticky em z-(--z-fixo) */
  cabecalhoFixo?: boolean
  /** <caption class="sr-only"> — total/página para leitor de tela */
  legenda: string
  /**
   * Ponto em que a tabela vira cartão: `md` abaixo de 48rem (até 5 colunas),
   * `lg` abaixo de 64rem (6+). `nenhum` mantém a rolagem lateral do wrapper.
   * Ver o bloco "Tabela em modo cartão" no index.css.
   */
  cartoes?: 'md' | 'lg' | 'nenhum'
}

/**
 * Tabela do design system — cópia adaptada de componentes/ui/tabela.tsx do
 * clinica, portada em 20/08/2026. O wrapper tem scroll próprio (a página
 * nunca rola horizontal); células numéricas usam `numerica`.
 *
 * Ainda não adotada pelas 21 tabelas existentes do gescon (continuam em
 * <table> puro, com `data-cartoes`/`data-rotulo` aplicados direto). Fica
 * disponível pra quem for escrever tabela nova, ou pra migração futura.
 *
 * Nasce com modo cartão ligado (`cartoes="lg"`): o componente é o caminho
 * recomendado, e seria uma armadilha ele ser o único que não vira cartão em
 * tela estreita.
 */
function TabelaRaiz({
  densidade = 'confortavel',
  cabecalhoFixo = false,
  legenda,
  cartoes = 'lg',
  className,
  children,
  ...props
}: TabelaProps) {
  return (
    <ContextoTabela.Provider value={{ densidade, cabecalhoFixo }}>
      <div className="w-full overflow-x-auto rounded-superficie border border-linha-forte bg-superficie">
        <table
          data-cartoes={cartoes === 'nenhum' ? undefined : cartoes}
          className={cn('w-full border-collapse text-corpo text-texto', className)}
          {...props}
        >
          <caption className="sr-only">{legenda}</caption>
          {children}
        </table>
      </div>
    </ContextoTabela.Provider>
  )
}

function Cabecalho({ className, ...props }: HTMLAttributes<HTMLTableSectionElement>) {
  return <thead className={className} {...props} />
}

function Corpo({ className, ...props }: HTMLAttributes<HTMLTableSectionElement>) {
  return <tbody className={cn('[content-visibility:auto]', className)} {...props} />
}

export interface TabelaLinhaProps extends HTMLAttributes<HTMLTableRowElement> {
  /** bg-selecao + aria-selected */
  selecionada?: boolean
}

function Linha({ selecionada = false, className, ...props }: TabelaLinhaProps) {
  const { densidade } = useContext(ContextoTabela)
  return (
    <tr
      aria-selected={selecionada || undefined}
      className={cn(
        'border-b border-linha transition-colors duration-(--duracao-1)',
        densidade === 'compacta' ? 'h-9' : 'h-12',
        selecionada ? 'bg-selecao' : 'hover:bg-fundo',
        className,
      )}
      {...props}
    />
  )
}

export interface TabelaCelulaProps extends TdHTMLAttributes<HTMLTableCellElement> {
  /** Célula de dado numérico: tabular-nums + alinhamento à direita */
  numerica?: boolean
  /**
   * Nome da coluna, exibido ao lado do valor quando a tabela vira cartão. CSS
   * não consegue ler o texto do `<th>` correspondente, então ele vem daqui.
   * Célula sem rótulo (ações, seleção) ocupa a linha inteira do cartão.
   */
  rotulo?: string
  /** Rótulo em cima e valor embaixo — para texto corrido ou barra de botões. */
  rotuloEmBloco?: boolean
}

function Celula({ numerica = false, rotulo, rotuloEmBloco = false, className, ...props }: TabelaCelulaProps) {
  const { densidade } = useContext(ContextoTabela)
  return (
    <td
      data-rotulo={rotulo}
      data-rotulo-bloco={rotuloEmBloco ? '' : undefined}
      className={cn(
        densidade === 'compacta' ? 'px-3 py-2' : 'px-4 py-3',
        numerica && 'text-right tabular-nums',
        className,
      )}
      {...props}
    />
  )
}

function CelulaCabecalho({ className, ...props }: ThHTMLAttributes<HTMLTableCellElement>) {
  const { densidade, cabecalhoFixo } = useContext(ContextoTabela)
  return (
    <th
      scope="col"
      className={cn(
        'border-b border-linha-forte bg-fundo text-left text-meta font-medium uppercase tracking-[0.25em] text-texto-suave',
        densidade === 'compacta' ? 'px-3 py-2' : 'px-4 py-3',
        cabecalhoFixo && 'sticky top-0 z-(--z-fixo)',
        className,
      )}
      {...props}
    />
  )
}

export const Tabela = Object.assign(TabelaRaiz, { Cabecalho, Corpo, Linha, Celula, CelulaCabecalho })
