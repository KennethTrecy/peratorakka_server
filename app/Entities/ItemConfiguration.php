<?php

namespace App\Entities;

use App\Casts\ItemConfigurationValuationMethod;

class ItemConfiguration extends BaseResourceEntity
{
    protected $datamap = [];

    protected $dates = [];

    protected $casts = [
        "account_id" => "integer",
        "item_detail_id" => "integer",
        "valuation_method" => "item_configuration_valuation_method"
    ];

    protected $castHandlers = [
        "item_configuration_valuation_method" => ItemConfigurationValuationMethod::class
    ];
}
