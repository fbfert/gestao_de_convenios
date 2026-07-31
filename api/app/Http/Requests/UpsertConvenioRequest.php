<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertConvenioRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['nome' => ['required', 'string', 'max:255'], 'descricao' => ['nullable', 'string'], 'connector_type' => ['required', Rule::in(['manual', 'api', 'scraping'])], 'connector_driver' => ['nullable', Rule::in(['unimed_rda'])], 'ativo' => ['required', 'boolean']]; }
}
