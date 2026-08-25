export type LancamentoPrintTemplate = {
  id: number
  chave: string
  nome: string
  html: string
  ativo: boolean
  placeholders: string[]
  updated_at: string | null
}

export type LancamentoPrintTemplateForm = {
  nome: string
  html: string
  ativo: boolean
}

export type TemplateSessao = {
  numero: string
  data_sessao: string
  hora_inicio: string
  hora_fim: string
  acompanhante: string
  resumo_atividades: string
}

export type TemplateData = {
  guia_numero: string
  clinica: string
  paciente: string
  numero_cartao: string
  profissional_executante: string
  terapia_aplicada: string
  data_impressao: string
  sessoes: TemplateSessao[]
}

export const defaultBlankTemplateData: TemplateData = {
  guia_numero: '__________________',
  clinica: '__________________',
  paciente: '__________________',
  numero_cartao: '__________________',
  profissional_executante: '__________________',
  terapia_aplicada: '__________________',
  data_impressao: new Date().toLocaleDateString('pt-BR'),
  sessoes: Array.from({ length: 8 }, (_, index) => ({
    numero: String(index + 1),
    data_sessao: '__________________',
    hora_inicio: '__________________',
    hora_fim: '__________________',
    acompanhante: '__________________',
    resumo_atividades: '____________________________________________',
  })),
}

export const sampleTemplateData: TemplateData = {
  guia_numero: '521381566206',
  clinica: 'Centro Neuro Kids Ltda',
  paciente: 'Ana Paula Ribeiro',
  numero_cartao: '0220 090000 551.330-8',
  profissional_executante: 'Mariana Souza',
  terapia_aplicada: 'ABA - Avaliação Neuropsicológica',
  data_impressao: new Date().toLocaleDateString('pt-BR'),
  sessoes: [
    {
      numero: '1',
      data_sessao: '08/04/2026',
      hora_inicio: '14:50',
      hora_fim: '15:40',
      acompanhante: 'Bruno Marinho',
      resumo_atividades: 'Aplicação de testes neuropsicológicos.',
    },
    {
      numero: '2',
      data_sessao: '09/04/2026',
      hora_inicio: '14:50',
      hora_fim: '15:40',
      acompanhante: 'Bruno Marinho',
      resumo_atividades: 'Denver e atividades de desenvolvimento.',
    },
  ],
}

function escapeHtml(value: string) {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

function replaceSimplePlaceholders(template: string, data: Record<string, string>) {
  return template.replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, (_, key: string) =>
    escapeHtml(data[key] ?? ''),
  )
}

export function renderLancamentoPrintTemplate(html: string, data: TemplateData) {
  const renderedBlocks = html.replace(
    /\{\{#sessoes\}\}([\s\S]*?)\{\{\/sessoes\}\}/g,
    (_, block: string) =>
      data.sessoes
        .map((sessao) =>
          replaceSimplePlaceholders(block, {
            numero: sessao.numero,
            data_sessao: sessao.data_sessao,
            hora_inicio: sessao.hora_inicio,
            hora_fim: sessao.hora_fim,
            acompanhante: sessao.acompanhante,
            resumo_atividades: sessao.resumo_atividades,
          }),
        )
        .join(''),
  )

  return replaceSimplePlaceholders(renderedBlocks, {
    guia_numero: data.guia_numero,
    clinica: data.clinica,
    paciente: data.paciente,
    numero_cartao: data.numero_cartao,
    profissional_executante: data.profissional_executante,
    terapia_aplicada: data.terapia_aplicada,
    data_impressao: data.data_impressao,
  })
}

const BLANK_FIELD = '__________________'

export type TemplateGuiaOrigin = {
  numero_guia: string | null
  tipo_terapia: string
  paciente?: { nome: string; carteirinha: string } | null
  especialidade?: { nome: string } | null
  /** "Profissional executante" escolhido na criação da guia. */
  profissional?: { nome: string } | null
}

/**
 * Monta o modelo de impressão com os dados da guia já conhecidos antes de
 * qualquer sessão existir (guia, paciente, cartão, terapia, profissional
 * executante) — só a tabela de sessões (datas/horas) ainda não existe nesse
 * momento, e fica em branco para preencher à mão, igual ao modelo em branco
 * puro.
 */
export function buildGuiaTemplateData(guia: TemplateGuiaOrigin, clinica: string): TemplateData {
  return {
    guia_numero: guia.numero_guia || BLANK_FIELD,
    clinica: clinica || BLANK_FIELD,
    paciente: guia.paciente?.nome || BLANK_FIELD,
    numero_cartao: guia.paciente?.carteirinha || BLANK_FIELD,
    profissional_executante: guia.profissional?.nome || BLANK_FIELD,
    // "Terapia aplicada" é a especialidade (ex.: Fisioterapia) — `tipo_terapia`
    // é só a classificação administrativa da guia (especializada/convencional).
    terapia_aplicada: guia.especialidade?.nome || BLANK_FIELD,
    data_impressao: new Date().toLocaleDateString('pt-BR'),
    sessoes: defaultBlankTemplateData.sessoes,
  }
}

export type TemplateAntecipacaoOrigin = {
  paciente?: { nome: string; carteirinha?: string | null } | null
  guia?: { numero_guia: string | null; tipo_terapia: string | null } | null
  especialidade?: { nome: string } | null
  lancamentos?: Array<{
    data_sessao: string | null
    hora_inicio: string | null
    hora_fim: string | null
    acompanhante?: string | null
    resumo_atividades?: string | null
    profissional?: { nome: string } | null
  }>
}

function formatDataSessao(value: string | null | undefined) {
  if (!value) {
    return '—'
  }

  const [ano, mes, dia] = value.split('-')
  return dia && mes && ano ? `${dia}/${mes}/${ano}` : value
}

/**
 * Monta o modelo de impressão já preenchido com os dados reais da
 * antecipação (guia, paciente, sessões já lançadas) — em vez do modelo em
 * branco (`defaultBlankTemplateData`), que é só um formulário para o
 * profissional preencher à mão.
 */
export function buildFilledTemplateData(
  antecipacao: TemplateAntecipacaoOrigin,
  clinica: string,
): TemplateData {
  const lancamentos = [...(antecipacao.lancamentos ?? [])].sort((a, b) =>
    (a.data_sessao ?? '').localeCompare(b.data_sessao ?? ''),
  )

  const profissionais = Array.from(
    new Set(
      lancamentos
        .map((lancamento) => lancamento.profissional?.nome)
        .filter((nome): nome is string => Boolean(nome)),
    ),
  )

  return {
    guia_numero: antecipacao.guia?.numero_guia || '—',
    clinica: clinica || '—',
    paciente: antecipacao.paciente?.nome || '—',
    numero_cartao: antecipacao.paciente?.carteirinha || '—',
    profissional_executante: profissionais.join(', ') || '—',
    terapia_aplicada: antecipacao.guia?.tipo_terapia || antecipacao.especialidade?.nome || '—',
    data_impressao: new Date().toLocaleDateString('pt-BR'),
    sessoes: lancamentos.length
      ? lancamentos.map((lancamento, index) => ({
          numero: String(index + 1),
          data_sessao: formatDataSessao(lancamento.data_sessao),
          hora_inicio: lancamento.hora_inicio || '—',
          hora_fim: lancamento.hora_fim || '—',
          acompanhante: lancamento.acompanhante || '—',
          resumo_atividades: lancamento.resumo_atividades || '—',
        }))
      : [
          {
            numero: '—',
            data_sessao: '—',
            hora_inicio: '—',
            hora_fim: '—',
            acompanhante: '—',
            resumo_atividades: 'Nenhuma sessão registrada nesta antecipação ainda.',
          },
        ],
  }
}

export function asPreviewDocument(bodyHtml: string) {
  return `<!doctype html><html><head><meta charset="utf-8"><style>body{margin:24px;background:#fff}</style></head><body>${bodyHtml}</body></html>`
}
