<?php

namespace App\Services\SPP\DTO;

use Illuminate\Support\Collection;

class ExportParams
{
    public function __construct(
        private readonly Collection|iterable $allBatchRecords,
        private readonly array $months,
        private readonly string $customerCode,
        private readonly string $period
    ) {}

    public function getAllBatchRecords(): Collection|iterable
    {
        return $this->allBatchRecords;
    }

    public function getMonths(): array
    {
        return $this->months;
    }

    public function getCustomerCode(): string
    {
        return $this->customerCode;
    }

    public function getPeriod(): string
    {
        return $this->period;
    }
}
