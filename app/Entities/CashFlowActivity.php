<?php

namespace App\Entities;

class CashFlowActivity extends BaseResourceEntity
{
    protected $datamap = [];

    protected $casts = [
        "id" => "integer",
        "user_id" => "integer",
        "name" => "string",
        "description" => "?string"
    ];
}
