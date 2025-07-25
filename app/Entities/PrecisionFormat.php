<?php

namespace App\Entities;

class PrecisionFormat extends BaseResourceEntity
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
        "minimum_presentational_precision" => "integer",
        "maximum_presentational_precision" => "integer"
    ];
}
