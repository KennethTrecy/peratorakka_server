<?php

namespace App\Entities;

class Formula extends BaseResourceEntity
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
        "name" => "string",
        "description" => "?string",
        "output_format" => "output_format",
        "presentational_precision" => "integer",
        "formula" => "string"
    ];
}
