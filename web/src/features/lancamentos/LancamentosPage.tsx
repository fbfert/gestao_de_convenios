import { Fragment, useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import { ChevronDown, ChevronRight } from 'lucide-react'
import { useListaNaUrl } from '../../lib/useListaNaUrl'
import { Botao } from '../../components/ui/Botao'
import { CapturaWebcam, webcamDisponivel } from '../../components/ui/CapturaWebcam'
import { Paginacao } from '../../components/ui/Paginacao'
import { Indicadores } from '../../components/ui/Indicadores'
import { Badge } from '../../components/ui/Badge'
import { Link, useMatch, useNavigate, useSearchParams } from 'react-router-dom'
import { translateStatus } from '../../lib/statusLabels'
import { Select } from '../../components/ui/Select'
import { useProfissionais } from '../../lib/queries/useReferenceData'
import { useGuia } from '../guias/useGuias'
import type { Guia } from '../guias/types'
import { statusTone as guiaStatusTone } from '../guias/statusTone'
import { FinalizarGuiaButton } from '../guias/FinalizarGuiaButton'
import {
  getHttpErrorMessage,
  useConferirAgenda,
  useConfirmarLancamentosTranscritos,
  useImportarLancamentosTranscritos,
  useLancamentoPrintTemplate,
  useLancamentos,
  useLerRegistroSessoes,
  buscarGuiasDisponiveis,
} from './useLancamentos'
import type {
  ConferenciaDeAgenda,
  LancamentoConfirmImportForm,
  LancamentoFilters,
  LancamentoTranscricaoSessao,
} from './types'
import {
  defaultBlankTemplateData,
  renderLancamentoPrintTemplate,
} from './printTemplate'
import { Tooltip } from '../../components/ui/Tooltip'
import { usePode } from '../../lib/permissoes'
import { HtmlIsolado } from '../../components/ui/HtmlIsolado'
import { SelecionarGuiaModal } from './SelecionarGuiaModal'
import { ConfirmarDivergenciaModal } from './ConfirmarDivergenciaModal'
import {
  conferirPacienteDaFolha,
  descreverDivergencia,
  type ConferenciaDaFolha,
} from './conferenciaDaFolha'

const defaultFilters: LancamentoFilters = {
  profissional_id: '',
  data_sessao: '',
  busca: '',
}

/** Folhas por confirmação — o teto de `ImportLancamentosTranscricaoRequest`. */
const MAXIMO_DE_FOLHAS = 10

const LINHA_VAZIA: LancamentoTranscricaoSessao = {
  data_sessao: null,
  hora_inicio: null,
  hora_fim: null,
  acompanhante: null,
  resumo_atividades: null,
}

/** A folha de registro tem no máximo 10 linhas — a grade sempre mostra as 10. */
function criarGradeVazia(): LancamentoTranscricaoSessao[] {
  return Array.from({ length: 10 }, () => ({ ...LINHA_VAZIA }))
}

/**
 * Normaliza o resultado de uma leitura (IA ou texto colado) para exatamente
 * 10 posições, preservando a ordem recebida — a leitura por IA já devolve 10
 * itens (posição física da folha), mas o parser de texto colado não tem essa
 * noção e pode devolver qualquer quantidade.
 */
function normalizarDezLinhas(sessoes: LancamentoTranscricaoSessao[]): LancamentoTranscricaoSessao[] {
  const recortadas = sessoes.slice(0, 10)
  while (recortadas.length < 10) {
    recortadas.push({ ...LINHA_VAZIA })
  }
  return recortadas
}

function selectClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function celulaClasses() {
  return 'w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-300/70'
}

function formatEmpty(value: string | null | undefined) {
  return value && value.trim() !== '' ? value : '—'
}

export function LancamentosPage() {
  const pode = usePode()
  const navigate = useNavigate()
  const isCreateRoute = useMatch('/lancamentos/novo') !== null
  const [searchParams] = useSearchParams()
  const initialGuiaId = searchParams.get('guia_id') ?? ''

  const { filters, page, setFilters, setPage, searchParams: paginaSearchParams } = useListaNaUrl(defaultFilters)
  const [draftFilters, setDraftFilters] = useState(filters)
  const [isFormOpen, setIsFormOpen] = useState(false)

  const [guiaModalAberto, setGuiaModalAberto] = useState(false)
  const [guiaSelecionada, setGuiaSelecionada] = useState<Guia | null>(null)
  const [profissionalId, setProfissionalId] = useState('')
  const [sessoes, setSessoes] = useState<LancamentoTranscricaoSessao[]>(criarGradeVazia())
  const [numeroCartao, setNumeroCartao] = useState<string | null>(null)
  const [transcricaoTexto, setTranscricaoTexto] = useState('')
  /** Folhas anexadas à mão, além da lida — segunda via, ou a folha de sessões vindas de texto colado. */
  const [folhasAvulsas, setFolhasAvulsas] = useState<File[]>([])
  /**
   * O arquivo que a leitura acabou de usar. Antes a tela o descartava depois
   * de ler, e a folha só ficava na guia se alguém a enviasse de novo no campo
   * avulso — que só aparecia para a regional 0220.
   */
  const [folhaLida, setFolhaLida] = useState<File | null>(null)
  const [formError, setFormError] = useState<string | null>(null)
  const [aviso, setAviso] = useState<string | null>(null)
  const [webcamAberta, setWebcamAberta] = useState(false)
  // O que a folha disse sobre a guia e sobre quem executou. A guia vira
  // escolha; o executante fica só como informação de conferência.
  const [guiaVeioDaLeitura, setGuiaVeioDaLeitura] = useState<string | null>(null)
  const [executanteLido, setExecutanteLido] = useState<string | null>(null)
  const [pacienteLido, setPacienteLido] = useState<string | null>(null)
  const [termoInicialGuia, setTermoInicialGuia] = useState('')
  const [divergenciaAConfirmar, setDivergenciaAConfirmar] = useState<ConferenciaDaFolha | null>(null)
  const arquivoRef = useRef<HTMLInputElement | null>(null)

  const profissionaisQuery = useProfissionais()
  const guiaPreSelecionadaQuery = useGuia(initialGuiaId ? Number(initialGuiaId) : null)
  const lancamentosQuery = useLancamentos(filters, page)
  const printTemplateQuery = useLancamentoPrintTemplate()
  const lerArquivo = useLerRegistroSessoes()
  const analisarTexto = useImportarLancamentosTranscritos()
  const confirmar = useConfirmarLancamentosTranscritos()

  const profissionais = useMemo(() => profissionaisQuery.data ?? [], [profissionaisQuery.data])
  const grupos = lancamentosQuery.data?.data ?? []
  const totalSessoesNaPagina = grupos.reduce((soma, grupo) => soma + grupo.lancamentos.length, 0)
  const totalPages = lancamentosQuery.data?.meta?.last_page ?? 1
  const query = paginaSearchParams.toString()
  const fromHref = query ? `/lancamentos?${query}` : '/lancamentos'

  // Fechados por padrão (decisão do usuário) — só quem foi clicado pra abrir
  // entra aqui.
  const [guiasAbertas, setGuiasAbertas] = useState<Set<number>>(new Set())
  const alternarGuia = (guiaId: number) => {
    setGuiasAbertas((atual) => {
      const proximo = new Set(atual)
      if (proximo.has(guiaId)) {
        proximo.delete(guiaId)
      } else {
        proximo.add(guiaId)
      }
      return proximo
    })
  }

  useEffect(() => {
    if (guiaPreSelecionadaQuery.data && !guiaSelecionada) {
      setGuiaSelecionada(guiaPreSelecionadaQuery.data)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [guiaPreSelecionadaQuery.data])

  /*
    Só quem atende a especialidade da guia. Lançar sessão no nome de quem não
    faz aquela terapia gera glosa na conciliação, e a lista completa da
    clínica torna o erro fácil.
  */
  const executantes = useMemo(() => {
    const especialidadeId = guiaSelecionada?.especialidade?.id

    if (!especialidadeId) {
      return profissionais
    }

    const doEspecialidade = profissionais.filter((profissional) =>
      (profissional.especialidade_ids?.length
        ? profissional.especialidade_ids
        : [profissional.especialidade_id]
      ).includes(especialidadeId),
    )

    return doEspecialidade.length > 0 ? doEspecialidade : profissionais
  }, [profissionais, guiaSelecionada])

  // Um executante só não precisa de escolha; vários, sim.
  useEffect(() => {
    setProfissionalId((atual) => {
      if (atual && executantes.some((profissional) => String(profissional.id) === atual)) {
        return atual
      }

      return executantes.length === 1 ? String(executantes[0].id) : ''
    })
  }, [executantes])

  useEffect(() => {
    setIsFormOpen(isCreateRoute)
  }, [isCreateRoute])

  const handleFilterSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFilters(draftFilters)
  }

  const resetarFormulario = () => {
    setGuiaSelecionada(null)
    setProfissionalId('')
    setSessoes(criarGradeVazia())
    setNumeroCartao(null)
    setTranscricaoTexto('')
    setFolhasAvulsas([])
    setFolhaLida(null)
    setFormError(null)
    setAviso(null)
    setGuiaVeioDaLeitura(null)
    setExecutanteLido(null)
    setPacienteLido(null)
    setTermoInicialGuia('')
    setDivergenciaAConfirmar(null)
  }

  const handleNew = () => {
    navigate({ pathname: '/lancamentos/novo', search: query })
    resetarFormulario()
  }

  const fecharFormulario = () => {
    if (isCreateRoute) {
      navigate({ pathname: '/lancamentos', search: query })
      return
    }

    setIsFormOpen(false)
  }

  /*
    A folha lida bate com a guia escolhida?
    Recalculado a cada render, e não guardado: depende de duas coisas que mudam
    por caminhos independentes — a leitura e a escolha da guia. Guardar num
    estado exigiria lembrar de recalcular nos dois, e um esquecimento deixaria
    o aviso desatualizado sem nada acusar.
  */
  const conferencia = useMemo(
    () =>
      conferirPacienteDaFolha(
        { paciente: pacienteLido, numero_cartao: numeroCartao },
        guiaSelecionada,
      ),
    [pacienteLido, numeroCartao, guiaSelecionada],
  )

  // Só o caminho do texto colado exige os dois: ele posta em
  // `/guias/{id}/lancamentos/importar-transcricao`, que recebe guia e
  // executante no corpo. A leitura por foto/PDF/webcam não exige nada, porque
  // é ela que descobre a guia.
  const prontoParaAnalisarTexto = Boolean(guiaSelecionada) && profissionalId !== ''
  const lendo = analisarTexto.isPending || lerArquivo.isPending
  const exigePdf = numeroCartao?.replace(/\D+/g, '').startsWith('0220') ?? false
  // Os formatos que a API guarda como folha (imagem vira PDF lá). Outro
  // formato lido — HEIC do iPhone, por exemplo — não pode travar o registro
  // das sessões: fica de fora, e a tela avisa.
  // Pelo tipo OU pela extensão: no Windows o navegador às vezes informa tipo
  // vazio para PDF (depende do registro do sistema), e a folha era ignorada em
  // silêncio. A API confere o conteúdo de qualquer forma.
  const folhaLidaAnexavel =
    folhaLida !== null &&
    (['application/pdf', 'image/jpeg', 'image/png'].includes(folhaLida.type) ||
      /\.(pdf|jpe?g|png)$/i.test(folhaLida.name))
  const folhasParaAnexar = [...(folhaLidaAnexavel && folhaLida ? [folhaLida] : []), ...folhasAvulsas]

  /** O limite é da API (dez por confirmação), contando a folha lida. */
  const anexarFolhasAvulsas = (arquivos: FileList | null) => {
    const novas = Array.from(arquivos ?? []).filter((arquivo) => /\.(pdf|jpe?g|png)$/i.test(arquivo.name))
    const vagas = Math.max(MAXIMO_DE_FOLHAS - folhasParaAnexar.length, 0)

    if (novas.length > vagas) {
      setFormError(
        `Até ${MAXIMO_DE_FOLHAS} folhas por registro, contando a folha lida. ${novas.length - vagas} ficaram de fora.`,
      )
    }

    setFolhasAvulsas((atuais) => [...atuais, ...novas.slice(0, vagas)])
  }
  const sessoesPreenchidas = useMemo(() => sessoes.filter((sessao) => Boolean(sessao.data_sessao)).length, [sessoes])

  /*
    Conferência de agenda ao vivo — ver a spec `sessoes-regras-de-agenda`.

    Roda no servidor porque as regras olham as sessões já gravadas em outras
    guias do paciente, que a tela não tem. Muda a cada edição de data ou hora,
    então corrigir a linha já derruba a marcação sem precisar tentar gravar.

    `conflitosPorLinha` e `avisosPorLinha` indexam pelo índice da linha, que é
    a referência que a API devolve.
  */
  const { conferencia: agenda, conferindo: conferindoAgenda } = useConferirAgenda({
    guiaId: guiaSelecionada?.id ?? null,
    profissionalId,
    sessoes,
  })

  const [conflitosPorLinha, avisosPorLinha] = useMemo(() => {
    const conflitos = new Map<number, ConferenciaDeAgenda['conflitos']>()
    const avisos = new Map<number, ConferenciaDeAgenda['avisos']>()

    agenda.conflitos.forEach((conflito) => {
      const linha = Number(conflito.referencia)
      conflitos.set(linha, [...(conflitos.get(linha) ?? []), conflito])
    })

    agenda.avisos.forEach((aviso) => {
      const linha = Number(aviso.referencia)
      avisos.set(linha, [...(avisos.get(linha) ?? []), aviso])
    })

    return [conflitos, avisos] as const
  }, [agenda])

  const temConflitoDeAgenda = agenda.conflitos.length > 0

  const aplicarResultado = (resultado: {
    cabecalho: { numero_cartao: string | null; paciente?: string | null }
    sessoes: LancamentoTranscricaoSessao[]
  }) => {
    const normalizadas = normalizarDezLinhas(resultado.sessoes)
    setSessoes(normalizadas)
    setNumeroCartao(resultado.cabecalho.numero_cartao ?? null)
    // Os DOIS identificadores da folha entram por aqui, e não só no caminho da
    // IA: o texto colado também traz "Paciente:" no cabeçalho, e guardar o
    // cartão sem o nome deixaria a conferência com um lado só — o que acusa
    // divergência sempre que a carteirinha do cadastro estiver em formato
    // diferente do impresso na folha.
    setPacienteLido(resultado.cabecalho.paciente ?? null)
    // As folhas anexadas à mão ficam: uma nova leitura não as apaga.
    // Texto colado não tem folha; a leitura por arquivo a repõe logo depois.
    setFolhaLida(null)
    setAviso(normalizadas.every((sessao) => !sessao.data_sessao) ? 'Nenhuma sessão foi reconhecida no documento.' : null)
  }

  /**
   * Usa o número de guia da folha para escolher a guia.
   *
   * Uma só, disponível para lançamento: escolhe e diz de onde veio. Nenhuma ou
   * várias: abre a busca já preenchida, para ninguém redigitar o que a IA
   * acabou de ler. Sem número lido não faz nada — cair para o nome do paciente
   * casaria com várias guias dele e escolheria a errada em silêncio.
   */
  const resolverGuiaLida = async (
    numeroLido: string | null,
    folha: { paciente: string | null; numero_cartao: string | null },
  ) => {
    if (!numeroLido || guiaSelecionada) {
      return
    }

    setTermoInicialGuia(numeroLido)

    try {
      const encontradas = await buscarGuiasDisponiveis(numeroLido)

      if (encontradas.length === 1) {
        /*
          Escolher sozinho exige que o SEGUNDO identificador da folha também
          feche. Um dígito lido errado raramente cai no vazio — cai numa guia
          real de outro paciente, e é só o paciente que denuncia isso. Sem esta
          conferência, esse caso seria indistinguível de um acerto.

          Divergindo, não escolhe: abre a busca. Não é bloqueio — o operador
          pode escolher esta mesma guia à mão e seguir pela justificativa; o que
          não acontece é a escolha errada entrar calada.
        */
        const confere = conferirPacienteDaFolha(folha, encontradas[0])

        if (confere.confere) {
          setGuiaSelecionada(encontradas[0])
          setProfissionalId('')
          setGuiaVeioDaLeitura(numeroLido)

          return
        }

        setAviso(
          `A guia ${numeroLido} foi encontrada, mas é de outro paciente. ${descreverDivergencia(confere)}. Confira antes de escolher.`,
        )
      }

      setGuiaModalAberto(true)
    } catch {
      // Falhar a busca não pode derrubar a leitura, que é a parte cara: a
      // grade já está preenchida, e a guia continua escolhível à mão.
      setGuiaModalAberto(true)
    }
  }

  const ler = async (arquivo: File | undefined) => {
    if (!arquivo) {
      return
    }

    setFormError(null)
    setAviso(null)
    setGuiaVeioDaLeitura(null)
    setExecutanteLido(null)
    setPacienteLido(null)

    try {
      // Sem guia: a folha é que diz de qual guia ela é, e é por isso que a
      // leitura pode vir ANTES da escolha.
      const resultado = await lerArquivo.mutateAsync(arquivo)
      aplicarResultado(resultado)
      setFolhaLida(arquivo)
      setExecutanteLido(resultado.cabecalho.profissional_executante ?? null)
      await resolverGuiaLida(resultado.cabecalho.guia_numero ?? null, {
        paciente: resultado.cabecalho.paciente ?? null,
        numero_cartao: resultado.cabecalho.numero_cartao ?? null,
      })
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível ler o registro de sessões.'))
    } finally {
      if (arquivoRef.current) {
        arquivoRef.current.value = ''
      }
    }
  }

  const analisar = async (event: FormEvent) => {
    event.preventDefault()
    if (!guiaSelecionada || !profissionalId) {
      return
    }

    setFormError(null)
    setAviso(null)

    try {
      aplicarResultado(
        await analisarTexto.mutateAsync({
          guia_id: String(guiaSelecionada.id),
          profissional_id: profissionalId,
          transcricao: transcricaoTexto,
        }),
      )
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível analisar a transcrição.'))
    }
  }

  const atualizarSessao = (
    indice: number,
    campo: keyof LancamentoTranscricaoSessao,
    valor: string,
  ) => {
    setSessoes((atual) => atual.map((sessao, i) => (i === indice ? { ...sessao, [campo]: valor || null } : sessao)))
  }

  const gravar = async (justificativa?: string) => {
    try {
      const payload: LancamentoConfirmImportForm = {
        guia_id: String(guiaSelecionada!.id),
        profissional_id: profissionalId,
        transcricao: transcricaoTexto,
        numero_cartao: numeroCartao,
        sessoes,
        folhas_registro: folhasParaAnexar,
        divergencia: justificativa ? descreverDivergencia(conferencia) : null,
        divergencia_justificativa: justificativa ?? null,
      }

      await confirmar.mutateAsync(payload)
      setDivergenciaAConfirmar(null)

      if (isCreateRoute) {
        navigate({ pathname: '/lancamentos', search: query })
      } else {
        setIsFormOpen(false)
      }
    } catch (error) {
      setDivergenciaAConfirmar(null)
      setFormError(getHttpErrorMessage(error, 'Não foi possível registrar as sessões.'))
    }
  }

  const enviar = async () => {
    setFormError(null)

    if (!guiaSelecionada || !profissionalId) {
      setFormError('Selecione a guia e o profissional executante.')
      return
    }

    if (sessoesPreenchidas === 0) {
      setFormError('Preencha ao menos uma linha com data da sessão.')
      return
    }

    if (exigePdf && folhasParaAnexar.length === 0) {
      setFormError('O PDF do registro de sessões é obrigatório para a regional 0220.')
      return
    }

    // Conflito de agenda não tem "confirmar assim mesmo": só sai daqui
    // corrigido. Diferente da divergência de paciente logo abaixo, que tem.
    if (temConflitoDeAgenda) {
      setFormError(
        'Há sessões em conflito na agenda do paciente. Corrija as linhas marcadas antes de registrar.',
      )
      return
    }

    // A trava fica AQUI, e não na escolha da guia: gravar é o passo
    // irreversível, e é o único ponto por onde passam a escolha automática, a
    // manual, e o caso de escolher a guia antes de ler a folha.
    if (!conferencia.confere) {
      setDivergenciaAConfirmar(conferencia)
      return
    }

    await gravar()
  }

  const printHtml = useMemo(
    () =>
      renderLancamentoPrintTemplate(
        printTemplateQuery.data?.html ?? '',
        defaultBlankTemplateData,
      ),
    [printTemplateQuery.data?.html],
  )

  return (
    <>
      <div className="space-y-8 print:hidden" data-testid="lancamentos-page">
        {!isCreateRoute ? (
        <section className="space-y-4">
          <div className="flex flex-col gap-4 sm:items-start lg:flex-row lg:items-end lg:justify-between">
            <div>
              <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Sessões</p>
              <h2 className="mt-2 flex items-center gap-2 text-display font-semibold text-white">
                Registro de sessões
                <Tooltip rotulo="O que se registra aqui">
                  <p className="font-semibold text-white">O atendimento realizado</p>
                  <p className="mt-1">
                    Cada sessão lançada aqui conta contra a cota de sessões disponíveis da guia
                    escolhida. Preencha manualmente ou anexe uma foto/PDF da folha de registro
                    para a IA ler e trazer até 10 sessões de uma vez, prontas para conferência.
                  </p>
                </Tooltip>
              </h2>
            </div>

            <div className="flex flex-wrap gap-2">
              <Link
                to="/lancamentos/templates"
                className="inline-flex items-center justify-center rounded-2xl border border-white/10 bg-white/5 h-10 px-4 text-corpo font-semibold text-white transition hover:bg-white/10"
                data-testid="lancamento-templates"
              >
                Templates
              </Link>
              {pode('lancamentos.manage') ? (
                <Link
                  to="/lancamentos/importar-planilha"
                  className="inline-flex items-center justify-center rounded-2xl border border-white/10 bg-white/5 h-10 px-4 text-corpo font-semibold text-white transition hover:bg-white/10"
                  data-testid="lancamento-importar-planilha"
                >
                  Importar planilha
                </Link>
              ) : null}
              <Botao
                variante="secundario"
                onClick={() => window.print()}
                data-testid="lancamento-imprimir-modelo"
                disabled={printTemplateQuery.isLoading || printHtml.trim() === ''}
              >
                {printTemplateQuery.isLoading ? 'Carregando modelo...' : 'Imprimir modelo em branco'}
              </Botao>
              <Botao variante="primario" onClick={handleNew} data-testid="lancamento-novo">
                Novo
              </Botao>
              <Tooltip rotulo="Diferença entre os botões">
                <p><strong>Templates:</strong> textos padrão reaproveitados no resumo da sessão.</p>
                <p className="mt-1">
                  <strong>Imprimir modelo em branco:</strong> gera a tabela em papel para o
                  profissional preencher à mão durante o atendimento.
                </p>
                <p className="mt-1">
                  <strong>Novo:</strong> busca a guia, escolhe o executante e registra até 10
                  sessões — digitando, colando a transcrição ou anexando a folha para a IA ler.
                </p>
              </Tooltip>
            </div>
          </div>

          <Indicadores
            itens={[
              { rotulo: 'Guias na página', valor: grupos.length },
              { rotulo: 'Sessões na página', valor: totalSessoesNaPagina },
              { rotulo: 'Página', valor: `${page} de ${totalPages}` },
            ]}
          />
        </section>
        ) : null}

        {isFormOpen ? (
          <section className="space-y-6 rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
            <div className="flex items-start justify-between gap-4">
              <h3 className="text-subtitulo font-semibold text-white">Novo lançamento</h3>
              <Botao variante="secundario" onClick={fecharFormulario} data-testid="lancamento-fechar">
                Fechar
              </Botao>
            </div>

            <div className="space-y-2">
              <span className="flex items-center gap-1 text-corpo font-medium text-slate-200">
                Guia
                <Tooltip rotulo="O que escolher aqui">
                  A guia aprovada do paciente para esta especialidade. Escolha a que corresponde
                  ao atendimento — lançar contra a guia errada consome a cota de outro paciente
                  ou especialidade.
                </Tooltip>
              </span>
              <button
                type="button"
                onClick={() => setGuiaModalAberto(true)}
                className={`${selectClasses()} text-left`}
                data-testid="lancamento-guia"
              >
                {guiaSelecionada
                  ? `#${guiaSelecionada.id} · ${guiaSelecionada.numero_guia ?? 'sem nº'} · ${guiaSelecionada.paciente?.nome ?? `Paciente ${guiaSelecionada.paciente_id}`}`
                  : 'Selecione uma guia'}
              </button>
              {guiaSelecionada ? (
                <p className="text-meta text-slate-400" data-testid="lancamento-especialidade">
                  Especialidade: {guiaSelecionada.especialidade?.nome ?? 'não definida'} ·{' '}
                  {guiaSelecionada.sessoes_disponiveis} sessão(ões) disponível(is)
                </p>
              ) : null}

              {/* De onde veio a escolha. Uma guia que se preenche sozinha sem
                  dizer por quê é pior do que uma em branco: ninguém confere o
                  que não sabe que foi decidido por outro. */}
              {guiaVeioDaLeitura ? (
                <p
                  className="text-meta text-emerald-200"
                  data-testid="lancamento-guia-da-leitura"
                >
                  Escolhida pela leitura: a folha traz a guia {guiaVeioDaLeitura}, e o paciente
                  confere. Confira antes de confirmar.
                </p>
              ) : null}

              {/*
                Aviso permanente, e não um alerta que passa: enquanto a guia
                escolhida contradisser a folha, a contradição fica na tela,
                nomeando os dois lados. Vale para a escolha manual também — o
                perigo é o mesmo, venha de onde vier.
              */}
              {!conferencia.confere ? (
                <p
                  className="rounded-2xl border border-amber-300/30 bg-amber-400/10 px-4 py-3 text-corpo text-amber-100"
                  role="alert"
                  data-testid="lancamento-divergencia-aviso"
                >
                  <strong className="font-semibold">A folha não confere com esta guia.</strong>{' '}
                  {descreverDivergencia(conferencia)}. Lançar assim consome a cota desta guia — vai
                  pedir justificativa na confirmação.
                </p>
              ) : null}
            </div>

            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Profissional executante</span>
              <Select
                value={profissionalId}
                onChange={(event) => setProfissionalId(event.target.value)}
                className={selectClasses()}
                disabled={!guiaSelecionada}
                data-testid="lancamento-profissional"
              >
                <option value="">Selecione</option>
                {executantes.map((profissional) => (
                  <option key={profissional.id} value={profissional.id}>
                    {profissional.nome}
                  </option>
                ))}
              </Select>

              {/* Mostrado, nunca preenchido. A folha é manuscrita, e lançar
                  contra executante que não atende a especialidade gera glosa
                  na conciliação — um palpite errado aqui só aparece lá. */}
              {executanteLido ? (
                <span className="block text-corpo-lg text-slate-300" data-testid="lancamento-executante-lido">
                  A folha diz:{' '}
                  <strong className="rounded-md bg-cyan-300/15 px-1.5 py-0.5 text-subtitulo font-bold text-white">
                    {executanteLido}
                  </strong>
                  .
                  Confirme quem executou — o campo não é preenchido pela leitura.
                </span>
              ) : null}
            </label>

            <div className="flex flex-wrap items-center gap-3">
              {/*
                Ler NÃO depende de guia nem de executante: é a folha que traz
                o número da guia, o paciente e o cartão. Exigir a escolha antes
                era pedir que alguém procurasse à mão exatamente o que a IA
                leria em seguida. Confirmar as sessões continua exigindo os
                dois — muda a ordem de descobrir, não o que é obrigatório.
              */}
              <Botao
                variante="primario"
                onClick={() => arquivoRef.current?.click()}
                disabled={lendo || webcamAberta}
                data-testid="lancamento-anexo-botao"
              >
                {lerArquivo.isPending ? 'Lendo registro...' : 'Ler foto ou PDF do registro'}
              </Botao>

              {/*
                O `capture` do input acima só vale no celular: no computador o
                atributo é ignorado e o clique vira seletor de arquivo. Daí a
                webcam — sem ela, quem trabalha no computador fotografa no
                celular, manda para si mesmo e baixa, com a câmera na mesa.
              */}
              {webcamDisponivel() ? (
                <Botao
                  type="button"
                  variante="secundario"
                  onClick={() => {
                    setFormError(null)
                    setAviso(null)
                    setWebcamAberta((aberta) => !aberta)
                  }}
                  disabled={lendo}
                  data-testid="lancamento-webcam-botao"
                >
                  {webcamAberta ? 'Fechar webcam' : 'Usar webcam'}
                </Botao>
              ) : null}

              <input
                ref={arquivoRef}
                type="file"
                accept="image/*,application/pdf"
                capture="environment"
                onChange={(event) => void ler(event.target.files?.[0])}
                className="hidden"
                data-testid="lancamento-anexo"
              />

              <span className="text-meta text-slate-400">
                A IA lê o documento e traz até 10 sessões para conferência. Se a folha tiver o
                número da guia, ela já vem escolhida.
              </span>
            </div>

            {webcamAberta ? (
              <CapturaWebcam
                onCapturar={(arquivo) => void ler(arquivo)}
                onFechar={() => setWebcamAberta(false)}
                onErro={setFormError}
                nomeArquivo="registro-sessoes.jpg"
                // A folha traz até 10 linhas de letra manuscrita, com data e
                // dois horários cada: nos 1600px que bastam para um cartão o
                // texto fica no limite do legível.
                larguraMaxima={2400}
                // Uma foto tremida custa a chamada de IA e a espera até
                // alguém descobrir que não deu.
                conferirAntesDeEnviar
                dica="Enquadre a folha inteira, sem sombra, com as linhas na horizontal."
                testIdPrefixo="lancamento-webcam"
              />
            ) : null}

            {lendo ? (
              <div
                className="flex items-center gap-3 rounded-2xl border border-cyan-300/30 bg-cyan-400/10 px-4 py-3 text-corpo text-cyan-50"
                role="status"
                aria-live="polite"
                data-testid="lancamento-lendo"
              >
                <span className="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-cyan-200/40 border-t-cyan-100" />
                <span>
                  <strong className="font-semibold">Lendo o registro…</strong> costuma levar de 5 a 30
                  segundos. Não feche a tela nem clique de novo.
                </span>
              </div>
            ) : null}

            <details className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1">
              <summary className="cursor-pointer text-corpo font-semibold text-slate-200">
                Colar a transcrição em texto
              </summary>

              <form onSubmit={analisar} className="mt-4 space-y-3">
                <textarea
                  value={transcricaoTexto}
                  onChange={(event) => setTranscricaoTexto(event.target.value)}
                  className={`${selectClasses()} min-h-48 font-mono text-corpo leading-6`}
                  placeholder={`GUIA Nº: 521381566206\nPaciente: ...\nNúmero Cartão: 0220 090000 551.330-8\n\n08/04/26 14:50 15:40 Bruno Marinho Aplicação de testes`}
                  data-testid="lancamento-transcricao"
                />

                <button
                  type="submit"
                  disabled={!prontoParaAnalisarTexto || lendo || transcricaoTexto.trim() === ''}
                  className="rounded-2xl border border-cyan-300/30 bg-cyan-400/10 px-4 py-2 text-corpo font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:opacity-60"
                  data-testid="lancamento-analisar-texto"
                >
                  {analisarTexto.isPending ? 'Analisando...' : 'Analisar texto colado'}
                </button>
              </form>
            </details>

            {formError ? (
              <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
                {formError}
              </p>
            ) : null}

            {aviso ? (
              <p className="rounded-2xl border border-amber-300/20 bg-amber-400/10 px-4 py-3 text-corpo text-amber-100">
                {aviso}
              </p>
            ) : null}

            {folhaLidaAnexavel ? (
              <p
                className="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-corpo text-emerald-100"
                data-testid="lancamento-folha-lida"
              >
                A folha lida (<strong className="font-semibold">{folhaLida?.name}</strong>) será anexada à
                guia ao registrar as sessões{folhaLida && (folhaLida.type.startsWith('image/') || /\.(jpe?g|png)$/i.test(folhaLida.name))
                  ? ', convertida em PDF'
                  : ''}.
              </p>
            ) : folhaLida ? (
              <p
                className="rounded-2xl border border-amber-400/20 bg-amber-500/10 px-4 py-3 text-corpo text-amber-100"
                data-testid="lancamento-folha-lida-nao-anexavel"
              >
                O formato da folha lida não pode ser guardado automaticamente. Depois de registrar,
                anexe a folha em PDF, JPG ou PNG na tela da guia.
              </p>
            ) : null}

            {exigePdf && folhasParaAnexar.length === 0 ? (
              <div className="rounded-2xl border border-amber-400/20 bg-amber-500/10 px-4 py-3 text-corpo text-amber-50">
                Regional 0220 detectada pela carteirinha. A folha do registro de sessões é obrigatória
                para confirmar o envio — anexe-a abaixo.
              </div>
            ) : null}

            {/*
              Sempre visível: a segunda via, a folha de sessões vindas de texto
              colado, ou a folha que a leitura não pôde guardar. Antes o campo só
              aparecia para a regional 0220, e aceitava um arquivo só.
            */}
            <div className="space-y-2" data-testid="lancamento-folhas-avulsas">
              <label className="block space-y-2">
                <span className="text-corpo font-medium text-slate-200">Anexar folhas de registro</span>
                <input
                  type="file"
                  multiple
                  accept="application/pdf,.pdf,image/jpeg,.jpg,.jpeg,image/png,.png"
                  onChange={(event) => {
                    anexarFolhasAvulsas(event.target.files)
                    event.target.value = ''
                  }}
                  className="inline-flex items-center justify-center block w-full rounded-2xl border border-white/10 bg-white/5 h-10 px-4 text-corpo text-slate-200 file:mr-4 file:rounded-full file:border-0 file:bg-cyan-400 file:px-4 file:py-2 file:text-corpo file:font-semibold file:text-slate-950"
                  data-testid="lancamento-pdf"
                />
                <span className="block text-meta text-slate-400">
                  PDF, JPG ou PNG — foto vira PDF. Vão para a guia junto com a folha lida, ao registrar as sessões.
                </span>
              </label>

              {folhasAvulsas.length > 0 ? (
                <ul className="space-y-1">
                  {folhasAvulsas.map((folha, indice) => (
                    <li
                      key={`${folha.name}-${indice}`}
                      className="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-corpo text-slate-200"
                      data-testid="lancamento-folha-avulsa"
                    >
                      <span className="truncate">{folha.name}</span>
                      <button
                        type="button"
                        onClick={() => setFolhasAvulsas((atuais) => atuais.filter((_, i) => i !== indice))}
                        className="shrink-0 rounded-full border border-white/10 px-3 py-1 text-meta font-semibold text-slate-300 transition hover:bg-white/10"
                        data-testid={`lancamento-folha-avulsa-retirar-${indice}`}
                      >
                        Retirar
                      </button>
                    </li>
                  ))}
                </ul>
              ) : null}
            </div>

            <div className="overflow-x-auto rounded-superficie border border-linha">
              <table className="w-full min-w-[52rem] border-collapse text-left text-corpo" data-cartoes="lg">
                <thead className="bg-fundo text-meta uppercase tracking-[0.25em] text-texto-suave">
                  <tr>
                    <th className="px-4 py-3">Linha</th>
                    <th className="px-4 py-3">Data</th>
                    <th className="px-4 py-3">Início</th>
                    <th className="px-4 py-3">Fim</th>
                    <th className="px-4 py-3">Acompanhante</th>
                    <th className="px-4 py-3">Resumo</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-linha bg-superficie">
                  {sessoes.map((sessao, indice) => {
                    const conflitosDaLinha = conflitosPorLinha.get(indice) ?? []
                    const avisosDaLinha = avisosPorLinha.get(indice) ?? []
                    const emConflito = conflitosDaLinha.length > 0

                    return (
                    <tr
                      key={indice}
                      data-testid={`lancamento-linha-${indice + 1}`}
                      data-conflito={emConflito ? 'true' : undefined}
                      className={emConflito ? 'bg-rose-500/10' : undefined}
                    >
                      <td className="px-4 py-3 text-slate-400">{indice + 1}</td>
                      <td data-rotulo="Data" className="px-4 py-3">
                        <input
                          type="date"
                          value={sessao.data_sessao ?? ''}
                          onChange={(event) => atualizarSessao(indice, 'data_sessao', event.target.value)}
                          className={`${celulaClasses()} ${emConflito ? 'border-rose-400/70' : ''}`}
                        />
                      </td>
                      <td data-rotulo="Início" className="px-4 py-3">
                        <input
                          type="time"
                          value={sessao.hora_inicio ?? ''}
                          onChange={(event) => atualizarSessao(indice, 'hora_inicio', event.target.value)}
                          className={`${celulaClasses()} ${emConflito ? 'border-rose-400/70' : ''}`}
                        />
                      </td>
                      <td data-rotulo="Fim" className="px-4 py-3">
                        <input
                          type="time"
                          value={sessao.hora_fim ?? ''}
                          onChange={(event) => atualizarSessao(indice, 'hora_fim', event.target.value)}
                          className={celulaClasses()}
                        />
                      </td>
                      <td data-rotulo="Acompanhante" className="px-4 py-3">
                        <input
                          value={sessao.acompanhante ?? ''}
                          onChange={(event) => atualizarSessao(indice, 'acompanhante', event.target.value)}
                          className={celulaClasses()}
                        />
                      </td>
                      <td data-rotulo="Resumo" data-rotulo-bloco className="px-4 py-3">
                        <textarea
                          value={sessao.resumo_atividades ?? ''}
                          onChange={(event) => atualizarSessao(indice, 'resumo_atividades', event.target.value)}
                          className={`${celulaClasses()} min-h-16`}
                        />

                        {conflitosDaLinha.map((conflito, i) => (
                          <p
                            key={`conflito-${i}`}
                            className="mt-2 text-meta text-rose-300"
                            data-testid={`lancamento-linha-${indice + 1}-conflito`}
                          >
                            {conflito.mensagem}
                          </p>
                        ))}

                        {avisosDaLinha.map((aviso, i) => (
                          <p
                            key={`aviso-${i}`}
                            className="mt-2 text-meta text-amber-300"
                            data-testid={`lancamento-linha-${indice + 1}-aviso`}
                          >
                            {aviso.mensagem}
                          </p>
                        ))}
                      </td>
                    </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
              <p className="inline-flex min-h-6 items-center text-corpo text-slate-300">
                {sessoesPreenchidas} de 10 linha(s) preenchida(s).
                {temConflitoDeAgenda ? (
                  <span className="ml-2 text-rose-300" data-testid="lancamento-conflito-resumo">
                    {agenda.conflitos.length} em conflito — corrija para registrar.
                  </span>
                ) : null}
                {conferindoAgenda ? (
                  <span className="ml-2 text-slate-400">Conferindo a agenda...</span>
                ) : null}
              </p>

              <Botao
                variante="primario"
                onClick={() => void enviar()}
                disabled={confirmar.isPending || sessoesPreenchidas === 0 || temConflitoDeAgenda}
                data-testid="lancamento-submit"
              >
                {confirmar.isPending ? 'Salvando...' : 'Registrar sessões'}
              </Botao>
            </div>
          </section>
        ) : null}

        {!isCreateRoute ? (
        <section className="space-y-4 rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
          <div className="flex flex-col gap-4 sm:items-start lg:flex-row lg:items-end lg:justify-between">

            <form className="grid gap-3 md:grid-cols-4 xl:grid-cols-4" onSubmit={handleFilterSubmit}>
              <label className="space-y-2">
                <span className="text-meta uppercase tracking-[0.25em] text-slate-400">
                  Buscar
                </span>
                <input
                  type="text"
                  value={draftFilters.busca}
                  onChange={(event) =>
                    setDraftFilters((current) => ({ ...current, busca: event.target.value }))
                  }
                  placeholder="Guia, paciente, médico, profissional ou ID..."
                  className={selectClasses()}
                  data-testid="lancamento-filtro-busca"
                />
              </label>

              <label className="space-y-2">
                <span className="text-meta uppercase tracking-[0.25em] text-slate-400">
                  Profissional executante
                </span>
                <Select
                  value={draftFilters.profissional_id}
                  onChange={(event) =>
                    setDraftFilters((current) => ({ ...current, profissional_id: event.target.value }))
                  }
                  className={selectClasses()}
                  data-testid="lancamento-filtro-profissional"
                >
                  <option value="">Todos</option>
                  {profissionais.map((profissional) => (
                    <option key={profissional.id} value={profissional.id}>
                      {profissional.nome}
                    </option>
                  ))}
                </Select>
              </label>

              <label className="space-y-2">
                <span className="text-meta uppercase tracking-[0.25em] text-slate-400">
                  Data sessão
                </span>
                <input
                  type="date"
                  value={draftFilters.data_sessao}
                  onChange={(event) =>
                    setDraftFilters((current) => ({ ...current, data_sessao: event.target.value }))
                  }
                  className={selectClasses()}
                  data-testid="lancamento-filtro-data-sessao"
                />
              </label>

              <Botao type="submit" variante="secundario">
                Aplicar
              </Botao>
            </form>
          </div>

          {lancamentosQuery.isLoading ? (
            <div className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1 text-corpo text-slate-300">
              Carregando sessões...
            </div>
          ) : lancamentosQuery.isError ? (
            <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-corpo text-rose-100">
              Não foi possível carregar a lista.
            </div>
          ) : (
            <div className="overflow-hidden rounded-3xl border border-white/10">
              <table className="w-full border-collapse text-left text-corpo" data-cartoes="lg">
                <thead className="bg-fundo text-meta uppercase tracking-[0.25em] text-texto-suave">
                  <tr>
                    <th className="px-4 py-3">ID</th>
                    <th className="px-4 py-3">Profissional executante</th>
                    <th className="px-4 py-3">Data / Hora</th>
                    <th className="px-4 py-3">Acompanhante</th>
                    <th className="px-4 py-3">Resumo</th>
                    <th className="px-4 py-3">Status</th>
                    <th className="px-4 py-3">Ações</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-linha bg-superficie">
                  {grupos.map((grupo) => {
                    const aberta = guiasAbertas.has(grupo.guia_id)

                    return (
                      <Fragment key={grupo.guia_id}>
                        <tr>
                          <td colSpan={7} className="p-0">
                            <div className="flex w-full flex-wrap items-center gap-3 px-4 py-3">
                              <button
                                type="button"
                                onClick={() => alternarGuia(grupo.guia_id)}
                                className="flex flex-1 flex-wrap items-center gap-3 text-left transition hover:bg-white/5"
                                data-testid={`lancamento-grupo-guia-${grupo.guia_id}`}
                                aria-expanded={aberta}
                              >
                                {aberta ? (
                                  <ChevronDown className="size-4 shrink-0 text-slate-400" aria-hidden="true" />
                                ) : (
                                  <ChevronRight className="size-4 shrink-0 text-slate-400" aria-hidden="true" />
                                )}
                                <span className="font-semibold text-white">
                                  Guia {grupo.guia?.numero_guia ?? `#${grupo.guia_id}`}
                                </span>
                                {grupo.guia?.paciente_nome ? (
                                  <span className="text-slate-300">· {grupo.guia.paciente_nome}</span>
                                ) : null}
                                {grupo.guia?.medico_nome ? (
                                  <span className="text-slate-400">· Dr(a). {grupo.guia.medico_nome}</span>
                                ) : null}
                                {grupo.guia?.status ? (
                                  <Badge tone={guiaStatusTone(grupo.guia.status)}>
                                    {translateStatus('guias', grupo.guia.status)}
                                  </Badge>
                                ) : null}
                              </button>
                              <FinalizarGuiaButton guia={grupo.guia} />
                              <span className="ml-auto text-meta font-semibold text-slate-400">
                                {grupo.lancamentos.length} sessão(ões)
                              </span>
                            </div>
                          </td>
                        </tr>

                        {aberta
                          ? grupo.lancamentos.map((lancamento) => (
                              <tr key={lancamento.id} data-testid={`lancamento-row-${lancamento.id}`}>
                                <td data-rotulo="ID" className="px-4 py-4 font-medium text-white">
                                  #{lancamento.id}
                                </td>
                                <td data-rotulo="Profissional executante" className="px-4 py-4 text-slate-200">
                                  {lancamento.profissional?.nome ??
                                    profissionais.find((item) => item.id === lancamento.profissional_id)?.nome ??
                                    lancamento.profissional_id}
                                </td>
                                <td data-rotulo="Data / Hora" className="px-4 py-4 text-slate-200">
                                  <div>{lancamento.data_sessao}</div>
                                  <div className="text-meta text-slate-400">
                                    {formatEmpty(lancamento.hora_inicio)} - {formatEmpty(lancamento.hora_fim)}
                                  </div>
                                </td>
                                <td data-rotulo="Acompanhante" className="px-4 py-4 text-slate-200">
                                  {formatEmpty(lancamento.acompanhante)}
                                </td>
                                <td data-rotulo="Resumo" data-rotulo-bloco className="px-4 py-4 text-slate-200">
                                  <span className="block max-w-xl">{formatEmpty(lancamento.resumo_atividades)}</span>
                                </td>
                                <td data-rotulo="Status" className="px-4 py-4 text-slate-200">
                                  {translateStatus('lancamentos', lancamento.status)}
                                </td>
                                <td data-rotulo="Ações" data-rotulo-bloco className="px-4 py-4">
                                  {pode('lancamentos.manage') ? (
                                    <Link
                                      to={`/lancamentos/${lancamento.id}/editar`}
                                      state={{ from: fromHref }}
                                      className="inline-flex rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-meta font-semibold text-white transition hover:bg-white/10"
                                      data-testid={`lancamento-editar-${lancamento.id}`}
                                    >
                                      Editar
                                    </Link>
                                  ) : null}
                                </td>
                              </tr>
                            ))
                          : null}
                      </Fragment>
                    )
                  })}
                  {grupos.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="px-4 py-8 text-center text-slate-300">
                        Nenhuma sessão encontrada.
                      </td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </div>
          )}

          <Paginacao page={page} totalPages={totalPages} onChange={setPage} />
        </section>
        ) : null}
      </div>

      <SelecionarGuiaModal
        open={guiaModalAberto}
        onClose={() => setGuiaModalAberto(false)}
        termoInicial={termoInicialGuia}
        onSelecionar={(guia) => {
          setGuiaSelecionada(guia)
          setProfissionalId('')
          // Escolha manual: o rótulo "veio da leitura" deixa de valer. O aviso
          // de divergência, não — ele se recalcula e continua valendo para a
          // guia que acabou de ser escolhida à mão.
          setGuiaVeioDaLeitura(null)
        }}
      />

      <ConfirmarDivergenciaModal
        conferencia={divergenciaAConfirmar}
        descricao={divergenciaAConfirmar ? descreverDivergencia(divergenciaAConfirmar) : ''}
        enviando={confirmar.isPending}
        onCancelar={() => setDivergenciaAConfirmar(null)}
        onConfirmar={(justificativa) => void gravar(justificativa)}
      />

      <HtmlIsolado
        className="hidden print:block bg-white p-8 text-slate-950"
        html={printHtml}
      />
    </>
  )
}
