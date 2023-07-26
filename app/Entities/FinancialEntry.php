<?php

namespace App\Entities;

class FinancialEntry extends BaseResourceEntity
{
    protected $datamap = [];

    protected $dates = [
        "transacted_at",
        "created_at",
        "updated_at",
        "deleted_at",
    ];

    protected $casts = [
        "id" => "integer",
        "modifier_id" => "integer",
        "debit_amount" => "string",
        "credit_amount" => "string",
        "remarks" => "?string"
    ];
}
