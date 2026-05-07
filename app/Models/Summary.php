<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Summary extends Model
{
    protected $table = 'summaries';

    protected $fillable = [
        'upload_batch_id',
        'customer_id',
        'port_id',
        'assy_id',
        'upload_batch',
        'customer',
        'source_file',
        'sheet_name',
        'part_number',
        'model',
        'family',
        'order_type',
        'month',
        'week',
        'etd',
        'eta',
        'port',
        'line_count',
        'total_qty',
    ];

    protected $casts = [
        'etd' => 'date',
        'eta' => 'date',
        'line_count' => 'integer',
        'total_qty' => 'integer',
    ];

    public function uploadBatch()
    {
        return $this->belongsTo(UploadBatch::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function portMaster()
    {
        return $this->belongsTo(Port::class, 'port_id');
    }

    public function assy()
    {
        return $this->belongsTo(Assy::class);
    }
}
