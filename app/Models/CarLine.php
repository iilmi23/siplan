<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\LogsActivity;

class CarLine extends Model
{
    use LogsActivity;

    protected $table = 'carline';

    protected $fillable = [
        'code',
    ];

    // Relasi
    public function assy()
    {
        return $this->hasMany(Assy::class, 'carline_id', 'id');
    }
}