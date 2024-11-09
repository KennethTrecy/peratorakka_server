<?php

namespace App\Libraries\Context;

enum ContextKeys: string
{
    case RECURRENCE_KIND = "recurrence_kind";
    case RECENCY_KIND = "recency_kind";

    case EXCHANGE_RATE_BASIS = "exchange_rate_basis";
    case LATEST_FINISHED_DATE = "latest_finished_date";
    case DESTINATION_CURRENCY_ID = "destination_currency_id";

    case TIME_GROUP_MANAGER = "manager";
    case FLASH_CACHE = "flash_cache";
    case EXCHANGE_RATE_CACHE = "exchange_rate_cache";
    case ACCOUNT_CACHE = "account_cache";
}
