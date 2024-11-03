<?php

namespace App\Entities;

use App\Casts\NumericalToolKind;
use App\Casts\NumericalToolRecurrencePeriod;

class NumericalTool extends BaseResourceEntity
{
    protected $datamap = [];

    protected $dates = [
        "created_at",
        "updated_at",
        "deleted_at",
    ];

    protected $casts = [
        "id" => "integer",
        "user_id" => "integer",
        "kind" => "numerical_tool_kind",
        "recurrence" => "numerical_tool_recurrence_period",
        "recency" => "integer",
        "order" => "integer",
        "notes" => "?string",
        "configuration" => "?string"
    ];

    protected $castHandlers = [
        "numerical_tool_kind" => NumericalToolKind::class,
        "numerical_tool_recurrence_period" => NumericalToolRecurrencePeriod::class
    ];
}
