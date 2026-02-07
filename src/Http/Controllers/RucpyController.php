<?php

namespace martinpa13py\RUCParaguay\Http\Controllers;

use App\Http\Controllers\Controller;
use martinpa13py\RUCParaguay\Models\RucParaguaySet;
use martinpa13py\RUCParaguay\Services\RUCParaguayUpdater;

class RucpyController extends Controller
{
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

    /**
     * Descarga e importa los datos de RUC desde DNIT.
     * Para compatibilidad con versiones anteriores, delega al servicio RUCParaguayUpdater.
     */
    public function download(?bool $force = null, ?int $batchSize = null, ?int $limit = null): void
    {
        $updater = app(RUCParaguayUpdater::class);

        if ($force !== null) {
            $updater->setForce($force);
        }
        if ($batchSize !== null) {
            $updater->setBatchSize($batchSize);
        }
        if ($limit !== null) {
            $updater->setLimit($limit);
        }

        $updater->run();
    }
}

