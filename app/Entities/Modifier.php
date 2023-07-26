<?php

namespace App\Entities;

use App\Casts\ResultSide;
use App\Casts\ModifierKind;

class Modifier extends BaseResourceEntity
{
    protected $datamap = [];

    protected $casts = [
        "id" => "integer",
        "account_id" => "integer",
        "opposite_account_id" => "integer",
        "name" => "string",
        "description" => "?string",
        "result_side" => "side",
        "kind" => "modifier_kind"
    ];

    protected $castHandlers = [
        "side" => ResultSide::class,
        "modifier_kind" => ModifierKind::class,
    ];
}
