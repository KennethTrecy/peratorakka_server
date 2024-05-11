<?php

namespace App\Entities;

use App\Casts\CashFlowCategoryKind;

class CashFlowCategory extends BaseResourceEntity
{
    protected $datamap = [];

    protected $casts = [
        "id" => "integer",
        "user_id" => "integer",
        "name" => "string",
        "description" => "?string",
        "kind" => "cash_flow_category_kind"
    ];

    protected $castHandlers = [
        "cash_flow_category_kind" => CashFlowCategoryKind::class,
    ];
}
