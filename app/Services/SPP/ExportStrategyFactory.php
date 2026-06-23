<?php

namespace App\Services\SPP;

use App\Services\SPP\Strategies\ExportStrategyInterface;
use App\Services\SPP\Strategies\SaiExportStrategy;
use App\Services\SPP\Strategies\TycExportStrategy;
use App\Services\SPP\Strategies\YcExportStrategy;
use App\Services\SPP\Strategies\YnaExportStrategy;

class ExportStrategyFactory
{
    public function make(string $customerCode): ?ExportStrategyInterface
    {
        return match (strtoupper($customerCode)) {
            'SAI' => new SaiExportStrategy(),
            'TYC' => new TycExportStrategy(),
            'YC'  => new YcExportStrategy(),
            'YNA' => new YnaExportStrategy(),
            default => null,
        };
    }
}
