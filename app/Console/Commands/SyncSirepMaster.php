<?php

namespace App\Console\Commands;

use App\Services\SirepMasterSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncSirepMaster extends Command
{
    protected $signature = 'sirep:sync-master {--only= : Sync scope: assy or carline}';

    protected $description = 'Sync master Assy and Carline data from SIREP shared API';

    public function handle(SirepMasterSyncService $service): int
    {
        $scope = strtolower((string) $this->option('only'));

        try {
            $result = match ($scope) {
                'assy' => ['assy' => $service->syncAssy()],
                'carline' => ['carline' => $service->syncCarlines()],
                '', 'all' => $service->syncMaster(),
                default => throw new \InvalidArgumentException('Option --only hanya menerima assy atau carline.'),
            };

            $this->info('Sync SIREP selesai.');
            $this->printResult($result);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Sync SIREP gagal: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function printResult(array $result): void
    {
        foreach ($result as $label => $stats) {
            if (isset($stats['assy'], $stats['carline'])) {
                $this->line(strtoupper($label));
                $this->printResult($stats);
                continue;
            }

            $this->line(sprintf(
                '%s: total=%d, created=%d, updated=%d, skipped=%d',
                str_replace('_', ' ', ucfirst($label)),
                $stats['total'] ?? 0,
                $stats['created'] ?? 0,
                $stats['updated'] ?? 0,
                $stats['skipped'] ?? 0
            ));

            foreach (array_slice($stats['errors'] ?? [], 0, 5) as $error) {
                $this->warn($error);
            }
        }
    }
}
