<?php

namespace App\Entities;

class Currency extends BaseResourceEntity
{
    protected $datamap = [];

    protected $casts = [
        "id" => "integer",
        "user_id" => "integer",
        "code" => "string",
        "name" => "string"
    ];
}
