<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportLancamentosTranscricaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'profissional_id' => ['required', 'integer', 'exists:profissionais,id'],
            'transcricao' => ['required', 'string'],
            'confirmar_envio' => ['sometimes', 'boolean'],
            'pdf_registro_sessoes' => ['nullable', 'file', 'mimes:pdf'],
            'sessoes' => ['required_if:confirmar_envio,true', 'array'],
            'sessoes.*.data_sessao' => ['required_if:confirmar_envio,true', 'date'],
            'sessoes.*.hora_inicio' => ['nullable', 'date_format:H:i'],
            'sessoes.*.hora_fim' => ['nullable', 'date_format:H:i'],
            'sessoes.*.acompanhante' => ['nullable', 'string'],
            'sessoes.*.resumo_atividades' => ['nullable', 'string'],
        ];
    }
}
