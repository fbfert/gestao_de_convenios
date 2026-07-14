<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreTabelaValorRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { $tenant=$this->user()?->tenant_id; return ['especialidade_id'=>['nullable','integer',Rule::exists('especialidades','id')->where(fn($q)=>$q->where('tenant_id',$tenant))], 'profissional_id'=>['nullable','integer',Rule::exists('profissionais','id')->where(fn($q)=>$q->where('tenant_id',$tenant))], 'valor'=>['required','numeric','min:0'], 'vigente_desde'=>['required','date']]; } }
