<?php

namespace martinpa13py\RUCParaguay\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;
use martinpa13py\RUCParaguay\Models\RucParaguaySet;
use ZipArchive;

class RUCParaguayUpdater
{
    protected string $url = 'https://www.dnit.gov.py/documents/20123/976078/';
    protected string $folder = 'ruc-paraguay';
    protected int $cacheDuration = 3600 * 24 * 2; // 2 días
    protected int $batchSize = 1000;
    protected ?int $limit = null;
    protected bool $force = false;
    protected $output;

    public function __construct(?callable $output = null)
    {
        $this->output = $output ?? function (string $msg) {
            echo $msg . "\n";
        };
    }

    public function setForce(bool $force): self
    {
        $this->force = $force;
        return $this;
    }

    public function setBatchSize(int $batchSize): self
    {
        $this->batchSize = max(100, $batchSize);
        return $this;
    }

    public function setLimit(?int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function setOutput(callable $output): self
    {
        $this->output = $output;
        return $this;
    }

    protected function out(string $message): void
    {
        ($this->output)($message);
    }

    public function run(): void
    {
        $startTime = time();
        $storagePath = Storage::disk('local')->path($this->folder);
        $client = new Client();
        $newData = false;
        $downloadedFiles = 0;

        $this->out('Iniciando proceso de descarga e importación de datos RUC...');

        if (!Storage::disk('local')->exists($this->folder)) {
            Storage::disk('local')->makeDirectory($this->folder, 0775, true);
            $this->out("Directorio creado: {$this->folder}");
        }

        if ($this->force) {
            $this->clearCache();
            $this->out('Caché limpiada (--force). Todos los archivos serán descargados nuevamente.');
        }

        $this->out('Buscando archivos de datos nuevos...');
        foreach (range(0, 9) as $i) {
            $filePath = "ruc{$i}.zip";
            $url = "{$this->url}{$filePath}";

            if (!$this->force && $this->isCached($filePath)) {
                $this->out("  Saltando {$filePath} (en caché)");
                continue;
            }

            $newData = true;
            $downloadedFiles++;
            $this->out("  Descargando {$filePath}...");
            $client->get($url, ['sink' => "{$storagePath}/{$filePath}"]);
        }

        if (!$newData) {
            $this->out('No hay datos nuevos para importar. Todos los archivos están en caché.');
            return;
        }

        $this->out("Archivos descargados: {$downloadedFiles}");
        $this->out('Iniciando extracción e importación...');

        $extractStartTime = time();
        $this->extractAndImportData($storagePath);
        $extractTime = time() - $extractStartTime;

        $this->out("Extracción e importación completada en {$extractTime} segundos");
        $this->out('Proceso total completado en ' . (time() - $startTime) . ' segundos');
    }

    public function clearCache(): void
    {
        $files = Storage::disk('local')->files($this->folder);
        foreach ($files as $file) {
            Storage::disk('local')->delete($file);
        }
        $this->out('Archivos de caché eliminados.');
    }

    protected function isCached(string $filePath): bool
    {
        if (Storage::disk('local')->exists("{$this->folder}/{$filePath}")) {
            $lastModified = Storage::disk('local')->lastModified("{$this->folder}/{$filePath}");
            return (time() - $lastModified) < $this->cacheDuration;
        }
        return false;
    }

    protected function extractAndImportData(string $storagePath): void
    {
        $this->extractZipFiles($storagePath);
        RucParaguaySet::truncate();

        $txtFiles = array_filter(Storage::disk('local')->files($this->folder), function ($file) {
            return strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'txt';
        });

        $totalFiles = count($txtFiles);
        $currentFile = 0;
        $totalInserted = 0;

        foreach ($txtFiles as $txtFile) {
            if ($this->limit !== null && $totalInserted >= $this->limit) {
                $this->out("Límite de {$this->limit} registros alcanzado. Deteniendo importación.");
                break;
            }

            $currentFile++;
            $this->out("Procesando archivo {$currentFile}/{$totalFiles}: " . basename($txtFile));
            $inserted = $this->importDataFromTxt($txtFile, $totalInserted);
            $totalInserted += $inserted;
        }
    }

    protected function extractZipFiles(string $storagePath): void
    {
        foreach (Storage::disk('local')->files($this->folder) as $zipFile) {
            if (strtolower(pathinfo($zipFile, PATHINFO_EXTENSION)) === 'zip') {
                $this->extractZip($zipFile, $storagePath);
            }
        }
    }

    protected function extractZip(string $zipFile, string $storagePath): void
    {
        $localZip = Storage::disk('local')->path($zipFile);
        $zip = new ZipArchive;

        if ($zip->open($localZip) === true) {
            $zip->extractTo($storagePath);
            $zip->close();
        } else {
            Storage::disk('local')->delete($zipFile);
        }
    }

    protected function importDataFromTxt(string $txtFile, int $alreadyInserted): int
    {
        $localTxt = Storage::disk('local')->path($txtFile);
        $batch = [];
        $totalRecords = 0;
        $processedRecords = 0;

        if (($file = fopen($localTxt, 'r')) === false) {
            return 0;
        }

        $totalLines = 0;
        while (fgets($file) !== false) {
            $totalLines++;
        }
        rewind($file);

        $this->out("  Líneas totales a procesar: {$totalLines}");

        while (($line = fgets($file)) !== false) {
            if ($this->limit !== null && ($alreadyInserted + $totalRecords) >= $this->limit) {
                break;
            }

            $data = $this->txt2ruc($line);
            if ($data) {
                $batch[] = $data;
                $processedRecords++;

                if (count($batch) >= $this->batchSize) {
                    RucParaguaySet::insert($batch);
                    $totalRecords += count($batch);
                    $this->out("  Procesadas {$processedRecords}/{$totalLines} líneas, insertados {$totalRecords} registros");
                    $batch = [];
                }
            }
        }

        if (!empty($batch)) {
            if ($this->limit !== null) {
                $remaining = $this->limit - ($alreadyInserted + $totalRecords);
                $batch = array_slice($batch, 0, max(0, $remaining));
            }
            if (!empty($batch)) {
                RucParaguaySet::insert($batch);
                $totalRecords += count($batch);
            }
        }

        $this->out("  Completado: {$totalRecords} registros insertados");
        fclose($file);

        return $totalRecords;
    }

    protected function txt2ruc(string $line): ?array
    {
        $line = trim($line);
        if (empty($line)) {
            return null;
        }

        $data = explode('|', $line);

        if (count($data) < 5) {
            return null;
        }

        $digitoVerificador = trim($data[2] ?? '');
        if (!empty($digitoVerificador)) {
            $digitoVerificador = preg_replace('/[^0-9]/', '', $digitoVerificador);
            $digitoVerificador = substr($digitoVerificador, 0, 1);
            if (empty($digitoVerificador)) {
                $digitoVerificador = '';
            }
        }

        return [
            'nro_ruc' => trim($data[0] ?? ''),
            'denominacion' => trim($data[1] ?? ''),
            'digito_verificador' => $digitoVerificador,
            'ruc_anterior' => trim($data[3] ?? ''),
            'estado' => trim($data[4] ?? ''),
        ];
    }
}
