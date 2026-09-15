import { useState, type ReactNode } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { Badge } from '../../components/ui/Badge'
import { Botao } from '../../components/ui/Botao'
import { formatCarteirinha } from '../../lib/carteirinha'
import { translateStatus } from '../../lib/statusLabels'
import { TODOS_DOCUMENTOS } from '../../lib/documentoTipos'
import { statusTone as guiaStatusTone } from '../guias/statusTone'
import { GrupoDocumento } from './PastaDoPacienteDrawer'
import { usePacienteArquivos } from './usePacienteArquivos'
import {
  usePacientePasta,
  type PastaAntecipacao,
  type PastaGuia,
  type PastaSessao,
  type PastaSolicitacao,
} from './usePacientePasta'

function formatarData(valor: string | null) {
  if (!valor) {
    return '—'
  }

  return new Intl.DateTimeFormat('pt-BR').format(new Date(valor))
}

/**
 * Seção recolhida, com a contagem no cabeçalho.
 *
 * A pasta reúne cinco listas; abertas de uma vez, o histórico de um paciente
 * antigo vira uma parede de rolagem e o que interessa fica longe. Recolhida, a
 * contagem já responde "tem quanto?" — expandir é para quando a resposta for
 * "preciso ver quais".
 *
 * Sempre recolhida ao abrir, como os alertas da tela de Guias: expandir vale
 * para a visita e não fica guardado.
 */
function Secao({
  titulo,
  quantidade,
  testId,
  children,
}: {
  titulo: string
  quantidade: number
  testId: string
  children: ReactNode
}) {
  const [aberta, setAberta] = useState(false)
  const painelId = `${testId}-painel`

  return (
    <section
      className="rounded-janela border border-linha bg-superficie-elevada shadow-e2"
      data-testid={testId}
    >
      <button
        type="button"
        onClick={() => setAberta((atual) => !atual)}
        aria-expanded={aberta}
        aria-controls={painelId}
        className="flex w-full items-center justify-between gap-4 p-5 text-left"
        data-testid={`${testId}-alternar`}
      >
        <span className="text-subtitulo font-semibold text-texto">
          {titulo} <span className="text-texto-suave">({quantidade})</span>
        </span>
        <span className="shrink-0 text-corpo font-semibold text-acento">
          {aberta ? 'Recolher' : 'Ver'}
        </span>
      </button>

      {aberta ? (
        <div id={painelId} className="border-t border-linha p-5">
          {quantidade === 0 ? (
            <p className="text-corpo text-texto-suave">Nada registrado até agora.</p>
          ) : (
            children
          )}
        </div>
      ) : null}
    </section>
  )
}

function Linha({ children, to }: { children: ReactNode; to?: string }) {
  const conteudo = (
    <div className="rounded-superficie border border-linha bg-superficie p-4">{children}</div>
  )

  return to ? (
    <Link to={to} className="block transition hover:opacity-80">
      {conteudo}
    </Link>
  ) : (
    conteudo
  )
}

function ListaSolicitacoes({ itens }: { itens: PastaSolicitacao[] }) {
  return (
    <div className="space-y-2">
      {itens.map((s) => (
        <Linha key={s.id} to={`/solicitacoes?id=${s.id}`}>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="font-semibold text-texto">
              #{s.id} · {s.convenio ?? 'Convênio não informado'}
            </p>
            <Badge tone="info">{translateStatus('solicitacoes', s.status)}</Badge>
          </div>
          <p className="mt-1 text-meta text-texto-suave">
            Solicitada em {formatarData(s.solicitado_em)} · {s.medico ?? 'Médico não informado'}
          </p>
          {s.itens.length > 0 ? (
            <p className="mt-1 text-meta text-texto-suave">
              {s.itens
                .map((item) => `${item.especialidade ?? '—'} (${item.quantidade ?? '—'})`)
                .join(' · ')}
            </p>
          ) : null}
        </Linha>
      ))}
    </div>
  )
}

function ListaGuias({ itens }: { itens: PastaGuia[] }) {
  return (
    <div className="space-y-2">
      {itens.map((g) => (
        <Linha key={g.id} to={`/guias/${g.id}`}>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="font-semibold text-texto">
              {g.numero_guia ?? `Guia #${g.id}`} · {g.especialidade ?? 'Especialidade não informada'}
            </p>
            <Badge tone={guiaStatusTone(g.status)}>{translateStatus('guias', g.status)}</Badge>
          </div>
          <p className="mt-1 text-meta text-texto-suave">
            {g.convenio ?? 'Convênio não informado'} · {g.profissional ?? 'Profissional não informado'}
            {g.senha ? ` · senha ${g.senha}` : ''}
            {g.validade_senha ? ` · válida até ${formatarData(g.validade_senha)}` : ''}
          </p>
          <p className="mt-1 text-meta text-texto-suave">
            {g.lancamentos_count} sessão(ões) lançada(s) · {g.sessoes_disponiveis} disponível(is)
          </p>
        </Linha>
      ))}
    </div>
  )
}

function ListaSessoes({ itens }: { itens: PastaSessao[] }) {
  return (
    <div className="space-y-2">
      {itens.map((s) => (
        <Linha key={s.id} to={`/guias/${s.guia_id}`}>
          <p className="font-semibold text-texto">
            {formatarData(s.data_sessao)}
            {s.hora_inicio ? ` · ${s.hora_inicio}` : ''}
            {s.hora_fim ? `–${s.hora_fim}` : ''}
          </p>
          <p className="mt-1 text-meta text-texto-suave">
            {s.profissional ?? 'Profissional não informado'} · guia{' '}
            {s.numero_guia ?? `#${s.guia_id}`}
          </p>
        </Linha>
      ))}
    </div>
  )
}

function ListaAntecipacoes({ itens }: { itens: PastaAntecipacao[] }) {
  return (
    <div className="space-y-2">
      {itens.map((a) => (
        <Linha key={a.id}>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="font-semibold text-texto">
              #{a.id} · {a.convenio ?? 'Convênio não informado'}
            </p>
            <Badge tone={a.status === 'gerada' ? 'sucesso' : 'neutro'}>
              {a.status === 'gerada' ? 'Gerada' : 'Ignorada'}
            </Badge>
          </div>
          <p className="mt-1 text-meta text-texto-suave">
            {a.status === 'gerada' ? `${a.itens_gerados} item(ns) gerado(s)` : 'Dispensada'} em{' '}
            {formatarData(a.created_at)}
            {a.criado_por ? ` por ${a.criado_por}` : ''}
          </p>
        </Linha>
      ))}
    </div>
  )
}

/**
 * Pasta do paciente em tela própria.
 *
 * Era um drawer de meia largura: o cadastro e os documentos cabiam, mas
 * histórico de solicitações, guias, sessões e antecipações não tinham onde
 * aparecer. Com rota própria o link é compartilhável, abre em aba nova e o
 * botão voltar do navegador funciona — mesmo padrão de Guias e Solicitações.
 */
export function PacientePastaPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const pacienteId = id && /^\d+$/.test(id) ? Number(id) : null
  const pastaQuery = usePacientePasta(pacienteId)
  const arquivosQuery = usePacienteArquivos(pacienteId)
  const [erro, setErro] = useState<string | null>(null)

  if (pastaQuery.isLoading) {
    return (
      <div className="rounded-janela border border-linha bg-superficie-elevada p-6 text-corpo text-texto-suave">
        Carregando a pasta do paciente…
      </div>
    )
  }

  if (pastaQuery.isError || !pastaQuery.data) {
    return (
      <div className="space-y-4">
        <p className="rounded-janela border border-perigo/30 bg-perigo-suave p-6 text-corpo text-perigo-texto">
          Paciente não encontrado nesta clínica.
        </p>
        <Link to="/pacientes" className="inline-flex min-h-6 items-center text-corpo font-semibold text-acento">
          ← Voltar para Pacientes
        </Link>
      </div>
    )
  }

  const { paciente, solicitacoes, guias, sessoes, antecipacoes } = pastaQuery.data
  const arquivos = arquivosQuery.data ?? []

  return (
    <div className="space-y-6" data-testid="paciente-pasta-page">
      <Link
        to="/pacientes"
        className="inline-flex min-h-6 items-center text-corpo font-semibold text-acento"
      >
        ← Voltar para Pacientes
      </Link>

      <section className="rounded-janela border border-linha bg-superficie-elevada p-6 shadow-e2">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p className="text-meta uppercase tracking-[0.3em] text-acento">Pasta do paciente</p>
            <h2 className="mt-2 text-display font-semibold text-texto">{paciente.nome}</h2>
            <p className="mt-1 text-corpo text-texto-suave">
              {formatCarteirinha(paciente.carteirinha, paciente.convenio?.carteirinha_blocos ?? undefined)}
              {paciente.convenio?.nome ? ` · ${paciente.convenio.nome}` : ''}
            </p>
          </div>
          <div className="flex items-center gap-3">
            <Badge tone={paciente.ativo ? 'sucesso' : 'perigo'}>
              {paciente.ativo ? 'Ativo' : 'Inativo'}
            </Badge>
            <Botao
              variante="secundario"
              onClick={() => navigate(`/pacientes/${paciente.id}/editar`)}
              data-testid="paciente-pasta-editar"
            >
              Editar cadastro
            </Botao>
          </div>
        </div>

        <dl className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <dt className="text-meta uppercase tracking-[0.2em] text-texto-suave">CPF</dt>
            <dd className="mt-1 text-corpo text-texto">{paciente.cpf ?? '—'}</dd>
          </div>
          <div>
            <dt className="text-meta uppercase tracking-[0.2em] text-texto-suave">Nascimento</dt>
            <dd className="mt-1 text-corpo text-texto">{formatarData(paciente.data_nascimento)}</dd>
          </div>
          <div>
            <dt className="text-meta uppercase tracking-[0.2em] text-texto-suave">Telefone</dt>
            <dd className="mt-1 text-corpo text-texto">{paciente.telefone ?? '—'}</dd>
          </div>
          <div>
            <dt className="text-meta uppercase tracking-[0.2em] text-texto-suave">Validade da carteirinha</dt>
            <dd className="mt-1 text-corpo text-texto">{formatarData(paciente.validade_carteirinha)}</dd>
          </div>
        </dl>
      </section>

      <Secao titulo="Solicitações" quantidade={solicitacoes.length} testId="pasta-solicitacoes">
        <ListaSolicitacoes itens={solicitacoes} />
      </Secao>

      <Secao titulo="Guias" quantidade={guias.length} testId="pasta-guias">
        <ListaGuias itens={guias} />
      </Secao>

      <Secao titulo="Sessões" quantidade={sessoes.length} testId="pasta-sessoes">
        <ListaSessoes itens={sessoes} />
      </Secao>

      <Secao titulo="Antecipações" quantidade={antecipacoes.length} testId="pasta-antecipacoes">
        <ListaAntecipacoes itens={antecipacoes} />
      </Secao>

      <Secao titulo="Arquivos" quantidade={arquivos.length} testId="pasta-arquivos">
        <div className="space-y-4">
          {erro ? (
            <p className="rounded-janela border border-perigo/30 bg-perigo-suave px-4 py-3 text-corpo text-perigo-texto">
              {erro}
            </p>
          ) : null}

          {TODOS_DOCUMENTOS.map((tipo) => (
            <GrupoDocumento
              key={tipo}
              paciente={paciente}
              tipo={tipo}
              arquivos={arquivos.filter((arquivo) => arquivo.tipo === tipo)}
              onError={setErro}
            />
          ))}
        </div>
      </Secao>
    </div>
  )
}
