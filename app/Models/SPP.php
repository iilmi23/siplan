<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SPP extends Model
{
    protected $table = 'spp';

    protected $fillable = [
        'spp_batch_id',
        'upload_batch_id',
        'customer_id',
        'port_id',
        'assy_id',
        'type',
        'assy_number',
        'level',
        'assy_code',
        'cct',
        'std_pack',
        'umh',
        'period',
        'order_type',
        'bal_qty',
        'del_qty',
        'prod_qty',
        'total_qty',
        'extra',
    ];

    protected $casts = [
        'std_pack' => 'integer',
        'umh' => 'decimal:6',
        'bal_qty' => 'integer',
        'del_qty' => 'integer',
        'prod_qty' => 'integer',
        'total_qty' => 'integer',
        'extra' => 'array',
    ];

    public function uploadBatch()
    {
        return $this->belongsTo(UploadBatch::class, 'upload_batch_id');
    }

    public function sppBatch()
    {
        return $this->belongsTo(SppBatch::class, 'spp_batch_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function port()
    {
        return $this->belongsTo(Port::class, 'port_id');
    }

    public function assy()
    {
        return $this->belongsTo(Assy::class, 'assy_id');
    }

    public function portMaster()
    {
        return $this->belongsTo(Port::class, 'port_id');
    }

    // Accessors untuk backward compatibility dengan 3NF
    public function getCustomerAttribute()
    {
        if (!$this->relationLoaded('customer')) {
            $this->load('customer');
        }
        return $this->getRelationValue('customer')?->code;
    }

    public function getPortAttribute()
    {
        if (!$this->relationLoaded('port')) {
            $this->load('port');
        }
        return $this->getRelationValue('port')?->name;
    }

    public function getSourceFileAttribute()
    {
        if (!$this->relationLoaded('uploadBatch')) {
            $this->load('uploadBatch');
        }
        return $this->getRelationValue('uploadBatch')?->source_file;
    }

    public function getSheetNameAttribute()
    {
        if (!$this->relationLoaded('uploadBatch')) {
            $this->load('uploadBatch');
        }
        return $this->getRelationValue('uploadBatch')?->sheet_name;
    }

    public function getUploadBatchAttribute()
    {
        if (!$this->relationLoaded('uploadBatch')) {
            $this->load('uploadBatch');
        }
        return $this->getRelationValue('uploadBatch')?->batch_uuid;
    }

    public function getCarlineAttribute()
    {
        if (!$this->relationLoaded('assy')) {
            $this->load('assy.carline');
        }
        return $this->getRelationValue('assy')?->carline?->code;
    }

    public function getMonthLabelAttribute()
    {
        return strtoupper(\Carbon\Carbon::parse($this->period . '-01')->format('M'));
    }

    public function getYearAttribute()
    {
        return (int) substr($this->period, 0, 4);
    }

    public function getPeriodStartAttribute()
    {
        $year = (int) substr($this->period, 0, 4);
        $month = (int) substr($this->period, 5, 2);
        
        $pw = ProductionWeek::where('year', $year)
            ->where('month_number', $month)
            ->when($this->customer_id, fn($q) => $q->where('customer_id', $this->customer_id))
            ->orderBy('week_no')
            ->first();
            
        return $pw ? $pw->week_start : \Carbon\Carbon::parse($this->period . '-01')->startOfMonth();
    }

    public function getPeriodEndAttribute()
    {
        $year = (int) substr($this->period, 0, 4);
        $month = (int) substr($this->period, 5, 2);
        
        $pw = ProductionWeek::where('year', $year)
            ->where('month_number', $month)
            ->when($this->customer_id, fn($q) => $q->where('customer_id', $this->customer_id))
            ->orderBy('week_no', 'desc')
            ->first();
            
        return $pw ? $pw->end_date : \Carbon\Carbon::parse($this->period . '-01')->endOfMonth();
    }
}
