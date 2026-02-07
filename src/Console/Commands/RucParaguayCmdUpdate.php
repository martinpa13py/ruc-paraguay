<?php

namespace martinpa13py\RUCParaguay\Console\Commands;

use Illuminate\Console\Command;
use martinpa13py\RUCParaguay\Services\RUCParaguayUpdater;

class RucParaguayCmdUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ruc:update
                            {--force : Forzar descarga borrando archivos en caché antes de descargar}
                            {--batch-size=1000 : Cantidad de registros a insertar por lote (mínimo 100)}
                            {--limit= : Límite máximo de registros a importar (útil para pruebas)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza los datos de RUC de Paraguay descargando e importando desde DNIT';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('========== INICIANDO ==========');
        $this->info('========== DESCARGANDO ==========');

        $updater = app(RUCParaguayUpdater::class);
        $updater->setOutput(fn(string $msg) => $this->line($msg));

        if ($this->option('force')) {
            $updater->setForce(true);
        }

        $updater->setBatchSize((int) $this->option('batch-size'));

        if ($this->option('limit')) {
            $limit = (int) $this->option('limit');
            if ($limit > 0) {
                $updater->setLimit($limit);
                $this->info("Límite de importación: {$limit} registros");
            }
        }

        $updater->run();

        $this->info('========== FINALIZADO ==========');

        return Command::SUCCESS;
    }
}
