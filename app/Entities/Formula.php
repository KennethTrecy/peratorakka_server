<?php

namespace App\Entities;

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
        "precision_format_id" => "integer",
        "name" => "string",
        "description" => "?string",
        "output_format" => "formula_output_format",
        "expression" => "string"
    ];

    protected $castHandlers = [
        "formula_output_format" => FormulaOutputFormat::class
    ];
}
