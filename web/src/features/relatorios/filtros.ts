import { useCallback, useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'
import type { Aba } from './tipos'

/**
 * O recorte do relatório vive na URL.
 *
 * Relatório é feito para ser mandado para alguém — "olha esse número". Sem URL
 * compartilhável isso vira captura de tela, e captura de tela não deixa o outro
 * mexer nos filtros. É o mesmo motivo de `useListaNaUrl` nas listagens, e aqui
 * pesa mais: são sete campos, e reproduzir todos à mão é inviável.
 */

export const PRESETS = ['hoje', '7dias', '30dias', 'mes_atual', 'mes_anterior', 'personalizado'] as const

export type Preset = (typeof PRESETS)[number]

export const ROTULO_DO_PRESET: Record<Preset, string> = {
  hoje: 'Hoje',
  '7dias': '7 dias',
  '30dias': '30 dias',
  mes_atual: 'Mês atual',
  mes_anterior: 'Mês anterior',
  personalizado: 'Personalizado',
}

export type FiltrosRelatorio = {
  aba: Aba | ''
  preset: Preset
  de: string
  ate: string
  granularidade: '' | 'dia' | 'semana' | 'mes'
  convenio_id: string
  especialidade_id: string
  profissional_id: string
  comparar: string
  tenant_id: string
}

const iso = (data: Date) => {
  // `toISOString()` converteria para UTC e, às 21h em São Paulo, devolveria o
  // dia seguinte. As partes locais são a data que a pessoa vê no relógio dela.
  const mes = String(data.getMonth() + 1).padStart(2, '0')
  const dia = String(data.getDate()).padStart(2, '0')

  return `${data.getFullYear()}-${mes}-${dia}`
}

/**
 * As datas de um preset.
 *
 * Espelha `RelatorioPeriodo::doPreset()` da API, que é a definição canônica —
 * o backend exige `de`/`ate` e não aceita o nome do preset, então a conta
 * acontece aqui. Mudou lá, muda aqui: a divergência apareceria como um dia a
 * mais ou a menos, que é exatamente o tipo de erro que ninguém nota.
 */
export function periodoDoPreset(preset: Preset, hoje = new Date()): { de: string; ate: string } {
  const base = new Date(hoje.getFullYear(), hoje.getMonth(), hoje.getDate())
  const menos = (dias: number) => new Date(base.getFullYear(), base.getMonth(), base.getDate() - dias)

  switch (preset) {
    case 'hoje':
      return { de: iso(base), ate: iso(base) }
    // Sete dias CONTANDO hoje, como a pessoa conta.
    case '7dias':
      return { de: iso(menos(6)), ate: iso(base) }
    case '30dias':
      return { de: iso(menos(29)), ate: iso(base) }
    case 'mes_atual':
      return { de: iso(new Date(base.getFullYear(), base.getMonth(), 1)), ate: iso(base) }
    case 'mes_anterior': {
      const primeiro = new Date(base.getFullYear(), base.getMonth() - 1, 1)
      const ultimo = new Date(base.getFullYear(), base.getMonth(), 0)

      return { de: iso(primeiro), ate: iso(ultimo) }
    }
    default:
      return { de: iso(menos(29)), ate: iso(base) }
  }
}

const PADRAO: FiltrosRelatorio = {
  aba: '',
  preset: '30dias',
  ...periodoDoPreset('30dias'),
  granularidade: '',
  convenio_id: '',
  especialidade_id: '',
  profissional_id: '',
  comparar: '',
  tenant_id: '',
}

export function useFiltrosNaUrl() {
  const [searchParams, setSearchParams] = useSearchParams()

  const filtros = useMemo<FiltrosRelatorio>(() => {
    const lido = { ...PADRAO }

    for (const chave of Object.keys(PADRAO) as (keyof FiltrosRelatorio)[]) {
      const valor = searchParams.get(chave)

      if (valor !== null) {
        lido[chave] = valor as never
      }
    }

    // Preset sem datas na URL (link curto, digitado à mão) resolve as datas
    // agora. As datas na URL mandam: é o que faz o link reproduzir o MESMO
    // relatório amanhã, e não "os últimos 30 dias de quando for aberto".
    if (lido.preset !== 'personalizado' && (!searchParams.get('de') || !searchParams.get('ate'))) {
      Object.assign(lido, periodoDoPreset(lido.preset))
    }

    return lido
  }, [searchParams])

  const aplicar = useCallback(
    (parciais: Partial<FiltrosRelatorio>) => {
      setSearchParams(
        (anterior) => {
          const params = new URLSearchParams(anterior)

          for (const [chave, valor] of Object.entries(parciais)) {
            if (valor === '' || valor === undefined) {
              params.delete(chave)
            } else {
              params.set(chave, String(valor))
            }
          }

          return params
          // `replace` como nas listagens: trocar filtro não pode empilhar uma
          // entrada de histórico por clique, senão o Voltar do navegador vira
          // inútil nesta tela.
        },
        { replace: true },
      )
    },
    [setSearchParams],
  )

  /** Troca o preset e recalcula as datas junto — os dois sempre andam juntos. */
  const aplicarPreset = useCallback(
    (preset: Preset) => {
      aplicar(preset === 'personalizado' ? { preset } : { preset, ...periodoDoPreset(preset) })
    },
    [aplicar],
  )

  return { filtros, aplicar, aplicarPreset }
}

/** Os parâmetros que vão para a API — sem os campos que são só da tela. */
export function paramsDaConsulta(filtros: FiltrosRelatorio): Record<string, string> {
  const params: Record<string, string> = { de: filtros.de, ate: filtros.ate }

  if (filtros.granularidade) params.granularidade = filtros.granularidade
  if (filtros.convenio_id) params.convenio_id = filtros.convenio_id
  if (filtros.especialidade_id) params.especialidade_id = filtros.especialidade_id
  if (filtros.profissional_id) params.profissional_id = filtros.profissional_id
  if (filtros.comparar === '1') params.comparar = '1'
  // `tenant_id` só existe para super admin, e mandá-lo vazio é 403 na API:
  // ela trata a presença do parâmetro como escolha de clínica.
  if (filtros.tenant_id) params.tenant_id = filtros.tenant_id

  return params
}
