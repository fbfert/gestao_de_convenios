import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import { useMatch, useNavigate } from 'react-router-dom'
import { useConvenios } from '../../lib/queries/useReferenceData'
import { Select } from '../../components/ui/Select'
import { getHttpErrorMessage, useAtualizarPaciente, useCriarPaciente, usePacientesCrud } from './usePacientes'
import {
  formatCarteirinha,
  isCarteirinhaCompleta,
  joinCarteirinha,
  splitCarteirinha,
} from '../../lib/carteirinha'
import { CarteirinhaBlocosInput } from '../../components/ui/CarteirinhaBlocosInput'
import { cpfValido, formatarCpf, formatarTelefone, somenteDigitos } from '../../lib/documentos'
import { LerCarteirinha } from './LerCarteirinha'
import type { PacientesConsulta } from './usePacientes'
import { TelefonesInput } from './TelefonesInput'
import type { LeituraCarteirinha, Paciente, PacienteForm } from './types'
import { Indicadores } from '../../components/ui/Indicadores'
import { Tooltip } from '../../components/ui/Tooltip'
import { Botao } from '../../components/ui/Botao'
import { Badge } from '../../components/ui/Badge'

const emptyForm: PacienteForm = {
  nome: '',
  cpf: '',
  data_nascimento: '',
  carteirinha: '',
  validade_carteirinha: '',
  convenio_id: '',
  telefones: [],
  ativo: true,
  carteirinha_documento_id: null,
}

const filtrosVazios: PacientesConsulta = {
  busca: '',
  convenio_id: '',
  status: '',
  carteirinha: '',
  ordenar_por: 'nome',
  direcao: 'asc',
}

function selectClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function toForm(paciente: Paciente): PacienteForm {
  return {
    nome: paciente.nome,
    cpf: paciente.cpf ?? '',
    data_nascimento: paciente.data_nascimento ?? '',
    carteirinha: paciente.carteirinha,
    validade_carteirinha: paciente.validade_carteirinha ?? '',
    convenio_id: String(paciente.convenio_id),
    telefones: (paciente.telefones ?? []).map((telefone) => ({
      ...telefone,
      contato_nome: telefone.contato_nome ?? '',
    })),
    ativo: paciente.ativo,
    carteirinha_documento_id: null,
  }
}

/** Telefone que a listagem mostra: o principal, com recuo para a coluna antiga. */
function telefonePrincipal(paciente: Paciente): string {
  const lista = paciente.telefones ?? []
  const escolhido = lista.find((telefone) => telefone.principal) ?? lista[0]

  if (escolhido) {
    return formatarTelefone(escolhido.numero)
  }

  return paciente.telefone ?? '-'
}

/** Vencida quando a validade cadastrada já passou. */
function carteirinhaVencida(validade: string): boolean {
  if (!validade) {
    return false
  }

  return new Date(`${validade}T23:59:59`) < new Date()
}

export function PacientesPage() {
  const navigate = useNavigate()
  const isCreateRoute = useMatch('/pacientes/novo') !== null
  // Editar tambem em rota propria, pelo mesmo motivo da criacao.
  const editRouteMatch = useMatch('/pacientes/:id/editar')
  const routeEditingId = editRouteMatch ? Number(editRouteMatch.params.id) : null
  const isEditRoute = routeEditingId !== null && Number.isInteger(routeEditingId)
  const isFormRoute = isCreateRoute || isEditRoute
  const [filtros, setFiltros] = useState<PacientesConsulta>(filtrosVazios)
  const [rascunho, setRascunho] = useState<PacientesConsulta>(filtrosVazios)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState<PacienteForm>(emptyForm)
  // Os blocos vivem em estado próprio: derivá-los de form.carteirinha fazia os dígitos
  // migrarem de bloco assim que um bloco anterior ficava incompleto.
  const [blocosDigitados, setBlocosDigitados] = useState<string[]>([])
  const [formError, setFormError] = useState<string | null>(null)
  const carregadoPacienteRef = useRef<number | null>(null)

  const pacientesQuery = usePacientesCrud(filtros)
  const conveniosQuery = useConvenios()
  const criarPaciente = useCriarPaciente()
  const atualizarPaciente = useAtualizarPaciente()

  const pacientes = useMemo(() => pacientesQuery.data ?? [], [pacientesQuery.data])
  const pacienteEmEdicao = useMemo(
    () => (isEditRoute ? pacientes.find((paciente) => paciente.id === routeEditingId) ?? null : null),
    [isEditRoute, routeEditingId, pacientes],
  )

  // Hidrata quando a edicao e aberta direto pela URL ou recarregada.
  useEffect(() => {
    if (!isEditRoute) {
      carregadoPacienteRef.current = null

      return
    }

    if (!pacienteEmEdicao || carregadoPacienteRef.current === pacienteEmEdicao.id) {
      return
    }

    carregadoPacienteRef.current = pacienteEmEdicao.id
    setEditingId(pacienteEmEdicao.id)
    setForm(toForm(pacienteEmEdicao))
    setBlocosDigitados(
      pacienteEmEdicao.convenio?.carteirinha_blocos
        ? splitCarteirinha(pacienteEmEdicao.carteirinha, pacienteEmEdicao.convenio.carteirinha_blocos)
        : [],
    )
    setFormError(null)
  }, [isEditRoute, pacienteEmEdicao])
  const convenios = useMemo(() => conveniosQuery.data ?? [], [conveniosQuery.data])
  const selectedConvenio = useMemo(
    () => convenios.find((convenio) => String(convenio.id) === form.convenio_id),
    [convenios, form.convenio_id],
  )
  // O formato vem do convenio, nao do driver de automacao: ver a migration
  // 2026_08_12_200000. `null` significa carteirinha em texto livre.
  const blocos = selectedConvenio?.carteirinha_blocos ?? null
  const totalAtivos = pacientes.filter((paciente) => paciente.ativo).length
  const totalInativos = pacientes.length - totalAtivos

  /*
    O convenio NAO e pre-selecionado. Ele passou a ser a primeira pergunta do
    cadastro justamente porque manda no formato da carteirinha, nas regras de
    autorizacao e no valor pago — escolher tem de ser ato consciente. Antes o
    formulario marcava o primeiro da lista, e bastava nao olhar para cadastrar
    o paciente na operadora errada, em silencio.
  */

  // Trocar para um convenio com formato reaproveita o que ja estava digitado
  // no campo unico.
  useEffect(() => {
    if (!blocos) {
      return
    }

    setBlocosDigitados((current) =>
      joinCarteirinha(current) === form.carteirinha.replace(/\D/g, '')
        ? current
        : splitCarteirinha(form.carteirinha, blocos),
    )
  }, [blocos, form.carteirinha])

  const formIsComplete =
    form.nome.trim() !== '' &&
    (blocos
      ? isCarteirinhaCompleta(blocosDigitados, blocos)
      : form.carteirinha.trim() !== '') &&
    form.convenio_id !== '' &&
    conveniosQuery.isSuccess

  const handleFilterSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFiltros({ ...rascunho, busca: rascunho.busca.trim() })
  }

  /**
   * Clique no cabeçalho: a mesma coluna inverte o sentido, coluna nova começa
   * crescente. Ordenar é no servidor — a lista não é paginada hoje, mas passará
   * a ser, e ordenar só a página visível confundiria mais do que ajudaria.
   */
  const ordenarPor = (coluna: string) => {
    const proxima: PacientesConsulta = {
      ...filtros,
      ordenar_por: coluna,
      direcao: filtros.ordenar_por === coluna && filtros.direcao === 'asc' ? 'desc' : 'asc',
    }

    setFiltros(proxima)
    setRascunho((atual) => ({ ...atual, ordenar_por: proxima.ordenar_por, direcao: proxima.direcao }))
  }

  const handleNew = () => {
    navigate('/pacientes/novo')
    setEditingId(null)
    setForm(emptyForm)
    setBlocosDigitados([])
    setFormError(null)
  }

  const handleEdit = (paciente: Paciente) => {
    setEditingId(paciente.id)
    setForm(toForm(paciente))
    setBlocosDigitados(
      paciente.convenio?.carteirinha_blocos
        ? splitCarteirinha(paciente.carteirinha, paciente.convenio.carteirinha_blocos)
        : [],
    )
    setFormError(null)
    navigate(`/pacientes/${paciente.id}/editar`)
  }

  /*
    O cadastro de convenio abre em outra aba (ver LerCarteirinha). Ao voltar
    para esta, a lista precisa ter o convenio novo — o app desliga o refetch
    automatico no foco, entao aqui e explicito, e so enquanto o formulario
    esta aberto.
  */
  const formAberto = isFormRoute

  useEffect(() => {
    if (!formAberto) {
      return
    }

    const aoFocar = () => {
      void conveniosQuery.refetch()
    }

    window.addEventListener('focus', aoFocar)

    return () => window.removeEventListener('focus', aoFocar)
  }, [formAberto, conveniosQuery])

  /**
   * Traz para o formulário o que a IA leu.
   *
   * Só sobrescreve campo que a leitura trouxe: o que o modelo não reconheceu
   * volta como null, e apagar o que o operador já digitou seria pior que não
   * ler nada. Os blocos da carteirinha se reorganizam sozinhos pelo efeito que
   * observa `form.carteirinha`.
   */
  const aplicarLeitura = (leitura: LeituraCarteirinha) => {
    const { dados, convenio } = leitura

    setFormError(null)
    setForm((current) => ({
      ...current,
      nome: dados.nome ?? current.nome,
      cpf: dados.cpf ?? current.cpf,
      data_nascimento: dados.data_nascimento ?? current.data_nascimento,
      validade_carteirinha: dados.validade_carteirinha ?? current.validade_carteirinha,
      carteirinha: dados.carteirinha ?? current.carteirinha,
      // Convênio só entra quando o casamento foi certeiro; senão o campo fica
      // como está e o aviso explica o que foi lido.
      convenio_id: convenio.id ? String(convenio.id) : current.convenio_id,
      carteirinha_documento_id: leitura.documento_id,
    }))
  }

  const handleToggleAtivo = async (paciente: Paciente) => {
    setFormError(null)

    try {
      await atualizarPaciente.mutateAsync({
        id: paciente.id,
        payload: { ativo: !paciente.ativo },
      })
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível atualizar o status do paciente.'))
    }
  }

  const handleCancel = () => {
    setEditingId(null)
    setForm(emptyForm)
    setBlocosDigitados([])
    setFormError(null)
    carregadoPacienteRef.current = null
    navigate('/pacientes')
  }

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFormError(null)

    // Mesma conferencia da API, feita antes de gastar a viagem.
    if (!cpfValido(form.cpf)) {
      setFormError('O CPF informado não é válido.')

      return
    }

    try {
      if (editingId) {
        const payload: Partial<PacienteForm> = {
          nome: form.nome,
          cpf: form.cpf,
          data_nascimento: form.data_nascimento,
          carteirinha: form.carteirinha,
          validade_carteirinha: form.validade_carteirinha,
          convenio_id: form.convenio_id,
          telefones: form.telefones,
          ativo: form.ativo,
          carteirinha_documento_id: form.carteirinha_documento_id,
        }

        await atualizarPaciente.mutateAsync({
          id: editingId,
          payload,
        })
      } else {
        await criarPaciente.mutateAsync(form)
      }

      setEditingId(null)
      setForm(emptyForm)
      carregadoPacienteRef.current = null
      navigate('/pacientes')
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível salvar o paciente.'))
    }
  }

  return (
    <div className="space-y-8" data-testid="pacientes-page">
      {!isFormRoute ? (
      <section className="space-y-4">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Pacientes</p>
            <h2 className="mt-2 flex items-center gap-2 text-3xl font-semibold text-white">
              Cadastro e referência de pacientes
              <Tooltip rotulo="Para que serve esta tela">
                É a base usada por Solicitações, Guias e Antecipações. Cadastre o paciente aqui —
                com o convênio certo e a carteirinha correta — antes de abrir um pedido para ele.
              </Tooltip>
            </h2>
          </div>

          <Botao variante="primario" onClick={handleNew} data-testid="paciente-novo">
            Novo
          </Botao>
        </div>

        <Indicadores
          itens={[
            { rotulo: 'Total', valor: pacientes.length },
            { rotulo: 'Ativos', valor: totalAtivos },
            { rotulo: 'Inativos', valor: totalInativos },
          ]}
        />
      </section>
      ) : null}

      {isFormRoute ? (
        <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <form onSubmit={handleSubmit} className="space-y-4" data-testid="paciente-form">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="text-lg font-semibold text-white">
                  {editingId ? 'Editar paciente' : 'Novo paciente'}
                </h3>
              </div>
              {editingId ? (
                <span className="rounded-full border border-cyan-300/20 bg-cyan-400/10 px-3 py-1 text-xs font-semibold text-cyan-100">
                  Editando #{editingId}
                </span>
              ) : null}
            </div>

            {conveniosQuery.isLoading ? (
              <div className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
                Carregando convênios...
              </div>
            ) : null}

            {conveniosQuery.isError ? (
              <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-sm text-rose-100">
                Não foi possível carregar os convênios.
              </div>
            ) : null}

            <LerCarteirinha
              onLeitura={aplicarLeitura}
              onEscolherConvenio={(convenioId) =>
                setForm((current) => ({ ...current, convenio_id: String(convenioId) }))
              }
            />

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Convênio</span>
              <Select
                value={form.convenio_id}
                onChange={(event) =>
                  setForm((current) => ({ ...current, convenio_id: event.target.value }))
                }
                className={selectClasses()}
                data-testid="paciente-convenio"
                disabled={conveniosQuery.isLoading || convenios.length === 0}
              >
                <option value="">Selecione o convênio</option>
                {convenios.map((convenio) => (
                  <option key={convenio.id} value={convenio.id}>
                    {convenio.nome}
                  </option>
                ))}
              </Select>
              <span className="block text-xs text-slate-400">
                O convênio define o formato da carteirinha e as regras de autorização.
              </span>
            </label>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Nome Completo</span>
              <input
                value={form.nome}
                onChange={(event) => setForm((current) => ({ ...current, nome: event.target.value }))}
                className={selectClasses()}
                data-testid="paciente-nome"
              />
            </label>

            {blocos ? (
              <div className="space-y-2">
                <span className="text-sm font-medium text-slate-200">Carteirinha</span>
                <CarteirinhaBlocosInput
                  blocos={blocos}
                  blocks={blocosDigitados}
                  onChange={(blocks, carteirinha) => {
                    setBlocosDigitados(blocks)
                    setForm((current) => ({ ...current, carteirinha }))
                  }}
                  testIdPrefix="paciente-carteirinha-blocos"
                />
              </div>
            ) : (
              <label className="block space-y-2">
                <span className="text-sm font-medium text-slate-200">Carteirinha</span>
                <input
                  value={form.carteirinha}
                  onChange={(event) =>
                    setForm((current) => ({ ...current, carteirinha: event.target.value }))
                  }
                  className={selectClasses()}
                  data-testid="paciente-carteirinha"
                />
              </label>
            )}

            <div className="grid gap-4 md:grid-cols-2">
              <label className="block space-y-2">
                <span className="flex items-center gap-1 text-sm font-medium text-slate-200">
                  Validade da carteirinha
                  <Tooltip rotulo="O que acontece se vencer">
                    Uma carteirinha vencida só gera um aviso, aqui e ao abrir uma solicitação para o
                    paciente — nada é bloqueado. Confira o cartão atual com o paciente antes de
                    seguir.
                  </Tooltip>
                </span>
                <input
                  type="date"
                  value={form.validade_carteirinha}
                  onChange={(event) =>
                    setForm((current) => ({ ...current, validade_carteirinha: event.target.value }))
                  }
                  className={selectClasses()}
                  data-testid="paciente-validade-carteirinha"
                />
                {carteirinhaVencida(form.validade_carteirinha) ? (
                  <span className="block rounded-2xl border border-amber-300/20 bg-amber-400/10 px-3 py-2 text-xs text-amber-100">
                    Carteirinha vencida. O cadastro continua permitido — confira o cartão atual com
                    o paciente.
                  </span>
                ) : null}
              </label>

              <label className="block space-y-2">
                <span className="text-sm font-medium text-slate-200">Data de nascimento</span>
                <input
                  type="date"
                  value={form.data_nascimento}
                  onChange={(event) =>
                    setForm((current) => ({ ...current, data_nascimento: event.target.value }))
                  }
                  className={selectClasses()}
                  data-testid="paciente-data-nascimento"
                />
              </label>
            </div>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">CPF</span>
              <input
                value={formatarCpf(form.cpf)}
                onChange={(event) =>
                  setForm((current) => ({ ...current, cpf: somenteDigitos(event.target.value) }))
                }
                placeholder="000.000.000-00"
                inputMode="numeric"
                className={selectClasses()}
                data-testid="paciente-cpf"
              />
              <span className="block text-xs text-slate-400">Opcional.</span>
            </label>

            <TelefonesInput
              telefones={form.telefones}
              onChange={(telefones) => setForm((current) => ({ ...current, telefones }))}
            />

            <label className="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
              <input
                type="checkbox"
                checked={form.ativo}
                onChange={(event) =>
                  setForm((current) => ({ ...current, ativo: event.target.checked }))
                }
                className="h-4 w-4 rounded border-white/20 bg-white/10 text-cyan-300 focus:ring-cyan-300/20"
                data-testid="paciente-ativo"
              />
              <span className="flex items-center gap-1 text-sm font-medium text-slate-200">
                Ativo
                <Tooltip rotulo="O que muda ao desmarcar">
                  Paciente inativo some das listas do dia a dia, mas nada é apagado: o histórico de
                  solicitações, guias e sessões continua íntegro. Use para quem parou de ser
                  atendido.
                </Tooltip>
              </span>
            </label>

            {formError ? (
              <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                {formError}
              </p>
            ) : null}

            <div className="flex gap-3">
              <Botao
                type="submit"
                variante="primario"
                className="flex-1"
                disabled={
                  criarPaciente.isPending ||
                  atualizarPaciente.isPending ||
                  !formIsComplete ||
                  conveniosQuery.isLoading
                }
                data-testid="paciente-submit"
              >
                {editingId
                  ? atualizarPaciente.isPending
                    ? 'Salvando...'
                    : 'Salvar alterações'
                  : criarPaciente.isPending
                    ? 'Salvando...'
                    : 'Criar paciente'}
              </Botao>
              <Botao
                type="button"
                variante="secundario"
                onClick={handleCancel}
                data-testid={editingId ? 'paciente-cancelar' : 'paciente-fechar'}
              >
                {editingId ? 'Cancelar' : 'Fechar'}
              </Botao>
            </div>
          </form>
        </section>
      ) : null}

      {!isFormRoute ? (
      <section className="space-y-4 rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">

          <form className="grid w-full gap-3 md:grid-cols-2 xl:grid-cols-4" onSubmit={handleFilterSubmit}>
            <label className="space-y-2">
              <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Busca</span>
              <input
                value={rascunho.busca}
                onChange={(event) => setRascunho((atual) => ({ ...atual, busca: event.target.value }))}
                className={selectClasses()}
                placeholder="Nome, carteirinha, CPF ou telefone"
                data-testid="paciente-busca"
              />
            </label>

            <label className="space-y-2">
              <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Convênio</span>
              <Select
                value={rascunho.convenio_id}
                onChange={(event) =>
                  setRascunho((atual) => ({ ...atual, convenio_id: event.target.value }))
                }
                className={selectClasses()}
                data-testid="paciente-filtro-convenio"
              >
                <option value="">Todos</option>
                {convenios.map((convenio) => (
                  <option key={convenio.id} value={convenio.id}>
                    {convenio.nome}
                  </option>
                ))}
              </Select>
            </label>

            <label className="space-y-2">
              <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Status</span>
              <Select
                value={rascunho.status}
                onChange={(event) => setRascunho((atual) => ({ ...atual, status: event.target.value }))}
                className={selectClasses()}
                data-testid="paciente-filtro-status"
              >
                <option value="">Todos</option>
                <option value="ativos">Ativos</option>
                <option value="inativos">Inativos</option>
              </Select>
            </label>

            <label className="space-y-2">
              <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Carteirinha</span>
              <Select
                value={rascunho.carteirinha}
                onChange={(event) =>
                  setRascunho((atual) => ({ ...atual, carteirinha: event.target.value }))
                }
                className={selectClasses()}
                data-testid="paciente-filtro-carteirinha"
              >
                <option value="">Todas</option>
                <option value="vencidas">Vencidas</option>
                <option value="sem_validade">Sem validade cadastrada</option>
              </Select>
            </label>

            <div className="flex gap-3 md:col-span-2 xl:col-span-4">
              <Botao type="submit" variante="secundario" data-testid="paciente-busca-submit">
                Filtrar
              </Botao>
              <button
                type="button"
                onClick={() => {
                  setRascunho(filtrosVazios)
                  setFiltros(filtrosVazios)
                }}
                className="text-sm text-slate-300"
              >
                Limpar
              </button>
              <span className="self-center text-xs text-slate-400">
                {pacientesQuery.isLoading ? 'Carregando...' : `${pacientes.length} paciente(s)`}
              </span>
            </div>
          </form>
        </div>

        {pacientesQuery.isLoading ? (
          <div className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
            Carregando pacientes...
          </div>
        ) : pacientesQuery.isError ? (
          <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-sm text-rose-100">
            Não foi possível carregar os pacientes.
          </div>
        ) : (
          <div className="overflow-hidden rounded-3xl border border-white/10">
            <table className="w-full border-collapse text-left text-sm">
              <thead className="bg-white/5 text-xs uppercase tracking-[0.25em] text-slate-400">
                <tr>
                  {[
                    { coluna: 'nome', texto: 'Nome' },
                    { coluna: 'carteirinha', texto: 'Carteirinha' },
                    { coluna: 'convenio', texto: 'Convênio' },
                    { coluna: 'cpf', texto: 'Contato' },
                    { coluna: 'validade_carteirinha', texto: 'Validade' },
                    { coluna: 'ativo', texto: 'Status' },
                  ].map(({ coluna, texto }) => (
                    <th key={coluna} className="px-4 py-3">
                      <button
                        type="button"
                        onClick={() => ordenarPor(coluna)}
                        className="flex items-center gap-1 uppercase tracking-[0.25em] transition hover:text-cyan-100"
                        data-testid={`paciente-ordenar-${coluna}`}
                      >
                        {texto}
                        <span
                          className={
                            filtros.ordenar_por === coluna ? 'text-cyan-200' : 'text-slate-600'
                          }
                          aria-hidden="true"
                        >
                          {filtros.ordenar_por === coluna
                            ? filtros.direcao === 'asc'
                              ? '▲'
                              : '▼'
                            : '↕'}
                        </span>
                      </button>
                    </th>
                  ))}
                  <th className="px-4 py-3">Ações</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/10 bg-slate-950/30">
                {pacientes.map((paciente) => (
                  <tr key={paciente.id} data-testid={`paciente-row-${paciente.id}`}>
                    <td className="px-4 py-4 text-slate-100">
                      <div className="font-medium">{paciente.nome}</div>
                      <div className="text-xs text-slate-400">#{paciente.id}</div>
                    </td>
                    <td className="px-4 py-4 tabular-nums text-slate-200">
                      {formatCarteirinha(paciente.carteirinha, paciente.convenio?.carteirinha_blocos ?? undefined)}
                    </td>
                    <td className="px-4 py-4 text-slate-200">
                      {paciente.convenio?.nome ?? paciente.convenio_id}
                    </td>
                    <td className="px-4 py-4 text-slate-200">
                      {/* Principal da lista nova; a coluna antiga cobre quem foi
                          cadastrado antes dos telefones multiplos. */}
                      <div>{telefonePrincipal(paciente)}</div>
                      <div className="text-xs text-slate-400">
                        {paciente.cpf ? formatarCpf(paciente.cpf) : '-'}
                      </div>
                    </td>
                    <td className="px-4 py-4 text-slate-200">
                      {paciente.validade_carteirinha ? (
                        <span className={paciente.carteirinha_vencida ? 'text-amber-200' : undefined}>
                          {new Date(`${paciente.validade_carteirinha}T12:00:00`).toLocaleDateString('pt-BR')}
                          {paciente.carteirinha_vencida ? ' · vencida' : ''}
                        </span>
                      ) : (
                        <span className="text-slate-500">-</span>
                      )}
                    </td>
                    <td className="px-4 py-4">
                      <Badge
                        tone={paciente.ativo ? 'sucesso' : 'perigo'}
                        data-testid={`paciente-status-${paciente.id}`}
                      >
                        {paciente.ativo ? 'Ativo' : 'Inativo'}
                      </Badge>
                    </td>
                    <td className="px-4 py-4">
                      <div className="flex flex-wrap gap-2">
                        <button
                          type="button"
                          onClick={() => handleEdit(paciente)}
                          className="rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1.5 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
                          data-testid={`paciente-editar-${paciente.id}`}
                        >
                          Editar
                        </button>
                        <button
                          type="button"
                          onClick={() => handleToggleAtivo(paciente)}
                          className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/10 disabled:opacity-60"
                          disabled={atualizarPaciente.isPending}
                          data-testid={`paciente-toggle-${paciente.id}`}
                        >
                          {paciente.ativo ? 'Desativar' : 'Ativar'}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
                {pacientes.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-4 py-8 text-center text-slate-300">
                      Nenhum paciente encontrado.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        )}
      </section>
      ) : null}
    </div>
  )
}
