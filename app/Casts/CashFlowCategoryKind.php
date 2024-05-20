<?php

namespace App\Casts;

class CashFlowCategoryKind extends UnknownableKind
{
    protected static array $KINDS = CASH_FLOW_CATEGORY_KINDS;
    protected static string $UNKNOWN_KIND = UNKNOWN_CASH_FLOW_CATEGORY_KIND;
}
