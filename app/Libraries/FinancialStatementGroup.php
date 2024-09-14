<?php

namespace App\Libraries;

use App\Libraries\FinancialStatementGroup\ExchangeRateDerivator;

class FinancialStatementGroup
{
    private readonly array $summary_calculations;
    private readonly array $flow_calculations;
    private readonly ExchangeRateDerivator $derivator;

    public function __construct(
        array $summary_calculations,
        array $flow_calculations,
        ExchangeRateDerivator $derivator
    ) {
        $this->summary_calculations = $summary_calculations;
        $this->flow_calculations = $flow_calculations;
        $this->derivator = $derivator;
    }
}
