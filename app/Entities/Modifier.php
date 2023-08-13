<?php

namespace App\Entities;

use App\Casts\ResultSide;
use App\Casts\ModifierKind;

class Modifier extends BaseResourceEntity
{
    protected $datamap = [];

    protected $casts = [
        "id" => "integer",
        "debit_account_id" => "integer",
        "credit_account_id" => "integer",
        "name" => "string",
        "description" => "?string",
        "kind" => "modifier_kind"
    ];

    protected $castHandlers = [
        "modifier_kind" => ModifierKind::class,
    ];
}
