<?php

namespace App\Services\Utilities;

use App\Models\ProductionWeek;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WeekResolverService
{
    public function applyProductionWeeks(array $mapped, int $customerId): array
    {
        // Deteksi apakah customer ini adalah SAI.
        // SAI tidak menggunakan tabel production_weeks — periode (bulan/tahun/minggu)
        // sudah ditentukan langsung dari Excel oleh SAIMapper, sehingga DB lookup dilewati.
        $customer = \App\Models\Customer::find($customerId);
        $isSai    = $customer && strtoupper(trim($customer->code)) === 'SAI';

        foreach ($mapped as &$item) {
            if (empty($item['etd'])) {
                continue;
            }

            // ── Bypass khusus SAI ─────────────────────────────────────────────
            if ($isSai) {
                $item['production_week_id'] = null;

                // Bersihkan format week: "W2" → integer 2
                if (isset($item['week']) && is_string($item['week']) && preg_match('/^W([1-5])$/i', trim($item['week']), $m)) {
                    $item['week'] = (int) $m[1];
                }

                // month (mis. "MAY") dan year (mis. 2026) sudah diisi oleh SAIMapper,
                // tidak perlu diubah — pertahankan apa adanya.
                continue;
            }

            // ── Resolusi normal via database untuk customer selain SAI ─────────
            $weekId = WeekGenerator::resolveEtdMapping($customerId, $item['etd']);

            if ($weekId) {
                $week = ProductionWeek::find($weekId);

                if ($week) {
                    $item['production_week_id'] = $week->id;
                    $item['week'] = $week->week_no;
                    $item['month'] = $week->month_name;
                    $item['year'] = $week->year;
                }

                continue;
            }

            $this->fillMissingPeriodFromDate($item);
        }
        unset($item);

        return $mapped;
    }

    private function fillMissingPeriodFromDate(array &$item): void
    {
        $date = Carbon::parse($item['etd']);

        $item['production_week_id'] = $item['production_week_id'] ?? null;
        $item['week'] = empty($item['week']) ? ceil($date->day / 7) : $item['week'];
        $item['month'] = empty($item['month']) ? strtoupper($date->shortMonthName) : $item['month'];
        $item['year'] = empty($item['year']) ? $date->year : $item['year'];

        Log::warning('Production week fallback used', [
            'etd' => $item['etd'],
            'week' => $item['week'],
            'month' => $item['month'],
        ]);
    }
}
