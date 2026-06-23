<?php

namespace App\Services\SR;

use App\Services\SR\Mappers\TYCMapper;
use App\Services\SR\Mappers\YNAMapper;
use App\Services\SR\Mappers\SAIMapper;
use App\Services\SR\Mappers\YCMapper;

class SRMapperFactory
{
    public static function make($customer)
    {
        return match (strtoupper($customer)) {
            'TYC', 'JAI_TW' => new TYCMapper(),
            'YNA' => new YNAMapper(),
            'SAI' => new SAIMapper(),
            'YC' => new YCMapper(),
            default => throw new \InvalidArgumentException("No mapper found for customer {$customer}"),
        };
    }
}