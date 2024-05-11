<?php

namespace App\Entities;

use App\Casts\CashFlowGroupKind;

class CashFlowGroup extends BaseResourceEntity
{
    protected $datamap = [];

    protected $casts = [
        "id" => "integer",
        "user_id" => "integer",
        "name" => "string",
        "description" => "?string",
        "kind" => "cash_flow_group_kind"
    ];

    protected $castHandlers = [
        "cash_flow_group_kind" => CashFlowGroupKind::class,
    ];
}
