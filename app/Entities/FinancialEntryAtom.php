<?php

namespace App\Entities;

use App\Casts\RationalNumber;

class FinancialEntryAtom extends BaseResourceEntity
{
    protected $datamap = [];

    protected $dates = [];

    protected $casts = [
        "id" => "integer",
        "financial_entry_id" => "integer",
        "modifier_atom_id" => "integer",
        "numerical_value" => "rational_number"
    ];

    protected $castHandlers = [
        "rational_number" => RationalNumber::class
    ];
}
