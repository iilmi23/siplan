<?php

namespace App\Http\Controllers\Summary;

use App\Http\Controllers\Controller;
use App\Services\SR\UnmappedAssyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UnmappedAssyController extends Controller
{
    public function __construct(
        private readonly UnmappedAssyService $service
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'customer']);
        $isAdmin = Auth::check() && Auth::user()?->isAdmin();
        $userId = Auth::id();

        $data = $this->service->getUnmappedData($filters, $userId, $isAdmin);

        return Inertia::render('UnmappedAssy/Index', [
            'items' => $data['items'],
            'summary' => $data['summary'],
            'customers' => $data['customers'],
            'filters' => $filters,
            'flash' => [
                'success' => session('success'),
                'warning' => session('warning'),
                'error' => session('error'),
            ],
        ]);
    }

    public function remap(Request $request)
    {
        $validated = $request->validate([
            'assy_numbers' => ['required', 'array', 'min:1'],
            'assy_numbers.*' => ['required', 'string', 'max:255'],
        ]);

        $isAdmin = Auth::check() && Auth::user()?->isAdmin();
        $userId = Auth::id();

        try {
            $result = $this->service->remap($validated['assy_numbers'], $userId, $isAdmin);
            
            if ($result['remapped'] === 0) {
                return back()->with('warning', 'Belum ada yang berubah. Master assy exact-match belum ditemukan untuk pilihan tersebut.');
            }

            $message = "Berhasil remap {$result['remapped']} baris SR/Summary.";

            if ($result['notFoundCount'] > 0) {
                $message .= " {$result['notFoundCount']} assy masih belum ada di master.";
            }

            return back()->with($result['notFoundCount'] > 0 ? 'warning' : 'success', $message);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
