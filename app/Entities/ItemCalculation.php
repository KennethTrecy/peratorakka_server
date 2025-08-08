<?php

namespace App\Entities;

use App\Casts\RationalNumber;

class ItemCalculation extends BaseResourceEntity
{
    protected $datamap = [];

    protected $casts = [
        "frozen_account_hash" => "string",
        "financial_entry_id" => "integer",
        "unit_price" => "rational_number",
        "remaining_quantity" => "rational_number"
    ];

    protected $castHandlers = [
        "rational_number" => RationalNumber::class
    ];
}
