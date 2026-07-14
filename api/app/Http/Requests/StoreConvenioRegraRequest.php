<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreConvenioRegraRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['tipo_terapia'=>['required',Rule::in(['especializada','convencional','outro'])], 'frequencia_lancamento'=>['required',Rule::in(['diaria','semanal','mensal'])], 'qtd_autorizada_por_ciclo'=>['required','integer','min:1'], 'validade_senha_dias'=>['nullable','integer','min:1'], 'observacoes'=>['nullable','string'], 'vigente_desde'=>['required','date']]; } }
