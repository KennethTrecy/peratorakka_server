<?php

namespace App\Entities;

use App\Casts\RationalNumber;

class RealFlowCalculation extends BaseResourceEntity
{
    protected $datamap = [];

    protected $casts = [
        "frozen_account_hash" => "string",
        "cash_flow_activity_id" => "integer",
        "net_amount" => "rational_number"
    ];

    protected $castHandlers = [
        "rational_number" => RationalNumber::class
    ];
}
