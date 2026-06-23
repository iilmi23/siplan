<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SppBatch extends Model
{
    protected $table = 'spp_batches';

    protected $fillable = [
        'batch_uuid',
        'customer_id',
        'port_id',
        'generated_by',
        'source_file',
        'sheet_name',
        'status',
        'source_batch_count',
        'record_count',
        'total_qty',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'source_batch_count' => 'integer',
        'record_count' => 'integer',
        'total_qty' => 'integer',
        'metadata' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function port()
    {
        return $this->belongsTo(Port::class);
    }

    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function records()
    {
        return $this->hasMany(SPP::class, 'spp_batch_id');
    }

    public function sourceBatches()
    {
        return $this->belongsToMany(UploadBatch::class, 'spp_batch_sources')
            ->withTimestamps();
    }
}
