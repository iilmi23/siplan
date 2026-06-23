<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class HistoryLog extends Model
{
    protected $table = 'history_logs';

    protected $fillable = [
        'user_id',
        'activity_type',
        'title',
        'status',
        'customer_code',
        'port_name',
        'source_file',
        'batch_uuid',
        'sheet_name',
        'record_count',
        'total_qty',
        'mapped_count',
        'unmapped_count',
        'source_batch_count',
        'related_id',
        'notes',
        'details',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'record_count' => 'integer',
        'total_qty' => 'integer',
        'mapped_count' => 'integer',
        'unmapped_count' => 'integer',
        'source_batch_count' => 'integer',
        'related_id' => 'integer',
        'details' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper method to create a history log entry.
     */
    public static function log(
        string $activityType,
        string $title,
        ?string $status = 'completed',
        ?string $customerCode = null,
        ?string $portName = null,
        ?string $sourceFile = null,
        ?string $batchUuid = null,
        ?string $sheetName = null,
        int $recordCount = 0,
        int $totalQty = 0,
        int $mappedCount = 0,
        int $unmappedCount = 0,
        int $sourceBatchCount = 0,
        ?int $relatedId = null,
        ?string $notes = null,
        ?array $details = null
    ): self {
        return self::create([
            'user_id' => Auth::id(),
            'activity_type' => $activityType,
            'title' => $title,
            'status' => $status,
            'customer_code' => $customerCode,
            'port_name' => $portName,
            'source_file' => $sourceFile,
            'batch_uuid' => $batchUuid,
            'sheet_name' => $sheetName,
            'record_count' => $recordCount,
            'total_qty' => $totalQty,
            'mapped_count' => $mappedCount,
            'unmapped_count' => $unmappedCount,
            'source_batch_count' => $sourceBatchCount,
            'related_id' => $relatedId,
            'notes' => $notes,
            'details' => $details,
        ]);
    }
}
