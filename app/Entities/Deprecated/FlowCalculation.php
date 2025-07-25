<?php

namespace App\Entities\Deprecated;

use App\Casts\RationalNumber;
use App\Entities\BaseResourceEntity;

class FlowCalculation extends BaseResourceEntity
{
    protected $datamap = [];

    protected $casts = [
        "id" => "integer",
        "frozen_period_id" => "integer",
        "cash_flow_activity_id" => "integer",
        "account_id" => "integer",
        "net_amount" => "rational_number"
    ];

    protected $castHandlers = [
        "rational_number" => RationalNumber::class
    ];
}
