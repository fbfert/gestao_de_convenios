<?php

namespace App\Services\Sessoes;

use App\Models\Guia;
use App\Models\PacienteArquivo;
use App\Support\GuiaStatus;
use App\Support\ImagemParaPdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * As folhas de registro de sessões de uma guia.
 *
 * Uma guia de dez sessões costuma ser impressa em duas vias e preenchida em
 * partes — por isso são folhas no plural, e não um arquivo só. Todas vão para
 * a operadora na finalização, uma por vez.
 *
 * Ficam na pasta do paciente, como qualquer anexo dele, com `metadata.guia_id`
 * amarrando cada folha à remessa que a originou. É esse vínculo que permite,
 * meses depois, dizer de qual guia cada folha veio.
 */
class FolhasDeRegistroService
{
    public const TIPO = 'registro_sessoes';

    /** Prefixo de carteirinha da regional que exige a folha para lançar. */
    private const REGIONAL_QUE_EXIGE_FOLHA = '0220';

    /**
     * @return Collection<int, PacienteArquivo>
     */
    public function daGuia(Guia $guia): Collection
    {
        return PacienteArquivo::query()
            ->where('paciente_id', $guia->paciente_id)
            ->where('tipo', self::TIPO)
            ->get()
            // `metadata` é JSON e o filtro por chave dentro dele varia entre
            // SQLite e MariaDB; a quantidade de folhas de um paciente é
            // pequena, então filtrar em PHP sai mais barato que manter duas
            // formas de consulta.
            ->filter(fn (PacienteArquivo $arquivo) => (int) ($arquivo->metadata['guia_id'] ?? 0) === (int) $guia->id)
            ->values();
    }

    /**
     * @param  array<int, UploadedFile>|UploadedFile  $arquivos
     * @return array<int, PacienteArquivo>
     */
    public function anexar(Guia $guia, array|UploadedFile $arquivos): array
    {
        $lista = is_array($arquivos) ? $arquivos : [$arquivos];

        return array_map(fn (UploadedFile $arquivo) => $this->guardar($guia, $arquivo), $lista);
    }

    /**
     * Remover só vale enquanto a guia não foi finalizada na operadora.
     *
     * Depois disso a folha deixa de ser rascunho e passa a ser o comprovante
     * do que foi enviado — apagá-la apagaria a prova de uma remessa que já
     * aconteceu.
     */
    public function remover(Guia $guia, PacienteArquivo $arquivo): void
    {
        $this->garantirRemovivel($guia);

        if ((int) ($arquivo->metadata['guia_id'] ?? 0) !== (int) $guia->id) {
            throw ValidationException::withMessages([
                'arquivo' => ['Esta folha não é desta guia.'],
            ]);
        }

        Storage::disk('local')->delete($arquivo->path);
        $arquivo->delete();
    }

    public function travada(Guia $guia): bool
    {
        return $guia->status === GuiaStatus::FINALIZED;
    }

    /**
     * A guia de cada folha da lista, numa consulta só — para a pasta do
     * paciente dizer de qual guia é cada folha sem uma consulta por linha.
     *
     * @param  iterable<PacienteArquivo>  $arquivos
     * @return array<int, Guia> indexado pelo id do arquivo
     */
    public function guiasDasFolhas(iterable $arquivos): array
    {
        $folhas = collect($arquivos)->filter(fn (PacienteArquivo $arquivo) => $arquivo->tipo === self::TIPO);
        $guias = Guia::query()
            ->whereIn('id', $folhas->map(fn (PacienteArquivo $folha) => (int) ($folha->metadata['guia_id'] ?? 0))->filter()->unique()->values())
            ->get(['id', 'numero_guia', 'status'])
            ->keyBy('id');

        return $folhas
            ->mapWithKeys(fn (PacienteArquivo $folha) => [$folha->id => $guias->get((int) ($folha->metadata['guia_id'] ?? 0))])
            ->filter()
            ->all();
    }

    /**
     * Remoção pela pasta do paciente, que não passa pela guia.
     *
     * Sem isto a pasta era um desvio da regra de remover(): a folha de uma
     * guia já finalizada aparecia ali com "Remover" e o servidor apagava sem
     * perguntar de que guia ela era.
     */
    public function garantirRemovivelPelaPasta(PacienteArquivo $arquivo): void
    {
        if ($arquivo->tipo !== self::TIPO) {
            return;
        }

        $guia = $this->guiasDasFolhas([$arquivo])[$arquivo->id] ?? null;

        if ($guia !== null) {
            $this->garantirRemovivel($guia);
        }
    }

    private function garantirRemovivel(Guia $guia): void
    {
        if ($this->travada($guia)) {
            throw ValidationException::withMessages([
                'arquivo' => ['A guia já foi finalizada na operadora — a folha é o comprovante do envio e não pode ser removida.'],
            ]);
        }
    }

    /**
     * A regional 0220 exige a folha para lançar. Uma basta para confirmar: as
     * outras vias podem ser anexadas depois, antes de finalizar.
     */
    public function regiaoExigeFolha(?string $numeroCartao): bool
    {
        if ($numeroCartao === null || $numeroCartao === '') {
            return false;
        }

        return str_starts_with(preg_replace('/\D+/', '', $numeroCartao) ?? '', self::REGIONAL_QUE_EXIGE_FOLHA);
    }

    private function guardar(Guia $guia, UploadedFile $arquivo): PacienteArquivo
    {
        $pasta = "pacientes/{$guia->paciente_id}/registro-sessoes";
        $nomeOriginal = basename($arquivo->getClientOriginalName());

        /*
         * Foto e captura da webcam viram PDF. A folha é o comprovante que vai
         * à operadora, e a regional 0220 a exige em PDF — guardar a imagem
         * crua deixaria para a finalização descobrir que o formato não serve.
         * O tipo vem do conteúdo, não da extensão nem do header.
         */
        if (ImagemParaPdf::ehImagem((string) $arquivo->getMimeType())) {
            $path = "{$pasta}/".Str::uuid()->toString().'.pdf';
            Storage::disk('local')->put($path, ImagemParaPdf::converter($arquivo->getRealPath()));
            $nomeOriginal = pathinfo($nomeOriginal, PATHINFO_FILENAME).'.pdf';
        } else {
            $path = $arquivo->storeAs(
                $pasta,
                Str::uuid()->toString().'.'.$arquivo->getClientOriginalExtension(),
                'local',
            );
        }

        return PacienteArquivo::query()->create([
            'tenant_id' => $guia->tenant_id,
            'paciente_id' => $guia->paciente_id,
            'tipo' => self::TIPO,
            'nome_original' => $nomeOriginal,
            // Do arquivo gravado, nunca do header multipart: o valor volta cru
            // no Content-Type do download.
            'mime' => Storage::disk('local')->mimeType($path) ?: 'application/octet-stream',
            'path' => $path,
            'metadata' => [
                'guia_id' => $guia->id,
                'numero_guia' => $guia->numero_guia,
                'enviado_por' => auth()->id(),
            ],
        ]);
    }
}
