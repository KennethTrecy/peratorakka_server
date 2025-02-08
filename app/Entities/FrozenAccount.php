<?php

namespace App\Entities;

class FrozenAccount extends BaseResourceEntity
{
    protected $datamap = [];

    protected $dates = [];

    protected $casts = [
        "frozen_period_id" => "integer",
        "account_id" => "integer",
        "hash" => "string"
    ];
}
