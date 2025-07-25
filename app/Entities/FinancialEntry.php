<?php

namespace App\Entities;

use App\Casts\RationalNumber;

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
        "remarks" => "?string"
    ];
}
