<?php

namespace App\Entities;

use App\Casts\RationalNumber;

class SummaryCalculation extends BaseResourceEntity
{
    protected $datamap = [];

    protected $dates = [];

    protected $casts = [
        "id" => "integer",
        "frozen_period_id" => "integer",
        "account_id" => "integer",
        "unadjusted_debit_amount" => "rational_number",
        "unadjusted_credit_amount" => "rational_number",
        "adjusted_debit_amount" => "rational_number",
        "adjusted_credit_amount" => "rational_number"
    ];

    protected $castHandlers = [
        "rational_number" => RationalNumber::class
    ];
}
