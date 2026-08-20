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
}

/**
 * Tabela do design system — cópia adaptada de componentes/ui/tabela.tsx do
 * clinica, portada em 20/08/2026. O wrapper tem scroll próprio (a página
 * nunca rola horizontal); células numéricas usam `numerica`.
 *
 * Ainda não adotada pelas ~18 tabelas existentes do gescon (continuam em
 * <table> puro, só com as classes semânticas trocadas — ver index.css @layer
 * base). Fica disponível pra quem for escrever tabela nova, ou pra migração
 * futura das existentes.
 */
function TabelaRaiz({
  densidade = 'confortavel',
  cabecalhoFixo = false,
  legenda,
  className,
  children,
  ...props
}: TabelaProps) {
  return (
    <ContextoTabela.Provider value={{ densidade, cabecalhoFixo }}>
      <div className="w-full overflow-x-auto rounded-superficie border border-linha-forte bg-superficie">
        <table className={cn('w-full border-collapse text-sm text-texto', className)} {...props}>
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
}

function Celula({ numerica = false, className, ...props }: TabelaCelulaProps) {
  const { densidade } = useContext(ContextoTabela)
  return (
    <td
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
        'border-b border-linha-forte bg-fundo text-left text-xs font-medium uppercase tracking-[0.25em] text-texto-suave',
        densidade === 'compacta' ? 'px-3 py-2' : 'px-4 py-3',
        cabecalhoFixo && 'sticky top-0 z-(--z-fixo)',
        className,
      )}
      {...props}
    />
  )
}

export const Tabela = Object.assign(TabelaRaiz, { Cabecalho, Corpo, Linha, Celula, CelulaCabecalho })
