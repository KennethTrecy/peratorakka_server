<?php

namespace App\Libraries\FinancialStatementGroup;

class ExchangeRateDerivator
{
    private readonly array $financial_entries;

    public function __construct(array $financial_entries)
    {
        $this->financial_entries = $financial_entries;
    }
}
