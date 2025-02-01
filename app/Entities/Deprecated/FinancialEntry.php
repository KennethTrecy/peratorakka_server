<?php

namespace App\Entities\Deprecated;

use App\Entities\BaseResourceEntity;

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
        "debit_amount" => "rational_number",
        "credit_amount" => "rational_number",
        "remarks" => "?string"
    ];

    protected $castHandlers = [
        "rational_number" => RationalNumber::class
    ];
}
