import { useEffect, useState } from 'react'
import { Botao } from '../../components/ui/Botao'
import {
  buscarContextoPacienteArquivo,
  usePacienteArquivos,
  type ContextoPacienteArquivo,
  type PacienteArquivo,
} from '../pacientes/usePacienteArquivos'

export type PedidoExistenteEscolhido = {
  arquivoId: number
  medico: ContextoPacienteArquivo['medico']
  cidIds: number[]
  itens: ContextoPacienteArquivo['itens']
}

type Etapa = 'escolha' | 'selecionar-arquivo' | 'carregando' | 'confirmar'

/**
 * Pergunta ativa assim que o paciente é escolhido em /solicitacoes/nova e já
 * tem Pedido Médico na pasta: usar um já existente, ler um novo com a IA, ou
 * anexar um novo à mão. Antes disso o aviso era só informativo
 * (`ResumoPastaPaciente`) e o reaproveitamento só acontecia depois da
 * solicitação já criada — a pedido do usuário (10/09/2026), a escolha agora
 * vem primeiro, e "usar" já mostra o médico da última solicitação que usou
 * aquele arquivo, pedindo confirmação em vez de a pessoa digitar tudo de novo.
 *
 * Só considera arquivos tipo `pedido_medico` — laudo, plano individualizado
 * etc. continuam só no aviso informativo de sempre.
 */
export function PedidoMedicoExistentePrompt({
  pacienteId,
  onUsarPedido,
  onLerNovo,
  onAnexarNovo,
}: {
  pacienteId: number | null
  onUsarPedido: (escolha: PedidoExistenteEscolhido) => void
  onLerNovo: () => void
  onAnexarNovo: () => void
}) {
  const arquivosQuery = usePacienteArquivos(pacienteId)
  const [etapa, setEtapa] = useState<Etapa>('escolha')
  const [erro, setErro] = useState<string | null>(null)
  const [contexto, setContexto] = useState<{ arquivo: PacienteArquivo; dados: ContextoPacienteArquivo } | null>(
    null,
  )

  // Muda de paciente (ou de arquivo escolhido em outra visita a esta tela):
  // a pergunta recomeça do zero, nunca herda a resposta de outro paciente.
  useEffect(() => {
    setEtapa('escolha')
    setErro(null)
    setContexto(null)
  }, [pacienteId])

  if (!pacienteId || arquivosQuery.isLoading || arquivosQuery.isError) {
    return null
  }

  const pedidos = (arquivosQuery.data ?? []).filter((arquivo) => arquivo.tipo === 'pedido_medico')

  if (pedidos.length === 0 || etapa === null) {
    return null
  }

  const carregarContexto = async (arquivo: PacienteArquivo) => {
    setEtapa('carregando')
    setErro(null)
    try {
      const dados = await buscarContextoPacienteArquivo(pacienteId, arquivo.id)
      setContexto({ arquivo, dados })
      setEtapa('confirmar')
    } catch {
      setErro('Não foi possível carregar os dados desse pedido. Tente novamente.')
      setEtapa(pedidos.length === 1 ? 'escolha' : 'selecionar-arquivo')
    }
  }

  const handleUsarPedido = () => {
    if (pedidos.length === 1) {
      void carregarContexto(pedidos[0])
      return
    }
    setEtapa('selecionar-arquivo')
  }

  return (
    <div
      className="rounded-2xl border border-cyan-300/20 bg-cyan-400/5 p-4 text-corpo text-cyan-50"
      data-testid="pedido-medico-existente-prompt"
    >
      {etapa === 'escolha' ? (
        <>
          <p className="font-semibold">Este paciente já tem Pedido Médico cadastrado</p>
          <p className="mt-1 text-cyan-50/80">
            {pedidos.length === 1
              ? `1 pedido na pasta (${pedidos[0].nome_original}).`
              : `${pedidos.length} pedidos na pasta.`}{' '}
            O que você quer fazer?
          </p>
          {erro ? <p className="mt-2 text-rose-200">{erro}</p> : null}
          <div className="mt-3 flex flex-wrap gap-2">
            <Botao
              type="button"
              variante="primario"
              tamanho="sm"
              onClick={handleUsarPedido}
              data-testid="pedido-medico-existente-usar"
            >
              Usar pedido existente
            </Botao>
            <Botao
              type="button"
              variante="secundario"
              tamanho="sm"
              onClick={onLerNovo}
              data-testid="pedido-medico-existente-ler-novo"
            >
              Ler novo pedido
            </Botao>
            <Botao
              type="button"
              variante="secundario"
              tamanho="sm"
              onClick={onAnexarNovo}
              data-testid="pedido-medico-existente-anexar-novo"
            >
              Anexar novo pedido
            </Botao>
          </div>
        </>
      ) : null}

      {etapa === 'selecionar-arquivo' ? (
        <>
          <p className="font-semibold">Qual pedido você quer usar?</p>
          <ul className="mt-3 space-y-2">
            {pedidos.map((arquivo) => (
              <li key={arquivo.id}>
                <button
                  type="button"
                  onClick={() => void carregarContexto(arquivo)}
                  className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-left text-corpo text-cyan-50 transition hover:bg-white/10"
                  data-testid={`pedido-medico-existente-opcao-${arquivo.id}`}
                >
                  {arquivo.nome_original}
                  {arquivo.created_at ? (
                    <span className="ml-2 text-meta text-cyan-50/60">
                      {new Intl.DateTimeFormat('pt-BR').format(new Date(arquivo.created_at))}
                    </span>
                  ) : null}
                </button>
              </li>
            ))}
          </ul>
          <Botao
            type="button"
            variante="secundario"
            tamanho="sm"
            className="mt-3"
            onClick={() => setEtapa('escolha')}
            data-testid="pedido-medico-existente-voltar"
          >
            Voltar
          </Botao>
        </>
      ) : null}

      {etapa === 'carregando' ? <p>Carregando dados do pedido...</p> : null}

      {etapa === 'confirmar' && contexto ? (
        <>
          <p className="font-semibold">Confirma usar este pedido?</p>
          <p className="mt-1 text-cyan-50/80">{contexto.arquivo.nome_original}</p>

          <div className="mt-3 space-y-1 rounded-xl border border-white/10 bg-white/5 p-3 text-corpo">
            <p>
              <span className="text-cyan-50/60">Médico: </span>
              {contexto.dados.medico
                ? `${contexto.dados.medico.nome}${contexto.dados.medico.crm ? ` · CRM ${contexto.dados.medico.crm}${contexto.dados.medico.crm_uf ? `/${contexto.dados.medico.crm_uf}` : ''}` : ''}`
                : 'Não identificado — você vai precisar selecionar manualmente.'}
            </p>
            {contexto.dados.cids.length > 0 ? (
              <p>
                <span className="text-cyan-50/60">CID: </span>
                {contexto.dados.cids.map((cid) => cid.codigo).join(', ')}
              </p>
            ) : null}
            {contexto.dados.itens.length > 0 ? (
              <p>
                <span className="text-cyan-50/60">Especialidade(s): </span>
                {contexto.dados.itens
                  .map((item) => `${item.especialidade_nome} · ${item.profissional_nome}`)
                  .join(' + ')}
              </p>
            ) : null}
            {!contexto.dados.medico && contexto.dados.itens.length === 0 ? (
              <p className="text-cyan-50/60">
                Este arquivo ainda não foi usado em nenhuma solicitação — só o pedido em si será
                reaproveitado, o resto do formulário continua em branco.
              </p>
            ) : null}
          </div>

          <div className="mt-3 flex flex-wrap gap-2">
            <Botao
              type="button"
              variante="primario"
              tamanho="sm"
              onClick={() =>
                onUsarPedido({
                  arquivoId: contexto.arquivo.id,
                  medico: contexto.dados.medico,
                  cidIds: contexto.dados.cid_ids,
                  itens: contexto.dados.itens,
                })
              }
              data-testid="pedido-medico-existente-confirmar"
            >
              Confirmar e usar
            </Botao>
            <Botao
              type="button"
              variante="secundario"
              tamanho="sm"
              onClick={() => setEtapa(pedidos.length === 1 ? 'escolha' : 'selecionar-arquivo')}
              data-testid="pedido-medico-existente-cancelar-confirmacao"
            >
              Voltar
            </Botao>
          </div>
        </>
      ) : null}
    </div>
  )
}
