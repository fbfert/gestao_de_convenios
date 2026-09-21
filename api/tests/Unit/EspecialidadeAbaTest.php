<?php

namespace Tests\Unit;

use App\Services\Sessoes\EspecialidadeAba;
use PHPUnit\Framework\TestCase;

class EspecialidadeAbaTest extends TestCase
{
    /**
     * @dataProvider nomesQueSaoAba
     */
    public function test_reconhece_terapia_aba(string $nome): void
    {
        $this->assertTrue(EspecialidadeAba::ehAba($nome), "Esperava reconhecer '{$nome}' como ABA.");
    }

    /**
     * @dataProvider nomesQueNaoSaoAba
     */
    public function test_nao_confunde_outras_especialidades(?string $nome): void
    {
        $this->assertFalse(EspecialidadeAba::ehAba($nome), "Não esperava reconhecer '{$nome}' como ABA.");
    }

    /** @return array<string, array{string}> */
    public static function nomesQueSaoAba(): array
    {
        return [
            'nome canônico' => ['Terapia ABA'],
            'especialidade composta' => ['Fisioterapia ABA'],
            'outra composta' => ['Psicologia ABA'],
            'caixa baixa' => ['terapia aba'],
            'caixa mista' => ['Fonoaudiologia Aba'],
            'com pontos' => ['Terapia A.B.A.'],
            'com pontos e sem espaço' => ['Psicopedagogia A.B.A'],
            'entre parênteses' => ['Psicologia (ABA)'],
            'com acento no resto do nome' => ['Educação Física ABA'],
        ];
    }

    /** @return array<string, array{string|null}> */
    public static function nomesQueNaoSaoAba(): array
    {
        return [
            'especialidade sem ABA' => ['Fisioterapia'],
            'só as letras dentro de outra palavra' => ['Abagail'],
            'letras no meio' => ['Terapia Cabana'],
            'prefixo' => ['Abandono'],
            'vazio' => [''],
            'nulo' => [null],
        ];
    }
}
