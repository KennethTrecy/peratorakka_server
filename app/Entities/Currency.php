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
        "precision_format_id" => "integer",
        "code" => "string",
        "name" => "string"
    ];
}
