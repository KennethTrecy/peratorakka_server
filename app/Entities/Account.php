<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class Account extends Entity
{
    protected $datamap = [];

    protected $dates = [
        "created_at",
        "updated_at",
        "deleted_at"
    ];

    protected $casts = [
        "id" => "integer",
        "currency_id" => "integer",
        "name" => "string",
        "description" => "?string",
        "kind" => "string"
    ];
}
