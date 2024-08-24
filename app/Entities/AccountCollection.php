<?php

namespace App\Entities;

class AccountCollection extends BaseResourceEntity
{
    protected $datamap = [];

    protected $casts = [
        "id" => "integer",
        "collection_id" => "integer",
        "account_id" => "integer"
    ];
}
