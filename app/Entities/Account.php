<?php

namespace App\Entities;

use App\Casts\AccountKind;

class Account extends BaseResourceEntity
{
    protected $datamap = [];

    protected $casts = [
        "id" => "integer",
        "currency_id" => "integer",
        "name" => "string",
        "description" => "?string",
        "kind" => "account_kind"
    ];

    protected $castHandlers = [
        "account_kind" => AccountKind::class,
    ];

    protected $dates = [
        "created_at",
        "updated_at",
        "deleted_at",
    ];
}
