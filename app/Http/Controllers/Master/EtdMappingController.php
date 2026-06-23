<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\EtdMapping;
use App\Services\Master\EtdMappingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EtdMappingController extends Controller
{
    public function __construct(
        private readonly EtdMappingService $service
    ) {
    }

    /**
     * Tampilkan ETD mapping untuk customer tertentu
     */
    public function index($customerId)
    {
        $data = $this->service->getCustomerMappings($customerId);
        
        return response()->json([
            'success' => true,
            'customer' => $data['customer'],
            'mappings' => $data['mappings'],
        ]);
    }
    
    /**
     * Update ETD mapping
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'production_week_id' => 'required|exists:production_weeks,id',
        ]);
        
        $mapping = EtdMapping::findOrFail($id);
        
        $mapping->update([
            'production_week_id' => $request->production_week_id,
            'is_edited' => true,
            'edited_by' => Auth::id(),
            'edited_at' => now(),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Mapping berhasil diupdate',
        ]);
    }
    
    /**
     * Hapus ETD mapping
     */
    public function destroy($id)
    {
        $mapping = EtdMapping::findOrFail($id);
        $mapping->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Mapping berhasil dihapus',
        ]);
    }
    
    /**
     * Sync semua ETD dari SR ke etd_mappings
     */
    public function sync(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
        ]);
        
        $result = $this->service->syncEtd($request->customer_id);
        
        return redirect()->back()->with('success', "Sync selesai: {$result['synced']} ETD ter-mapping. Error: " . count($result['errors']));
    }
}
