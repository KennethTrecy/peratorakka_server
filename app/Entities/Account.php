<?php

namespace App\Entities;

use App\Casts\AccountKind;

class Account extends BaseResourceEntity
{
    protected $datamap = [];

    protected $casts = [
        "id" => "integer",
        "currency_id" => "integer",
        "increase_cash_flow_category_id" => "?integer",
        "decrease_cash_flow_category_id" => "?integer",
        "name" => "string",
        "description" => "?string",
        "kind" => "account_kind"
    ];

    protected $castHandlers = [
        "account_kind" => AccountKind::class,
    ];
}
