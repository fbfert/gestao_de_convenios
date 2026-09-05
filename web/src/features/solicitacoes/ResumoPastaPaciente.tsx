import { usePacienteArquivos } from '../pacientes/usePacienteArquivos'
import { DOCUMENTO_LABELS, TODOS_DOCUMENTOS } from '../../lib/documentoTipos'

/**
 * Aviso automático assim que o paciente é escolhido: o que já existe na pasta
 * dele pode ser reaproveitado (via "Usar da pasta do paciente") na etapa de
 * anexos, logo depois que a solicitação for criada — aqui é só o avisado,
 * escolher e anexar de fato acontece lá.
 */
export function ResumoPastaPaciente({ pacienteId }: { pacienteId: number | null }) {
  const arquivosQuery = usePacienteArquivos(pacienteId)

  if (!pacienteId || arquivosQuery.isLoading || arquivosQuery.isError) {
    return null
  }

  const arquivos = arquivosQuery.data ?? []
  const porTipo = TODOS_DOCUMENTOS.map((tipo) => ({
    tipo,
    total: arquivos.filter((arquivo) => arquivo.tipo === tipo).length,
  })).filter(({ total }) => total > 0)

  if (porTipo.length === 0) {
    return null
  }

  return (
    <div
      className="rounded-2xl border border-cyan-300/20 bg-cyan-400/5 px-4 py-3 text-corpo text-cyan-50"
      data-testid="resumo-pasta-paciente"
    >
      <p className="font-semibold">Este paciente já tem documentos cadastrados</p>
      <p className="mt-1 text-cyan-50/80">
        {porTipo
          .map(({ tipo, total }) => `${total} ${DOCUMENTO_LABELS[tipo]}${total > 1 ? 's' : ''}`)
          .join(' · ')}{' '}
        na pasta — você vai poder reaproveitar ao concluir o cadastro, na etapa de anexos.
      </p>
    </div>
  )
}
