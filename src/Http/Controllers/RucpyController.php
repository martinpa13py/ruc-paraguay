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
    protected int $batchSize 		= 1000; // Configurable batch size for database inserts

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
        $downloadedFiles = 0;

        echo "Starting RUC data download and import process...\n";

        if (!Storage::disk('local')->exists($this->folder)) {
            Storage::disk('local')->makeDirectory($this->folder, 0775, true);
            echo "Created storage directory: {$this->folder}\n";
        }

        echo "Checking for new data files...\n";
        foreach (range(0, 9) as $i) {
            $filePath 	= "ruc{$i}.zip";
            $url 		= "{$this->url}{$filePath}";

            if ($this->isCached($filePath)) {
                echo "  Skipping {$filePath} (cached)\n";
                continue;
            }

            $newData = true;
            $downloadedFiles++;
            echo "  Downloading {$filePath}...\n";
            $client->get($url, ['sink' => "{$storagePath}/{$filePath}"]);
        }

        if (!$newData) {
            echo 'No new data to import. All files are cached.\n';
            return;
        }

        echo "Downloaded {$downloadedFiles} new files.\n";
        echo "Starting data extraction and import...\n";
        
        $extractStartTime = time();
        $this->extractAndImportData($storagePath);
        $extractTime = time() - $extractStartTime;

        echo "Data extraction and import completed in {$extractTime} seconds\n";
        echo "Total process completed in " . (time() - $startTime) . " seconds\n";
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

        $txtFiles = array_filter(Storage::disk('local')->files($this->folder), function($file) {
            return strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'txt';
        });

        $totalFiles = count($txtFiles);
        $currentFile = 0;

        foreach ($txtFiles as $txtFile) {
            $currentFile++;
            echo "Processing file {$currentFile}/{$totalFiles}: " . basename($txtFile) . "\n";
            $this->importDataFromTxt($txtFile);
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
        $batch = [];
        $totalRecords = 0;
        $processedRecords = 0;

        if (($file = fopen($localTxt, 'r')) !== false) {
            // Count total lines for progress reporting
            $totalLines = 0;
            while (fgets($file) !== false) {
                $totalLines++;
            }
            rewind($file);
            
            echo "  Total lines to process: {$totalLines}\n";
            
            while (($line = fgets($file)) !== false) {
                $data = $this->txt2ruc($line);
                if ($data) {
                    $batch[] = $data;
                    $processedRecords++;
                    
                    // Insert batch when it reaches the batch size
                    if (count($batch) >= $this->batchSize) {
                        $this->addBatch($batch);
                        $totalRecords += count($batch);
                        echo "  Processed {$processedRecords}/{$totalLines} lines, inserted {$totalRecords} records\n";
                        $batch = [];
                    }
                }
            }
            
            // Insert remaining records in the last batch
            if (!empty($batch)) {
                $this->addBatch($batch);
                $totalRecords += count($batch);
            }
            
            echo "  Completed: {$totalRecords} records inserted\n";
            fclose($file);
        }
    }

    public function addBatch(array $dataArray): bool
    {
        try {
            return RucParaguaySet::insert($dataArray);
        } catch (\Exception $e) {
            echo "Error inserting batch: " . $e->getMessage() . "\n";
            return false;
        }
    }

    public function setBatchSize(int $batchSize): void
    {
        $this->batchSize = $batchSize;
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

        return [
            'nro_ruc' 				=> trim($data[0] ?? ''),
            'denominacion' 			=> trim($data[1] ?? ''),
            'digito_verificador' 	=> trim($data[2] ?? ''),
            'ruc_anterior' 			=> trim($data[3] ?? ''),
            'estado' 			    => trim($data[4] ?? ''),
        ];
    }
}

