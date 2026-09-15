<?php

namespace App\Support;

/**
 * Catálogo dos drivers de credencial de automação.
 *
 * Fonte única dos campos de cada fornecedor: a API valida contra ele e o front
 * renderiza o formulário a partir dele. Um convênio novo entra acrescentando uma
 * entrada aqui, sem tela nova — na linha do ADR-03, que manda tratar regra de
 * convênio como dado e não como código espalhado.
 *
 * Catálogo fixo em código, e não tabela, pelo mesmo motivo do
 * `PermissionCatalog` (ADR-14): a lista muda junto com o código que sabe usar
 * cada driver, então versioná-la no banco só criaria duas verdades.
 *
 * O `driver` daqui NÃO é `convenios.connector_driver`. Aquele é o interruptor da
 * automação, com doze consumidores — entre eles o `VerificarGuiasDiarioJob`, que
 * deixa de conferir manualmente as guias do convênio quando ele está ligado.
 * Este é só o rótulo de quais campos a credencial tem.
 */
class ConvenioDriverCatalog
{
    public const UNIMED_RDA = 'unimed_rda';

    public const SCSAUDE = 'scsaude';

    /**
     * @var array<string, array{rotulo: string, implementado: bool, aviso?: string, campos: array<int, array{chave: string, rotulo: string, tipo: string, obrigatorio: bool, dica?: string}>}>
     */
    private const DRIVERS = [
        self::UNIMED_RDA => [
            'rotulo' => 'Unimed RDA',
            'implementado' => true,
            'campos' => [
                [
                    'chave' => 'login',
                    'rotulo' => 'Login',
                    'tipo' => 'text',
                    'obrigatorio' => true,
                    'dica' => 'Usuário do portal RDA da Unimed.',
                ],
                [
                    'chave' => 'password',
                    'rotulo' => 'Senha',
                    'tipo' => 'password',
                    'obrigatorio' => true,
                    'dica' => 'Deixe em branco para manter a senha já gravada.',
                ],
                [
                    'chave' => 'base_url',
                    'rotulo' => 'URL base',
                    'tipo' => 'url',
                    'obrigatorio' => false,
                    'dica' => 'Só preencha para apontar para um ambiente diferente do padrão.',
                ],
                [
                    'chave' => 'nome_contratado',
                    'rotulo' => 'Nome do contratado',
                    'tipo' => 'text',
                    'obrigatorio' => false,
                    'dica' => 'Como a clínica aparece no portal, quando o login atende a mais de uma.',
                ],
            ],
        ],

        /*
         * Sem campos de propósito.
         *
         * O plano confirmou que o SC Saúde tem WebService, mas a clínica está em
         * fila de implementação e não recebeu documentação. Desenhar hoje um
         * formulário de login e senha para ele seria chute — e chute que o
         * operador preencheria achando que liga alguma coisa.
         */
        self::SCSAUDE => [
            'rotulo' => 'SC Saúde',
            'implementado' => false,
            'aviso' => 'A forma de autenticação do SC Saúde ainda não foi definida. '
                .'Assim que a documentação do WebService chegar, os campos aparecem aqui.',
            'campos' => [],
        ],
    ];

    /** @return array<int, string> */
    public static function drivers(): array
    {
        return array_keys(self::DRIVERS);
    }

    public static function existe(?string $driver): bool
    {
        return $driver !== null && array_key_exists($driver, self::DRIVERS);
    }

    public static function rotulo(?string $driver): ?string
    {
        return self::DRIVERS[$driver]['rotulo'] ?? null;
    }

    public static function implementado(?string $driver): bool
    {
        return (bool) (self::DRIVERS[$driver]['implementado'] ?? false);
    }

    public static function aviso(?string $driver): ?string
    {
        return self::DRIVERS[$driver]['aviso'] ?? null;
    }

    /**
     * Campos do driver, na ordem em que o formulário deve renderizá-los.
     *
     * Driver desconhecido devolve lista vazia em vez de estourar: a tela mostra
     * o mesmo aviso do driver sem campos, que é o comportamento útil quando um
     * convênio antigo guarda um driver que saiu do catálogo.
     *
     * @return array<int, array{chave: string, rotulo: string, tipo: string, obrigatorio: bool, dica?: string}>
     */
    public static function campos(?string $driver): array
    {
        return self::DRIVERS[$driver]['campos'] ?? [];
    }

    /** @return array<int, string> */
    public static function chaves(?string $driver): array
    {
        return array_column(self::campos($driver), 'chave');
    }

    /** @return array<int, string> */
    public static function chavesObrigatorias(?string $driver): array
    {
        return array_column(
            array_filter(self::campos($driver), fn (array $campo) => $campo['obrigatorio']),
            'chave',
        );
    }

    /**
     * Chaves cujo valor nunca sai do servidor.
     *
     * Usada tanto pelo resource (devolve `preenchido: true`, nunca o valor)
     * quanto pela request (campo em branco preserva o que já está gravado).
     *
     * @return array<int, string>
     */
    public static function chavesSecretas(?string $driver): array
    {
        return array_column(
            array_filter(self::campos($driver), fn (array $campo) => $campo['tipo'] === 'password'),
            'chave',
        );
    }

    /**
     * O catálogo como o front o consome.
     *
     * @return array<string, mixed>
     */
    public static function paraResposta(?string $driver): array
    {
        return [
            'driver' => $driver,
            'rotulo' => self::rotulo($driver),
            'implementado' => self::implementado($driver),
            'aviso' => self::aviso($driver),
            'campos' => self::campos($driver),
        ];
    }
}
