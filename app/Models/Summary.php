<?php

namespace App\Models;

use App\Services\Variance\AnalyticsCacheService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Summary extends Model
{
    protected $table = 'summaries';

    protected $fillable = [
        'upload_batch_id',
        'customer_id',
        'port_id',
        'assy_id',
        'production_week_id',
        'etd',
        'eta',
        'month',
        'week',
        'assy_number',
        'order_type',
        'line_count',
        'total_qty',
    ];

    protected $casts = [
        'etd' => 'date',
        'eta' => 'date',
        'line_count' => 'integer',
        'total_qty' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updated(fn() => app(AnalyticsCacheService::class)->invalidate());
        static::deleted(fn() => app(AnalyticsCacheService::class)->invalidate());
    }

    public function uploadBatch()
    {
        return $this->belongsTo(UploadBatch::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function port()
    {
        return $this->belongsTo(Port::class, 'port_id');
    }

    public function portMaster()
    {
        return $this->belongsTo(Port::class, 'port_id');
    }

    public function assy()
    {
        return $this->belongsTo(Assy::class);
    }

    public function productionWeek()
    {
        return $this->belongsTo(ProductionWeek::class, 'production_week_id');
    }

    // Accessors untuk backward compatibility dengan 3NF
    public function getCustomerAttribute()
    {
        if (array_key_exists('customer', $this->attributes)) {
            return $this->attributes['customer'];
        }
        if (!$this->relationLoaded('customer')) {
            $this->load('customer');
        }
        return $this->getRelationValue('customer')?->code;
    }

    public function getPortAttribute()
    {
        if (array_key_exists('port', $this->attributes)) {
            return $this->attributes['port'];
        }
        if (!$this->relationLoaded('port')) {
            $this->load('port');
        }
        return $this->getRelationValue('port')?->name;
    }

    public function getSourceFileAttribute()
    {
        if (array_key_exists('source_file', $this->attributes)) {
            return $this->attributes['source_file'];
        }
        if (!$this->relationLoaded('uploadBatch')) {
            $this->load('uploadBatch');
        }
        return $this->getRelationValue('uploadBatch')?->source_file;
    }

    public function getSheetNameAttribute()
    {
        if (array_key_exists('sheet_name', $this->attributes)) {
            return $this->attributes['sheet_name'];
        }
        if (!$this->relationLoaded('uploadBatch')) {
            $this->load('uploadBatch');
        }
        return $this->getRelationValue('uploadBatch')?->sheet_name;
    }

    public function getUploadBatchAttribute()
    {
        if (array_key_exists('upload_batch', $this->attributes)) {
            return $this->attributes['upload_batch'];
        }
        if (!$this->relationLoaded('uploadBatch')) {
            $this->load('uploadBatch');
        }
        return $this->getRelationValue('uploadBatch')?->batch_uuid;
    }

    public function getMonthAttribute()
    {
        if (! empty($this->attributes['month'] ?? null)) {
            return $this->attributes['month'];
        }
        if (!$this->relationLoaded('productionWeek')) {
            $this->load('productionWeek');
        }
        return $this->getRelationValue('productionWeek')?->month_name;
    }

    public function getWeekAttribute()
    {
        if (! empty($this->attributes['week'] ?? null)) {
            return $this->attributes['week'];
        }
        if (!$this->relationLoaded('productionWeek')) {
            $this->load('productionWeek');
        }
        return $this->getRelationValue('productionWeek')?->week_no;
    }

    public function getModelAttribute()
    {
        if (array_key_exists('model', $this->attributes)) {
            return $this->attributes['model'];
        }
        if (!$this->relationLoaded('assy')) {
            $this->load('assy.carline');
        }
        return $this->getRelationValue('assy')?->carline?->code;
    }

    public function getFamilyAttribute()
    {
        if (array_key_exists('family', $this->attributes)) {
            return $this->attributes['family'];
        }
        if (!$this->relationLoaded('assy')) {
            $this->load('assy.carline');
        }
        return $this->getRelationValue('assy')?->carline?->code;
    }

    public function getEtaAttribute()
    {
        if (! empty($this->attributes['eta'] ?? null)) {
            return Carbon::parse($this->attributes['eta']);
        }
        if (!$this->relationLoaded('productionWeek')) {
            $this->load('productionWeek');
        }
        return $this->getRelationValue('productionWeek')?->end_date;
    }

    public function getEtdAttribute()
    {
        if (! empty($this->attributes['etd'] ?? null)) {
            return Carbon::parse($this->attributes['etd']);
        }
        if (!$this->relationLoaded('productionWeek')) {
            $this->load('productionWeek');
        }
        return $this->getRelationValue('productionWeek')?->week_start;
    }

    public function getQtyAttribute()
    {
        if (array_key_exists('qty', $this->attributes)) {
            return $this->attributes['qty'];
        }
        return $this->total_qty;
    }
}
