<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class Currency extends Entity
{
    protected $datamap = [];

    protected $dates = [
        "created_at",
        "updated_at",
        "deleted_at"
    ];

    protected $casts = [
        "user_id" => "integer",
        "code" => "string",
        "name" => "string"
    ];
}
