import { CalendarDays, NotepadText, Paperclip, Stethoscope } from 'lucide-react'
import type { ReactNode } from 'react'
import { Tooltip } from '../../components/ui/Tooltip'
import { DOCUMENTO_LABELS } from '../../lib/documentoTipos'
import { formatarData, formatarDataHora } from './datas'
import type { Solicitacao } from './types'

/**
 * Os quatro indicadores de apoio da linha: datas, observações, anexos e CID.
 *
 * Todos os dados vêm do que a listagem JÁ carrega — `cidCadastros`,
 * `documentos.arquivo` e `itens.documentos.arquivo` estão no eager load de
 * `SolicitacaoService::listar()`. Esta coluna não adiciona nenhuma consulta, e
 * há teste travando isso.
 */


/**
 * Um indicador. Sem conteúdo ele NÃO some: fica visível e apagado.
 *
 * Posição fixa é o que torna a coluna legível de relance — ícone que aparece e
 * some obriga a reler a linha inteira para saber o que está faltando.
 *
 * E, sem conteúdo, ele sai do caminho do teclado (`aria-hidden`, sem foco):
 * quatro gatilhos focáveis por linha, em quinze linhas, seriam sessenta paradas
 * de Tab entre a tabela e a paginação — e um ícone vazio não tem o que anunciar
 * a um leitor de tela.
 */
function Indicador({
  temConteudo,
  rotulo,
  icone,
  children,
}: {
  temConteudo: boolean
  rotulo: string
  icone: ReactNode
  children: ReactNode
}) {
  if (!temConteudo) {
    return (
      <span aria-hidden="true" className="inline-flex text-texto-suave/30">
        {icone}
      </span>
    )
  }

  return (
    <Tooltip rotulo={rotulo} icone={icone}>
      {children}
    </Tooltip>
  )
}

export function SolicitacaoInfoCelula({ solicitacao }: { solicitacao: Solicitacao }) {
  const anexos = [
    ...(solicitacao.documentos ?? []),
    ...(solicitacao.itens ?? []).flatMap((item) => item.documentos ?? []),
  ]
  const cids = solicitacao.cids ?? []
  const temObservacoes = Boolean(solicitacao.observacoes?.trim())

  const icone = 'h-4 w-4'

  return (
    <div className="flex items-center gap-2">
      <Indicador temConteudo rotulo="Datas" icone={<CalendarDays className={icone} />}>
        {/* As TRÊS datas: `solicitado_em` é a data do pedido médico e
            `created_at` é quando alguém digitou no sistema. Sem as duas, ninguém
            sabe há quanto tempo o pedido está parado DENTRO do sistema. */}
        Solicitada em {formatarData(solicitacao.solicitado_em)} · cadastrada em{' '}
        {formatarDataHora(solicitacao.created_at ?? null)} · atualizada em{' '}
        {formatarDataHora(solicitacao.updated_at ?? null)}
      </Indicador>

      <Indicador
        temConteudo={temObservacoes}
        rotulo="Observações"
        icone={<NotepadText className={icone} />}
      >
        {solicitacao.observacoes}
      </Indicador>

      <Indicador
        temConteudo={anexos.length > 0}
        rotulo="Anexos"
        icone={<Paperclip className={icone} />}
      >
        {anexos
          .map((documento) => {
            // Tipo desconhecido cai no valor cru em vez de quebrar: o catálogo
            // pode ganhar um tipo novo no backend antes de o front conhecê-lo.
            const rotulo =
              DOCUMENTO_LABELS[documento.tipo as keyof typeof DOCUMENTO_LABELS] ?? documento.tipo

            return `${rotulo}: ${documento.nome_original ?? 'sem nome'}`
          })
          .join(' · ')}
      </Indicador>

      {/* Estetoscópio, e NÃO uma cruz vermelha: neste sistema vermelho significa
          perigo ou negado — é a cor do alerta de guia negada —, e uma cruz
          vermelha em toda linha faria o olho ler "algo errado aqui" em quinze
          linhas saudáveis. Token neutro, pelo mesmo motivo do ADR-23. */}
      <Indicador temConteudo={cids.length > 0} rotulo="CID" icone={<Stethoscope className={icone} />}>
        {cids.map((cid) => `${cid.codigo} — ${cid.descricao}`).join(' · ')}
      </Indicador>
    </div>
  )
}
