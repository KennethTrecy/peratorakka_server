<?php

namespace App\Entities;

use App\Casts\ModifierKind;
use App\Casts\ModifierAction;

class Modifier extends BaseResourceEntity
{
    protected $datamap = [];

    protected $casts = [
        "id" => "integer",
        "debit_account_id" => "integer",
        "credit_account_id" => "integer",
        "debit_cash_flow_category_id" => "?integer",
        "credit_cash_flow_category_id" => "?integer",
        "name" => "string",
        "description" => "?string",
        "action" => "modifier_action",
        "kind" => "modifier_kind"
    ];

    protected $castHandlers = [
        "modifier_action" => ModifierAction::class,
        "modifier_kind" => ModifierKind::class,
    ];
}
