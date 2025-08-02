<?php

namespace App\Entities;

class ItemDetail extends BaseResourceEntity
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
        "name" => "string",
        "unit" => "string",
        "description" => "string"
    ];
}
