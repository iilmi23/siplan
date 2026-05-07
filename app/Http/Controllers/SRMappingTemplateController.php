<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SRMappingTemplate;
use App\Services\SR\GenericTemplateMapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SRMappingTemplateController extends Controller
{
    public function index()
    {
        return Inertia::render('Master/SRMappingTemplate/Index', [
            'templates' => SRMappingTemplate::with('customer:id,code,name')
                ->latest()
                ->get(),
            'flash' => session('flash'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Master/SRMappingTemplate/Form', [
            'customers' => Customer::orderBy('code')->get(['id', 'code', 'name']),
            'template' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateTemplate($request);

        DB::transaction(function () use ($validated) {
            if ($validated['is_active']) {
                SRMappingTemplate::where('customer_id', $validated['customer_id'])->update(['is_active' => false]);
            }

            SRMappingTemplate::create($validated);
        });

        return redirect()
            ->route('sr-mapping-templates.index')
            ->with('flash', ['success' => 'Template SR berhasil ditambahkan.']);
    }

    public function edit(SRMappingTemplate $srMappingTemplate)
    {
        return Inertia::render('Master/SRMappingTemplate/Form', [
            'customers' => Customer::orderBy('code')->get(['id', 'code', 'name']),
            'template' => $srMappingTemplate,
        ]);
    }

    public function update(Request $request, SRMappingTemplate $srMappingTemplate)
    {
        $validated = $this->validateTemplate($request);

        DB::transaction(function () use ($validated, $srMappingTemplate) {
            if ($validated['is_active']) {
                SRMappingTemplate::where('customer_id', $validated['customer_id'])
                    ->where('id', '!=', $srMappingTemplate->id)
                    ->update(['is_active' => false]);
            }

            $srMappingTemplate->update($validated);
        });

        return redirect()
            ->route('sr-mapping-templates.index')
            ->with('flash', ['success' => 'Template SR berhasil diperbarui.']);
    }

    public function destroy(SRMappingTemplate $srMappingTemplate)
    {
        $srMappingTemplate->delete();

        return redirect()
            ->route('sr-mapping-templates.index')
            ->with('flash', ['success' => 'Template SR berhasil dihapus.']);
    }

    public function previewExcel(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,xlsm', 'max:51200'],
            'sheet_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $tempPath = $this->storeTempFile($request->file('file'));

        try {
            $spreadsheet = IOFactory::load($tempPath);
            $sheetIndex = (int) $request->input('sheet_index', 0);

            if ($sheetIndex >= $spreadsheet->getSheetCount()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Sheet index tidak valid.',
                ], 422);
            }

            $worksheet = $spreadsheet->getSheet($sheetIndex);
            $highestRow = min($worksheet->getHighestRow(), 25);
            $highestColumnIndex = min(Coordinate::columnIndexFromString($worksheet->getHighestColumn()), 30);
            $rows = [];

            for ($row = 1; $row <= $highestRow; $row++) {
                $cells = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cells[] = [
                        'column' => Coordinate::stringFromColumnIndex($col),
                        'value' => trim((string) $worksheet->getCellByColumnAndRow($col, $row)->getFormattedValue()),
                    ];
                }
                $rows[] = [
                    'row_number' => $row,
                    'cells' => $cells,
                ];
            }

            $sheets = [];
            for ($i = 0; $i < $spreadsheet->getSheetCount(); $i++) {
                $sheets[] = [
                    'index' => $i,
                    'name' => $spreadsheet->getSheet($i)->getTitle(),
                ];
            }

            return response()->json([
                'success' => true,
                'sheets' => $sheets,
                'current_sheet' => [
                    'index' => $sheetIndex,
                    'name' => $worksheet->getTitle(),
                ],
                'columns' => array_map(
                    fn ($col) => Coordinate::stringFromColumnIndex($col),
                    range(1, $highestColumnIndex)
                ),
                'rows' => $rows,
            ]);
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    public function validatePreview(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,xlsm', 'max:51200'],
            'sheet_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated = $this->validateTemplate($request);
        $customer = Customer::findOrFail($validated['customer_id']);
        $template = new SRMappingTemplate($validated);
        $tempPath = $this->storeTempFile($request->file('file'));

        try {
            $spreadsheet = IOFactory::load($tempPath);
            $sheetIndex = (int) $request->input('sheet_index', $validated['sheet_index'] ?? 0);

            if ($sheetIndex >= $spreadsheet->getSheetCount()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Sheet index tidak valid.',
                ], 422);
            }

            $sheet = $this->worksheetToArray($spreadsheet->getSheet($sheetIndex));
            $mapped = (new GenericTemplateMapper($template, $customer))->map($sheet);
            $preview = array_slice($mapped, 0, 20);

            return response()->json([
                'success' => true,
                'total_records' => count($mapped),
                'unique_parts' => count(array_unique(array_column($mapped, 'part_number'))),
                'total_qty' => array_sum(array_column($mapped, 'qty')),
                'preview' => $preview,
                'message' => 'Template berhasil membaca file contoh.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private function validateTemplate(Request $request): array
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:255'],
            'orientation' => ['required', Rule::in(['vertical', 'horizontal'])],
            'sheet_index' => ['nullable', 'integer', 'min:0'],
            'header_row' => ['nullable', 'integer', 'min:1'],
            'data_start_row' => ['required', 'integer', 'min:1'],
            'part_number_column' => ['required', 'string', 'max:8'],
            'qty_column' => ['nullable', 'required_if:orientation,vertical', 'string', 'max:8'],
            'qty_start_column' => ['nullable', 'required_if:orientation,horizontal', 'string', 'max:8'],
            'qty_end_column' => ['nullable', 'required_if:orientation,horizontal', 'string', 'max:8'],
            'date_header_row' => ['nullable', 'required_if:orientation,horizontal', 'integer', 'min:1'],
            'etd_column' => ['nullable', 'required_if:orientation,vertical', 'string', 'max:8'],
            'eta_column' => ['nullable', 'string', 'max:8'],
            'order_type_column' => ['nullable', 'string', 'max:8'],
            'default_order_type' => ['nullable', 'string', 'max:50'],
            'model_column' => ['nullable', 'string', 'max:8'],
            'family_column' => ['nullable', 'string', 'max:8'],
            'port_column' => ['nullable', 'string', 'max:8'],
            'month_column' => ['nullable', 'string', 'max:8'],
            'week_column' => ['nullable', 'string', 'max:8'],
            'year_column' => ['nullable', 'string', 'max:8'],
            'date_format' => ['nullable', 'string', 'max:50'],
            'skip_keywords' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        foreach ([
            'part_number_column', 'qty_column', 'qty_start_column', 'qty_end_column', 'etd_column',
            'eta_column', 'order_type_column', 'model_column', 'family_column', 'port_column',
            'month_column', 'week_column', 'year_column',
        ] as $field) {
            $value = isset($validated[$field]) ? trim((string) $validated[$field]) : '';
            $validated[$field] = $value !== ''
                ? strtoupper($value)
                : null;
        }

        $validated['default_order_type'] = $validated['default_order_type']
            ? strtoupper(trim($validated['default_order_type']))
            : null;
        $validated['date_format'] = isset($validated['date_format']) && trim((string) $validated['date_format']) !== ''
            ? trim($validated['date_format'])
            : null;
        $validated['skip_keywords'] = collect(explode(',', $validated['skip_keywords'] ?? ''))
            ->map(fn ($keyword) => trim($keyword))
            ->filter()
            ->values()
            ->all();
        $validated['is_active'] = filter_var($validated['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $validated;
    }

    private function storeTempFile($file): string
    {
        $directory = storage_path('app/temp');
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $path = $directory . DIRECTORY_SEPARATOR . Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->move($directory, basename($path));

        return $path;
    }

    private function worksheetToArray($worksheet): array
    {
        $sheetData = [];
        $highestRow = $worksheet->getHighestRow();
        $highestColIndex = Coordinate::columnIndexFromString($worksheet->getHighestColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($col = 1; $col <= $highestColIndex; $col++) {
                $rowData[] = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
            }
            $sheetData[$row - 1] = $rowData;
        }

        return $sheetData;
    }
}
