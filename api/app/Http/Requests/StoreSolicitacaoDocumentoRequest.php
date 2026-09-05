<?php

namespace App\Http\Requests;

use App\Concerns\ValidaTipoESolicitacaoItemDoDocumento;
use App\Models\PacienteArquivo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSolicitacaoDocumentoRequest extends FormRequest
{
    use ValidaTipoESolicitacaoItemDoDocumento;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 5 MB é o teto aceito pelo portal da Unimed (worker-unimed/src/operations/gerarGuia.js),
            // então recusamos aqui para o operador descobrir na hora do anexo, não no envio.
            'arquivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,gif', 'max:5120'],
            'tipo' => ['required', 'string', Rule::in(PacienteArquivo::TIPOS)],
            'solicitacao_item_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'arquivo.mimes' => 'O anexo precisa ser uma imagem (JPG, PNG, GIF) ou PDF.',
            'arquivo.max' => 'O anexo não pode passar de 5 MB, limite aceito pelo portal da Unimed.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validarTipoEItem($validator, (string) $this->input('tipo'), $this->input('solicitacao_item_id'));
        });
    }
}
