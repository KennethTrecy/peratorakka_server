<?php

namespace App\Entities;

class Modifier extends BaseResourceEntity
{
    protected $datamap = [];

    protected $casts = [
        "id" => "integer",
        "account_id" => "integer",
        "opposite_account_id" => "integer",
        "name" => "string",
        "description" => "?string"
    ];
}
