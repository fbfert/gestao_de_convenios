<?php

use App\Models\Cid;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

/**
 * CIDs comuns em clinicas de terapia infantil/TEA, prontos para uso — o
 * operador nao comeca com a lista vazia. Cada tenant mantem a sua dai em
 * diante pelo cadastro normal; isto so semeia o ponto de partida.
 */
return new class extends Migration
{
    private const CIDS = [
        'F84.0' => 'Autismo infantil',
        'F84.1' => 'Autismo atípico',
        'F84.5' => 'Síndrome de Asperger',
        'F90.0' => 'Distúrbio da atividade e da atenção (TDAH)',
        'F90.1' => 'Transtorno hipercinético de conduta',
        'F80.0' => 'Transtorno específico da articulação da fala',
        'F80.1' => 'Transtorno da linguagem expressiva',
        'F80.2' => 'Transtorno da linguagem receptiva',
        'F80.9' => 'Transtorno do desenvolvimento da fala/linguagem não especificado',
        'F70' => 'Retardo mental leve',
        'F71' => 'Retardo mental moderado',
        'F79' => 'Retardo mental não especificado',
        'F81.0' => 'Transtorno específico de leitura (dislexia)',
        'F81.2' => 'Transtorno específico da habilidade em aritmética (discalculia)',
        'F82' => 'Transtorno específico do desenvolvimento motor (dispraxia)',
        'F93.0' => 'Transtorno de ansiedade de separação',
        'F94.0' => 'Mutismo eletivo',
        'R62.0' => 'Atraso do desenvolvimento',
    ];

    public function up(): void
    {
        foreach (Tenant::query()->get() as $tenant) {
            foreach (self::CIDS as $codigo => $descricao) {
                Cid::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'codigo' => $codigo],
                    ['descricao' => $descricao, 'ativo' => true],
                );
            }
        }
    }

    public function down(): void
    {
        Cid::query()->whereIn('codigo', array_keys(self::CIDS))->delete();
    }
};
