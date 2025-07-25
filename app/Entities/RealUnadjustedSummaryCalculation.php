<?php

namespace App\Entities;

use App\Casts\RationalNumber;

class RealUnadjustedSummaryCalculation extends BaseResourceEntity
{
    protected $datamap = [];

    protected $dates = [];

    protected $casts = [
        "frozen_account_hash" => "string",
        "debit_amount" => "rational_number",
        "credit_amount" => "rational_number"
    ];

    protected $castHandlers = [
        "rational_number" => RationalNumber::class
    ];
}
