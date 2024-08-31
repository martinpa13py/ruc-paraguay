<?php

namespace martinpa13py\RUCParaguay\Http\Controllers;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;
use martinpa13py\RUCParaguay\Models\RucParaguaySet;
use ZipArchive;

class RucpyController extends Controller
{
    protected string $url 			= 'https://www.dnit.gov.py/documents/20123/976078/';
    protected string $folder 		= 'ruc-paraguay';
    protected int $cacheDuration 	= 3600 * 24 * 2; // 2 days

    public function search(string $busqueda)
    {
        return RucParaguaySet::query()
            ->where('nro_ruc', 'LIKE', "%{$busqueda}%")
            ->orWhere('denominacion', 'LIKE', "%{$busqueda}%")
            ->orWhere('ruc_anterior', 'LIKE', "%{$busqueda}%")
        ->get();
    }

    public function add(array $data): bool
    {
        return RucParaguaySet::create($data) !== null;
    }

    public function download()
    {
        $startTime 		= time();
        $storagePath 	= Storage::disk('local')->path($this->folder);
        $client 		= new Client();
        $newData 		= false;

        if (!Storage::disk('local')->exists($this->folder)) {
            Storage::disk('local')->makeDirectory($this->folder, 0775, true);
        }

        foreach (range(0, 9) as $i) {
            $filePath 	= "ruc{$i}.zip";
            $url 		= "{$this->url}{$filePath}";

            if ($this->isCached($filePath)) {
                continue;
            }

            $newData = true;
            $client->get($url, ['sink' => "{$storagePath}/{$filePath}"]);
        }

        if (!$newData) {
            echo 'No new data to import';
            return;
        }

        $this->extractAndImportData($storagePath);

        echo "\nCompleted in " . (time() - $startTime) . " seconds\n";
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

        foreach (Storage::disk('local')->files($this->folder) as $txtFile) {
            if (strtolower(pathinfo($txtFile, PATHINFO_EXTENSION)) === 'txt') {
                $this->importDataFromTxt($txtFile);
            }
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

    protected function importDataFromTxt(string $txtFile): void
    {
        $localTxt = Storage::disk('local')->path($txtFile);

        if (($file = fopen($localTxt, 'r')) !== false) {
            while (($line = fgets($file)) !== false) {
                $data = $this->txt2ruc($line);
                if ($data) {
                    $this->add($data);
                }
            }
            fclose($file);
        }
    }

    protected function txt2ruc(string $line): ?array
    {
		
        $data = explode('|', $line);

        if(count($data) > 6) {
            return null;
        }

        return array_merge([
            'nro_ruc' 				=> null,
            'denominacion' 			=> null,
            'digito_verificador' 	=> null,
            'ruc_anterior' 			=> null,
            'estado' 			    => null,
        ], array_filter([
            'nro_ruc' 				=> $data[0],
            'denominacion' 			=> $data[1],
            'digito_verificador' 	=> $data[2],
            'ruc_anterior' 			=> $data[3],
            'estado' 			    => $data[4],
        ], 'trim'));
    }
}

