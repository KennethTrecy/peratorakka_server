<?php

namespace App\Libraries;

class FinancialStatementGroup
{
    private readonly array $summary_calculations;
    private readonly array $flow_calculations;

    public function __construct(array $summary_calculations, array $flow_calculations)
    {
        $this->summary_calculations = $summary_calculations;
        $this->flow_calculations = $flow_calculations;
    }
}
