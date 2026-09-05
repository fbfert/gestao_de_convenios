<?php

namespace App\Http\Requests;

use App\Concerns\ValidaTipoESolicitacaoItemDoDocumento;
use App\Models\PacienteArquivo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Anexa à solicitação um arquivo que já existe na pasta do paciente, sem
 * upload — mesmas regras de tipo/item de StoreSolicitacaoDocumentoRequest,
 * só que o tipo vem do arquivo apontado, não de um campo do formulário.
 */
class VincularSolicitacaoDocumentoRequest extends FormRequest
{
    use ValidaTipoESolicitacaoItemDoDocumento;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'paciente_arquivo_id' => ['required', 'integer'],
            'solicitacao_item_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $solicitacao = $this->route('solicitacao');
            $arquivoId = $this->input('paciente_arquivo_id');

            if (! $arquivoId) {
                return;
            }

            $arquivo = PacienteArquivo::query()
                ->where('tenant_id', $solicitacao?->tenant_id)
                ->whereKey($arquivoId)
                ->first();

            if (! $arquivo) {
                $validator->errors()->add('paciente_arquivo_id', 'Arquivo não encontrado.');

                return;
            }

            if ($arquivo->paciente_id !== $solicitacao?->paciente_id) {
                $validator->errors()->add('paciente_arquivo_id', 'Este arquivo pertence a outro paciente.');

                return;
            }

            $this->validarTipoEItem($validator, $arquivo->tipo, $this->input('solicitacao_item_id'));
        });
    }
}
