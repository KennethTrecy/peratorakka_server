<?php

namespace App\Entities;

class Currency extends BaseResourceEntity
{
    protected $datamap = [];

    protected $dates = [
        "created_at",
        "updated_at",
        "deleted_at",
    ];

    protected $casts = [
        "id" => "integer",
        "user_id" => "integer",
        "code" => "string",
        "name" => "string",
        "presentational_precision" => "integer"
    ];
}
