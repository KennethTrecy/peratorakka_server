<?php

namespace App\Libraries\MathExpression;

enum ContextKeys: string
{
    case TIME_GROUP_MANAGER = "manager";
    case FLASH_CACHE = "flash_cache";
    case EXCHANGE_RATE_CACHE = "exchange_rate_cache";
    case DESTINATION_CURRENCY_ID = "destination_currency_id";
}
