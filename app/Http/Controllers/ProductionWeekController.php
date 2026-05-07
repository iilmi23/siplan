<?php

namespace App\Http\Controllers;

use App\Models\ProductionWeek;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductionWeekController extends Controller
{
    /**
     * Tampilkan list production weeks aggregated per month
     */
    public function index(Request $request)
    {
        $query = ProductionWeek::query();

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('month_name', 'like', "%{$search}%")
                    ->orWhere('year', 'like', "%{$search}%");
            });
        }

        $productionWeeksAggregated = $query
            ->selectRaw('customer_id, year, month_number, month_name, MIN(week_start) as start_date, MAX(end_date) as end_date, COUNT(*) as total_weeks')
            ->groupBy('customer_id', 'year', 'month_number', 'month_name')
            ->orderBy('year', 'desc')
            ->orderBy('month_number', 'asc')
            ->paginate(15)
            ->withQueryString();

        $productionWeeksAggregated->setCollection(
            $productionWeeksAggregated->getCollection()->map(function ($month) {
                $startDate = Carbon::parse($month->start_date)->toDateString();
                return [
                    'customer_id' => $month->customer_id,
                    'year' => (int) $month->year,
                    'month_number' => (int) $month->month_number,
                    'month_name' => $month->month_name,
                    'start_date' => $startDate,
                    'end_date' => $this->resolveMonthlyEndDate($month->end_date, (int) $month->year, (int) $month->month_number),
                    'total_weeks' => (int) $month->total_weeks,
                ];
            })
        );

        $availableYears = ProductionWeek::select('year')->distinct()->orderBy('year', 'desc')->pluck('year');
        $customers = Customer::orderBy('name')->get();

        // Statistik untuk summary cards
        $editedCount = ProductionWeek::whereHas('etdMappings', function ($q) {
            $q->where('is_edited', true);
        })->count();

        $globalWeeksCount = ProductionWeek::whereNull('customer_id')->count();
        $customerWeeksCount = ProductionWeek::whereNotNull('customer_id')->count();

        return Inertia::render('Master/ProductionWeek/Index', [
            'productionWeeks' => $productionWeeksAggregated,
            'customers' => $customers,
            'availableYears' => $availableYears,
            'filters' => $request->only(['customer_id', 'year', 'search']),
            'stats' => [
                'total' => $productionWeeksAggregated->total(),
                'edited_count' => $editedCount,
                'global_weeks_count' => $globalWeeksCount,
                'customer_weeks_count' => $customerWeeksCount,
            ],
            'flash' => session('flash') ?: [
                'success' => session('success'),
                'warning' => session('warning'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Form create production week
     */
    public function create()
    {
        $customers = Customer::orderBy('name')->get();

        return Inertia::render('Master/ProductionWeek/Create', [
            'customers' => $customers,
        ]);
    }

    public function importPage()
    {
        $customers = Customer::orderBy('name')->get();

        return Inertia::render('Master/ProductionWeek/Import', [
            'customers' => $customers,
        ]);
    }

    /**
     * Simpan production week baru
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'year' => 'required|integer|min:2020|max:2030',
            'month_number' => 'required|integer|min:1|max:12',
            'month_name' => 'required|string|max:3',
            'week_start' => 'required|date',
            'end_date' => 'required|date|after_or_equal:week_start',
            'num_weeks' => 'required|integer|in:3,4,5',
        ]);

        $customerId = $validated['customer_id'] ?? null;
        $monthNames = [
            1 => 'JAN',
            2 => 'FEB',
            3 => 'MAR',
            4 => 'APR',
            5 => 'MAY',
            6 => 'JUN',
            7 => 'JUL',
            8 => 'AUG',
            9 => 'SEP',
            10 => 'OCT',
            11 => 'NOV',
            12 => 'DEC',
        ];
        $monthName = $monthNames[(int) $validated['month_number']];

        $existsQuery = ProductionWeek::where('year', $validated['year'])
            ->where('month_number', $validated['month_number']);

        if (!empty($customerId)) {
            $existsQuery->where('customer_id', $customerId);
        } else {
            $existsQuery->whereNull('customer_id');
        }

        if ($existsQuery->exists()) {
            return redirect()->back()
                ->with('error', 'Production week untuk bulan tersebut sudah ada.')
                ->withInput();
        }

        $firstWeekStart = Carbon::parse($validated['week_start']);
        $endDate = Carbon::parse($validated['end_date']);
        $totalWeeks = (int) $validated['num_weeks'];
        $lastWeekStart = $firstWeekStart->copy()->addWeeks($totalWeeks - 1);

        if ($endDate->lt($lastWeekStart)) {
            throw ValidationException::withMessages([
                'end_date' => "End date minimal {$lastWeekStart->toDateString()} untuk {$totalWeeks} week.",
            ]);
        }

        DB::transaction(function () use ($validated, $customerId, $firstWeekStart, $endDate, $totalWeeks, $monthName) {
            for ($weekNo = 1; $weekNo <= $totalWeeks; $weekNo++) {
                $weekStart = $firstWeekStart->copy()->addWeeks($weekNo - 1);
                $weekEnd = $weekNo === $totalWeeks
                    ? $endDate->copy()
                    : $weekStart->copy()->addDays(6);

                ProductionWeek::create([
                    'customer_id' => $customerId,
                    'year' => (int) $validated['year'],
                    'month_number' => (int) $validated['month_number'],
                    'month_name' => $monthName,
                    'week_no' => $weekNo,
                    'week_start' => $weekStart->toDateString(),
                    'end_date' => $weekEnd->toDateString(),
                    'num_weeks' => $totalWeeks,
                ]);
            }
        });

        return redirect()->route('production-week.index')
            ->with('success', 'Production Week bulanan berhasil ditambahkan.');
    }

    /**
     * Form edit production week
     */
    public function edit(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
            'month' => 'required|integer|min:1|max:12',
            'customer_id' => 'nullable|integer|exists:customers,id',
        ]);

        $monthQuery = ProductionWeek::query()
            ->where('year', $validated['year'])
            ->where('month_number', $validated['month']);

        if (!empty($validated['customer_id'])) {
            $monthQuery->where('customer_id', $validated['customer_id']);
        } else {
            $monthQuery->whereNull('customer_id');
        }

        $productionWeek = $monthQuery->orderBy('week_no')->firstOrFail();
        $monthSummary = [
            'customer_id' => $productionWeek->customer_id,
            'year' => $productionWeek->year,
            'month_number' => $productionWeek->month_number,
            'month_name' => $productionWeek->month_name,
            'start_date' => (clone $monthQuery)->min('week_start'),
            'end_date' => $this->resolveMonthlyEndDate(
                (clone $monthQuery)->max('end_date'),
                (int) $productionWeek->year,
                (int) $productionWeek->month_number,
            ),
            'total_weeks' => (int) $monthQuery->count(),
            'num_weeks' => (int) $monthQuery->count(),
        ];

        $customers = Customer::orderBy('name')->get();

        return Inertia::render('Master/ProductionWeek/Edit', [
            'productionWeek' => $monthSummary,
            'customers' => $customers,
        ]);
    }

    /**
     * Update production week
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'year' => 'required|integer|min:2020|max:2030',
            'month_number' => 'required|integer|min:1|max:12',
            'month_name' => 'required|string|max:3',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'num_weeks' => 'required|integer|in:3,4,5',
        ]);

        $targetYear = (int) $request->query('year');
        $targetMonth = (int) $request->query('month');
        $targetCustomerId = $request->query('customer_id');

        $query = ProductionWeek::where('year', $targetYear)
            ->where('month_number', $targetMonth);

        if ($targetCustomerId !== null && $targetCustomerId !== '') {
            $query->where('customer_id', $targetCustomerId);
        } else {
            $query->whereNull('customer_id');
        }

        $weeks = (clone $query)->orderBy('week_no')->get();

        if ($weeks->isEmpty()) {
            return redirect()->back()
                ->with('error', 'Production week bulan tersebut tidak ditemukan.')
                ->withInput();
        }

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->startOfDay();
        $totalWeeks = (int) $validated['num_weeks'];
        $lastWeekStart = $startDate->copy()->addWeeks($totalWeeks - 1);

        if ($endDate->lt($lastWeekStart)) {
            throw ValidationException::withMessages([
                'end_date' => "End date minimal {$lastWeekStart->toDateString()} untuk {$totalWeeks} week.",
            ]);
        }

        $weeksToRemove = $weeks->filter(fn ($week) => (int) $week->week_no > $totalWeeks);

        if ($weeksToRemove->isNotEmpty() && $weeksToRemove->contains(fn ($week) => $week->etdMappings()->exists())) {
            return redirect()->back()
                ->with('error', 'Jumlah week tidak bisa dikurangi karena week yang akan dihapus masih dipakai di ETD Mapping.')
                ->withInput();
        }

        $targetExists = ProductionWeek::query()
            ->where('year', $validated['year'])
            ->where('month_number', $validated['month_number'])
            ->when(
                !empty($validated['customer_id']),
                fn ($target) => $target->where('customer_id', $validated['customer_id']),
                fn ($target) => $target->whereNull('customer_id')
            )
            ->whereNotIn('id', $weeks->pluck('id'))
            ->exists();

        if ($targetExists) {
            return redirect()->back()
                ->with('error', 'Production week tujuan sudah ada.')
                ->withInput();
        }

        DB::transaction(function () use ($weeks, $validated, $startDate, $endDate, $totalWeeks, $weeksToRemove) {
            $weeksToRemove->each->delete();

            $existingWeeks = $weeks
                ->reject(fn ($week) => (int) $week->week_no > $totalWeeks)
                ->keyBy('week_no');

            for ($weekNo = 1; $weekNo <= $totalWeeks; $weekNo++) {
                $index = $weekNo - 1;
                $weekStart = $startDate->copy()->addWeeks($index);
                $weekEnd = $index === $totalWeeks - 1
                    ? $endDate->copy()
                    : $weekStart->copy()->addDays(6);

                $payload = [
                    'customer_id' => $validated['customer_id'] ?: null,
                    'year' => (int) $validated['year'],
                    'month_number' => (int) $validated['month_number'],
                    'month_name' => $validated['month_name'],
                    'week_no' => $weekNo,
                    'week_start' => $weekStart->toDateString(),
                    'end_date' => $weekEnd->toDateString(),
                    'num_weeks' => $totalWeeks,
                ];

                $week = $existingWeeks->get($weekNo);

                if ($week) {
                    $week->update($payload);
                } else {
                    ProductionWeek::create($payload);
                }
            }
        });

        return redirect()->route('production-week.index')
            ->with('success', 'Production Week bulan tersebut berhasil diupdate!');
    }

    /**
     * Hapus production week
     */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
            'month' => 'required|integer|min:1|max:12',
            'customer_id' => 'nullable|integer|exists:customers,id',
        ]);

        $query = ProductionWeek::where('year', $validated['year'])
            ->where('month_number', $validated['month']);

        if (!empty($validated['customer_id'])) {
            $query->where('customer_id', $validated['customer_id']);
        } else {
            $query->whereNull('customer_id');
        }

        if ((clone $query)->whereHas('etdMappings')->exists()) {
            return redirect()->back()
                ->with('error', 'Bulan tersebut tidak bisa dihapus karena masih dipakai di ETD Mapping');
        }

        $query->delete();

        return redirect()->route('production-week.index')
            ->with('success', 'Production Week bulan tersebut berhasil dihapus!');
    }

    // ===================== IMPORT EXCEL =====================

    /**
     * Import production weeks dari Excel
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        try {
            $file = $request->file('file');
            $customerId = $request->filled('customer_id') ? $request->customer_id : null;

            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Hapus header row
            array_shift($rows);

            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            $monthMap = [
                'JAN' => 1,
                'FEB' => 2,
                'MAR' => 3,
                'APR' => 4,
                'MAY' => 5,
                'JUN' => 6,
                'JUL' => 7,
                'AUG' => 8,
                'SEP' => 9,
                'OCT' => 10,
                'NOV' => 11,
                'DEC' => 12
            ];

            foreach ($rows as $rowIndex => $row) {
                if (empty(array_filter($row))) continue;

                try {
                    $monthName = trim(strtoupper($row[0] ?? ''));
                    $rangeText = trim($row[1] ?? '');
                    $year = trim($row[2] ?? '');
                    $totalWeeksInput = trim($row[3] ?? '');

                    if (empty($monthName) || empty($rangeText) || empty($year) || empty($totalWeeksInput)) {
                        $errorCount++;
                        $errors[] = "Row " . ($rowIndex + 2) . ": Missing required data";
                        continue;
                    }

                    $monthNumber = $monthMap[$monthName] ?? null;
                    if (!$monthNumber) {
                        $errorCount++;
                        $errors[] = "Row " . ($rowIndex + 2) . ": Invalid month '{$monthName}'";
                        continue;
                    }

                    // Parse range untuk mendapatkan start dan end date
                    preg_match('/(\d+)\/([A-Z]+)\s*~\s*(\d+)\/([A-Z]+)\s*\((\d+)\)/', $rangeText, $matches);
                    if (empty($matches)) {
                        $errorCount++;
                        $errors[] = "Row " . ($rowIndex + 2) . ": Invalid range format '{$rangeText}'";
                        continue;
                    }

                    $startDay = $matches[1];
                    $startMonth = $monthMap[$matches[2]] ?? null;
                    $endDay = $matches[3];
                    $endMonth = $monthMap[$matches[4]] ?? null;
                    $rangeWeeks = (int) $matches[5];

                    if (!$startMonth || !$endMonth) {
                        $errorCount++;
                        $errors[] = "Row " . ($rowIndex + 2) . ": Invalid month in range";
                        continue;
                    }

                    $startDate = Carbon::parse("{$year}-{$startMonth}-{$startDay}");
                    $endDate = Carbon::parse("{$year}-{$endMonth}-{$endDay}");

                    $numWeeks = (int) $totalWeeksInput;

                    if ($numWeeks < 3 || $numWeeks > 5) {
                        $errorCount++;
                        $errors[] = "Row " . ($rowIndex + 2) . ": Total weeks must be 3, 4, or 5";
                        continue;
                    }

                    if ($rangeWeeks !== $numWeeks) {
                        $errorCount++;
                        $errors[] = "Row " . ($rowIndex + 2) . ": Total weeks does not match range text";
                        continue;
                    }

                    $existsQuery = ProductionWeek::where('year', $year)
                        ->where('month_number', $monthNumber);

                    if (!empty($customerId)) {
                        $existsQuery->where('customer_id', $customerId);
                    } else {
                        $existsQuery->whereNull('customer_id');
                    }

                    if ($existsQuery->exists()) {
                        $errorCount++;
                        $errors[] = "Row " . ($rowIndex + 2) . ": Duplicate entry for {$monthName} {$year}";
                        continue;
                    }

                    DB::transaction(function () use ($customerId, $year, $monthNumber, $monthName, $startDate, $endDate, $numWeeks) {
                        for ($weekNo = 1; $weekNo <= $numWeeks; $weekNo++) {
                            $weekStart = $startDate->copy()->addWeeks($weekNo - 1);
                            $weekEnd = $weekNo === $numWeeks
                                ? $endDate->copy()
                                : $weekStart->copy()->addDays(6);

                            ProductionWeek::create([
                                'customer_id' => $customerId,
                                'year' => (int) $year,
                                'month_number' => $monthNumber,
                                'month_name' => $monthName,
                                'week_no' => $weekNo,
                                'week_start' => $weekStart->toDateString(),
                                'end_date' => $weekEnd->toDateString(),
                                'num_weeks' => $numWeeks,
                            ]);
                        }
                    });

                    $successCount += $numWeeks;
                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
                }
            }

            $message = "Import completed: {$successCount} weeks imported successfully.";
            if ($errorCount > 0) {
                $message .= " {$errorCount} records failed.";
                return redirect()->route('production-week.index')
                    ->with('warning', $message)
                    ->with('import_errors', array_slice($errors, 0, 10));
            }

            return redirect()->route('production-week.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to import file: ' . $e->getMessage());
        }
    }

    /**
     * Download template Excel
     */
    public function downloadTemplate()
    {
        $templatePath = storage_path('app/templates/production-week-template.xlsx');

        if (!file_exists(storage_path('app/templates'))) {
            mkdir(storage_path('app/templates'), 0777, true);
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Bulan');
        $sheet->setCellValue('B1', 'Range (Start ~ End (Total Weeks))');
        $sheet->setCellValue('C1', 'Tahun');
        $sheet->setCellValue('D1', 'Jumlah Minggu');

        $sheet->setCellValue('A2', 'JAN');
        $sheet->setCellValue('B2', '05/JAN ~ 30/JAN (4)');
        $sheet->setCellValue('C2', '2026');
        $sheet->setCellValue('D2', '4');

        $sheet->setCellValue('A3', 'FEB');
        $sheet->setCellValue('B3', '02/FEB ~ 27/FEB (4)');
        $sheet->setCellValue('C3', '2026');
        $sheet->setCellValue('D3', '4');

        $sheet->setCellValue('A4', 'MAR');
        $sheet->setCellValue('B4', '02/MAR ~ 31/MAR (4)');
        $sheet->setCellValue('C4', '2026');
        $sheet->setCellValue('D4', '4');

        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0']
            ]
        ];
        $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($templatePath);

        return response()->download($templatePath, 'production-week-template.xlsx');
    }

    private function resolveMonthlyEndDate($storedEndDate, int $year, int $monthNumber): string
    {
        $calendarEndDate = Carbon::createFromDate($year, $monthNumber, 1)->endOfMonth();

        if (empty($storedEndDate)) {
            return $calendarEndDate->toDateString();
        }

        $endDate = $storedEndDate instanceof Carbon
            ? $storedEndDate->copy()
            : Carbon::parse($storedEndDate);

        return $endDate->lte($calendarEndDate)
            ? $endDate->toDateString()
            : $calendarEndDate->toDateString();
    }

}
