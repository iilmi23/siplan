<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Traits\LogsActivity;

class Port extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'customer_id',
        'name',
        'is_active'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}