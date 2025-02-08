<?php

namespace App\Entities;

use App\Casts\RationalNumber;

class RealAdjustedSummaryCalculation extends BaseResourceEntity
{
    protected $datamap = [];

    protected $dates = [];

    protected $casts = [
        "frozen_account_hash" => "string",
        "opened_amount" => "rational_number",
        "closed_amount" => "rational_number",
    ];

    protected $castHandlers = [
        "rational_number" => RationalNumber::class
    ];
}
