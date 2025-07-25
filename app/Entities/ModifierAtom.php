<?php

namespace App\Entities;

use App\Casts\ModifierAtomKind;

class ModifierAtom extends BaseResourceEntity
{
    protected $datamap = [];

    protected $dates = [];

    protected $casts = [
        "id" => "integer",
        "modifier_id" => "integer",
        "account_id" => "integer",
        "kind" => "modifier_atom_kind"
    ];

    protected $castHandlers = [
        "modifier_atom_kind" => ModifierAtomKind::class
    ];
}
