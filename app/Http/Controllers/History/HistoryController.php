<?php

namespace App\Http\Controllers\History;

use App\Http\Controllers\Controller;

use App\Models\Customer;
use App\Services\Utilities\HistoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HistoryController extends Controller
{
    public function __construct(private readonly HistoryService $history)
    {
    }

    public function index(Request $request)
    {
        $filters = $this->history->defaultFilters($request->only($this->history->filterKeys()));

        return Inertia::render('History/Index', [
            'historyItems' => $this->history->timeline(
                $filters,
                $request->integer('per_page', 25)
            ),
            'customers' => Customer::orderBy('name')->get(['name', 'code']),
            'filters' => $filters,
            'typeOptions' => (function () {
                $defaultTypes = [
                    'sr_upload' => 'SR Uploaded',
                    'spp_generated' => 'SPP Generated',
                    'summary_row_updated' => 'Summary Row Updated',
                    'summary_period_updated' => 'Summary Period Updated',
                    'summary_deleted' => 'Summary Deleted',
                ];

                $dbTypes = \App\Models\HistoryLog::distinct()
                    ->pluck('activity_type')
                    ->filter()
                    ->all();

                $allTypes = [];
                foreach (array_unique(array_merge(array_keys($defaultTypes), $dbTypes)) as $type) {
                    $label = $defaultTypes[$type] ?? ucwords(str_replace('_', ' ', $type));
                    $allTypes[] = ['value' => $type, 'label' => $label];
                }

                usort($allTypes, fn ($a, $b) => strcmp($a['label'], $b['label']));
                return $allTypes;
            })(),
            'statusOptions' => [
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'processed', 'label' => 'Processed'],
                ['value' => 'generated', 'label' => 'Generated'],
                ['value' => 'processing', 'label' => 'Processing'],
                ['value' => 'failed', 'label' => 'Failed'],
                ['value' => 'updated', 'label' => 'Updated'],
                ['value' => 'deleted', 'label' => 'Deleted'],
            ],
        ]);
    }
}
