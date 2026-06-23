<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

use App\Traits\LogsActivity;

class Assy extends Model
{
    use LogsActivity;

    protected $table = 'assy';

    protected $fillable = [
        'carline_id',
        'assy_number',
        'assy_code',
        'level',
        'pattern',
        'standard_sea_quantity',
        'standard_air_quantity',
        'max_quantity_sea',
        'max_quantity_air',
        'umh',
        'is_active',
    ];

    protected $casts = [
        'standard_sea_quantity' => 'integer',
        'standard_air_quantity' => 'integer',
        'max_quantity_sea' => 'integer',
        'max_quantity_air' => 'integer',
        'umh' => 'decimal:6',
        'is_active' => 'boolean',
    ];

    // Relasi
    public function carline()
    {
        return $this->belongsTo(CarLine::class, 'carline_id', 'id');
    }

    public function spp()
    {
        return $this->hasMany(SPP::class, 'assy_id');
    }

    // Local Scope
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // Helper: full identifier
    public function getFullNameAttribute()
    {
        return $this->assy_number . ' - ' . $this->assy_code;
    }
}
