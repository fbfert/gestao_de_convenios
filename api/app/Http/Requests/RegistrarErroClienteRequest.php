<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

/**
 * O relato de um erro que aconteceu no navegador de alguém.
 *
 * `authorize()` devolve true porque a rota é pública de propósito: um erro na
 * tela de login também precisa chegar, e ali não há sessão. O que protege a
 * rota é o throttle, esta validação, e o fato de nada do corpo ser interpretado
 * — vai inteiro para o log estruturado.
 */
class RegistrarErroClienteRequest extends FormRequest
{
    /**
     * Pilha maior que isto não acrescenta nada e só engorda o log. O corte
     * acontece aqui, e não no cliente: o cliente já trunca, mas quem garante o
     * limite é quem grava.
     */
    public const LIMITE_STACK = 4000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1000'],
            'stack' => ['nullable', 'string', 'max:'.self::LIMITE_STACK],
            'componentStack' => ['nullable', 'string', 'max:'.self::LIMITE_STACK],
            'url' => ['nullable', 'string', 'max:2000'],
            'userAgent' => ['nullable', 'string', 'max:500'],
            'occurredAt' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * Relato recusado também é notícia.
     *
     * Até 24/09/2026 a recusa era um 422 e nada mais: o erro que a clínica viu na
     * tela podia desaparecer sem deixar sinal algum, e foi o que me fez procurar
     * o problema no lugar errado. Um relato que não passou na validação é
     * exatamente o relato que alguém vai ligar perguntando.
     *
     * `Log::error` e não `Log::warning`: produção roda com `LOG_LEVEL=error`, e
     * um `warning` existiria no código sem existir no arquivo.
     *
     * Etiqueta própria, `erro-cliente-recusado`, para o `grep` de `erro-cliente`
     * continuar contando só os erros de verdade.
     *
     * Grava TAMANHOS, não conteúdo. Se a recusa foi por pilha grande demais, o
     * que interessa é saber quanto veio — despejar trinta mil caracteres no log
     * por causa de um payload recusado seria o próprio problema. E a rota é
     * pública: o que entra aqui pode não ter vindo da clínica.
     */
    protected function failedValidation(Validator $validator): void
    {
        Log::error('erro-cliente-recusado', [
            'motivos' => $validator->errors()->toArray(),
            'tamanhos' => $this->tamanhosRecebidos(),
            'url' => is_string($this->input('url')) ? mb_substr($this->input('url'), 0, 2000) : null,
            'ip' => $this->ip(),
        ]);

        parent::failedValidation($validator);
    }

    /**
     * Quantos caracteres vieram em cada campo — o bastante para saber por que a
     * validação recusou, sem o conteúdo.
     *
     * @return array<string, int|string>
     */
    private function tamanhosRecebidos(): array
    {
        $tamanhos = [];

        foreach (['message', 'stack', 'componentStack', 'url', 'userAgent', 'occurredAt'] as $campo) {
            $valor = $this->input($campo);

            $tamanhos[$campo] = match (true) {
                $valor === null => 'ausente',
                is_string($valor) => mb_strlen($valor),
                default => 'nao-e-texto',
            };
        }

        return $tamanhos;
    }
}
