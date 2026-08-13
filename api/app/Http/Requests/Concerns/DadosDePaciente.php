<?php

namespace App\Http\Requests\Concerns;

use App\Models\PacienteTelefone;
use App\Rules\Cpf;
use Illuminate\Validation\Rule;

/**
 * Regras compartilhadas entre criar e editar paciente.
 *
 * Vive separado do ValidaCarteirinhaPorConvenio porque aquele trait já resolve
 * a carteirinha; aqui ficam CPF, telefones e datas — que precisam ser idênticos
 * nos dois requests, senão um cadastro válido deixa de poder ser salvo na
 * edição.
 */
trait DadosDePaciente
{
    /** @return array<string, mixed> */
    protected function regrasDeContato(): array
    {
        return [
            // Máscara é assunto de tela: o banco guarda só dígitos, senão
            // busca e comparação passam a depender de formatação.
            'cpf' => ['nullable', 'string', new Cpf],
            'data_nascimento' => ['nullable', 'date', 'before_or_equal:today'],
            'validade_carteirinha' => ['nullable', 'date'],

            'telefones' => ['sometimes', 'array', 'max:10'],
            'telefones.*.numero' => ['required', 'string', 'min:8', 'max:20'],
            'telefones.*.rotulo' => ['nullable', Rule::in(PacienteTelefone::ROTULOS)],
            'telefones.*.contato_nome' => ['nullable', 'string', 'max:120'],
            'telefones.*.principal' => ['sometimes', 'boolean'],
        ];
    }

    protected function mensagensDeContato(): array
    {
        return [
            'telefones.max' => 'São aceitos no máximo 10 telefones por paciente.',
            'telefones.*.numero.min' => 'Telefone incompleto: informe DDD e número.',
            'data_nascimento.before_or_equal' => 'A data de nascimento não pode estar no futuro.',
        ];
    }

    /** Deixa CPF e telefones só com dígitos antes de qualquer validação. */
    protected function limparContato(): void
    {
        if ($this->has('cpf')) {
            $cpf = preg_replace('/\D+/', '', (string) $this->input('cpf'));
            $this->merge(['cpf' => $cpf === '' ? null : $cpf]);
        }

        if (! is_array($this->input('telefones'))) {
            return;
        }

        $telefones = collect($this->input('telefones'))
            ->map(function ($telefone) {
                $telefone['numero'] = preg_replace('/\D+/', '', (string) ($telefone['numero'] ?? ''));

                return $telefone;
            })
            // Linha vazia do formulário não é erro de digitação: é o operador
            // que abriu um campo e desistiu.
            ->reject(fn ($telefone) => $telefone['numero'] === '')
            ->values()
            ->all();

        $this->merge(['telefones' => $telefones]);
    }
}
