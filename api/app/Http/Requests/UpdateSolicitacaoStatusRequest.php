<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSolicitacaoStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 'approved' fica de fora de propósito: só o sistema grava esse valor
            // (aprovação real da operadora), ver SolicitacaoService::sincronizarStatusComGuias.
            'status' => ['required', Rule::in(['under_review', 'ready_for_automation', 'denied'])],
        ];
    }
}
