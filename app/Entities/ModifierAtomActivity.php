<?php

namespace App\Entities;

use App\Casts\RationalNumber;

class ModifierAtomActivity extends BaseResourceEntity
{
    protected $datamap = [];

    protected $dates = [];

    protected $casts = [
        "modifier_atom_id" => "integer",
        "cash_flow_activity_id" => "integer"
    ];
}
