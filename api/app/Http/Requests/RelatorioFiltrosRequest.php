<?php

namespace App\Http\Requests;

use App\Services\Relatorios\RelatorioPeriodo;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Filtros de qualquer aba de relatório — as quatro compartilham o recorte.
 *
 * A escolha de clínica é barrada em `authorize()`, e não em `rules()`, de
 * propósito: pedir dado de outra clínica não é campo mal preenchido, é
 * tentativa de acesso. 422 convidaria a tentar de novo com outro valor; 403 diz
 * o que é. E `authorize()` roda antes da validação, então nem chega a existir
 * mensagem de erro revelando quais ids de tenant existem.
 */
class RelatorioFiltrosRequest extends FormRequest
{
    /** Valor de `tenant_id` que significa "somar todas as clínicas". */
    public const TODOS = 'todos';

    /**
     * `has()`, e não `filled()`: mandar o parâmetro já é escolher clínica, e um
     * `tenant_id=` vazio vindo de usuário comum é sinal de front montando a URL
     * com um campo que não é dele. A tela simplesmente omite o parâmetro.
     */
    public function authorize(): bool
    {
        if (! $this->has('tenant_id')) {
            return true;
        }

        return (bool) $this->user()?->ehSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'de' => ['required', 'date_format:Y-m-d'],
            'ate' => ['required', 'date_format:Y-m-d', 'after_or_equal:de'],
            'granularidade' => ['nullable', Rule::in(RelatorioPeriodo::GRANULARIDADES)],
            'convenio_id' => ['nullable', 'integer', $this->existeNaClinica('convenios')],
            'especialidade_id' => ['nullable', 'integer', $this->existeNaClinica('especialidades')],
            'profissional_id' => ['nullable', 'integer', $this->existeNaClinica('profissionais')],
            'tenant_id' => $this->regrasDaClinica(),
            'comparar' => ['nullable', 'boolean'],
        ];
    }

    /**
     * O teto de dias não cabe numa regra declarativa: depende das duas datas
     * juntas. Só roda quando ambas passaram, para o usuário não receber "período
     * longo demais" sobre uma data que sequer é data.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['de', 'ate'])) {
                return;
            }

            $de = CarbonImmutable::parse($this->input('de'), RelatorioPeriodo::FUSO)->startOfDay();
            $ate = CarbonImmutable::parse($this->input('ate'), RelatorioPeriodo::FUSO)->startOfDay();

            if ($de->diffInDays($ate) + 1 > RelatorioPeriodo::MAX_DIAS) {
                $validator->errors()->add(
                    'ate',
                    'O período não pode ultrapassar '.RelatorioPeriodo::MAX_DIAS.' dias.',
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'de.required' => 'Informe a data inicial do período.',
            'ate.required' => 'Informe a data final do período.',
            'ate.after_or_equal' => 'A data final do período não pode ser anterior à inicial.',
        ];
    }

    public function periodo(): RelatorioPeriodo
    {
        return RelatorioPeriodo::entre(
            $this->input('de'),
            $this->input('ate'),
            $this->input('granularidade') ?: null,
        );
    }

    /**
     * A clínica do relatório: null quando são todas.
     *
     * Usuário comum nunca chega aqui com escolha — `authorize()` já barrou —,
     * então o valor dele é sempre o próprio `tenant_id`. Super admin sem escolha
     * também cai na própria clínica, que é o comportamento de todas as outras
     * telas.
     */
    public function tenantIdDoRelatorio(): ?int
    {
        $usuario = $this->user();

        if (! $usuario?->ehSuperAdmin()) {
            return (int) $usuario?->tenant_id;
        }

        $escolha = $this->input('tenant_id');

        if ($escolha === null || $escolha === '') {
            return (int) $usuario->tenant_id;
        }

        return $escolha === self::TODOS ? null : (int) $escolha;
    }

    /** @return array<int, mixed> */
    private function regrasDaClinica(): array
    {
        return $this->input('tenant_id') === self::TODOS
            ? ['nullable', 'string']
            : ['nullable', 'integer', Rule::exists('tenants', 'id')];
    }

    /**
     * Existe, e é da clínica consultada.
     *
     * Sem o recorte por tenant o 422 viraria um oráculo: id de convênio alheio
     * passaria na validação e id inexistente não, o que permite descobrir, um
     * número por vez, o que existe na base de outra clínica. Com "todas as
     * clínicas" (só super admin) o recorte não se aplica.
     */
    private function existeNaClinica(string $tabela): Exists
    {
        $tenantId = $this->tenantIdDoRelatorio();
        $regra = Rule::exists($tabela, 'id');

        return $tenantId === null
            ? $regra
            : $regra->where(fn ($query) => $query->where('tenant_id', $tenantId));
    }
}
