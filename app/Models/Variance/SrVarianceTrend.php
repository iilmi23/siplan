<?php

namespace App\Models\Variance;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;

class SrVarianceTrend extends Model
{
    protected $fillable = [
        'customer_id',
        'customer_code',
        'assy_number',
        'period_type',
        'period_key',
        'year',
        'month_number',
        'production_week',
        'total_previous_qty',
        'total_current_qty',
        'total_variance_qty',
        'average_growth',
        'variance_volatility',
        'trend_duration',
        'trend_direction',
        'calculated_at',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'year' => 'integer',
        'month_number' => 'integer',
        'production_week' => 'integer',
        'total_previous_qty' => 'integer',
        'total_current_qty' => 'integer',
        'total_variance_qty' => 'integer',
        'average_growth' => 'decimal:2',
        'variance_volatility' => 'decimal:2',
        'trend_duration' => 'integer',
        'calculated_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
