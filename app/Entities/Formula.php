<?php

namespace App\Entities;

use App\Casts\ExchangeRateBasis;
use App\Casts\FormulaOutputFormat;

class Formula extends BaseResourceEntity
{
    protected $datamap = [];

    protected $dates = [
        "created_at",
        "updated_at",
        "deleted_at",
    ];

    protected $casts = [
        "id" => "integer",
        "currency_id" => "integer",
        "name" => "string",
        "description" => "?string",
        "output_format" => "formula_output_format",
        "exchange_rate_basis" => "formula_exchange_rate_basis",
        "presentational_precision" => "integer",
        "formula" => "string"
    ];

    protected $castHandlers = [
        "formula_exchange_rate_basis" => ExchangeRateBasis::class,
        "formula_output_format" => FormulaOutputFormat::class
    ];
}
