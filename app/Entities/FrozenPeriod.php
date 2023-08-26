<?php

namespace App\Entities;

class FrozenPeriod extends BaseResourceEntity
{
    protected $datamap = [];

    protected $dates = [
        "started_at",
        "finished_at",
        "created_at",
        "updated_at",
        "deleted_at",
    ];

    protected $casts = [
        "id" => "integer"
    ];
}
