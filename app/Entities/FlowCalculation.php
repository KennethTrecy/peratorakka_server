<?php

namespace App\Entities;

use App\Casts\RationalNumber;

class FlowCalculation extends BaseResourceEntity
{
    protected $datamap = [];

    protected $casts = [
        "id" => "integer",
        "cash_flow_category_id" => "integer",
        "summary_calculation_id" => "integer",
        "net_amount" => "rational_number"
    ];

    protected $castHandlers = [
        "rational_number" => RationalNumber::class
    ];
}
