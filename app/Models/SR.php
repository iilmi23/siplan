<?php

namespace App\Models;

use App\Services\Variance\AnalyticsCacheService;
use Illuminate\Database\Eloquent\Model;

class SR extends Model
{
    protected $table = 'srs';

    protected $fillable = [
        'customer_id',
        'port_id',
        'carline_id',
        'sr_number',
        'assy_number',
        'qty',
        'total',
        'delivery_date',
        'etd',
        'eta',
        'route',
        'order_type',
        'assy_id',
        'upload_batch_id',
        'production_week_id',
        'is_mapped',
        'mapping_error',
        'extra',
    ];

    protected $casts = [
        'extra' => 'array',
        'is_mapped' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updated(fn () => app(AnalyticsCacheService::class)->invalidate());
        static::deleted(fn () => app(AnalyticsCacheService::class)->invalidate());
    }

    // Relasi
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function port()
    {
        return $this->belongsTo(Port::class, 'port_id', 'id');
    }

    public function carline()
    {
        return $this->belongsTo(CarLine::class, 'carline_id', 'id');
    }

    public function assy()
    {
        return $this->belongsTo(Assy::class, 'assy_id', 'id');
    }

    public function uploadBatch()
    {
        return $this->belongsTo(UploadBatch::class, 'upload_batch_id', 'id');
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
        if ($code = $this->getRelationValue('customer')?->code) {
            return $code;
        }
        if (!$this->relationLoaded('uploadBatch')) {
            $this->load('uploadBatch.customer');
        }
        return $this->getRelationValue('uploadBatch')?->customer?->code;
    }

    public function getPortAttribute()
    {
        if (array_key_exists('port', $this->attributes)) {
            return $this->attributes['port'];
        }
        if (!$this->relationLoaded('port')) {
            $this->load('port');
        }
        if ($name = $this->getRelationValue('port')?->name) {
            return $name;
        }
        if (!$this->relationLoaded('uploadBatch')) {
            $this->load('uploadBatch.port');
        }
        return $this->getRelationValue('uploadBatch')?->port?->name;
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

    public function getSheetIndexAttribute()
    {
        if (array_key_exists('sheet_index', $this->attributes)) {
            return $this->attributes['sheet_index'];
        }
        if (!$this->relationLoaded('uploadBatch')) {
            $this->load('uploadBatch');
        }
        return $this->getRelationValue('uploadBatch')?->sheet_index;
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

    public function getWeekAttribute()
    {
        if (array_key_exists('week', $this->attributes)) {
            return $this->attributes['week'];
        }
        if (!$this->relationLoaded('productionWeek')) {
            $this->load('productionWeek');
        }
        // Fallback ke extra JSON jika production_week_id null (mis. customer SAI)
        return $this->getRelationValue('productionWeek')?->week_no
            ?? ($this->extra['week'] ?? null);
    }

    public function getMonthAttribute()
    {
        if (array_key_exists('month', $this->attributes)) {
            return $this->attributes['month'];
        }
        if (!$this->relationLoaded('productionWeek')) {
            $this->load('productionWeek');
        }
        // Fallback ke extra JSON jika production_week_id null (mis. customer SAI)
        return $this->getRelationValue('productionWeek')?->month_name
            ?? ($this->extra['month'] ?? null);
    }

    public function getYearAttribute()
    {
        if (array_key_exists('year', $this->attributes)) {
            return $this->attributes['year'];
        }
        if (!$this->relationLoaded('productionWeek')) {
            $this->load('productionWeek');
        }
        // Fallback ke extra JSON jika production_week_id null (mis. customer SAI)
        return $this->getRelationValue('productionWeek')?->year
            ?? ($this->extra['year'] ?? null);
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
}
