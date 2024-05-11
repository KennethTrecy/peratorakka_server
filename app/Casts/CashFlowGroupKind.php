<?php

namespace App\Casts;

class CashFlowGroupKind extends UnknownableKind
{
    protected static array $KINDS = CASH_FLOW_GROUP_KINDS;
    protected static string $UNKNOWN_KIND = UNKNOWN_CASH_FLOW_GROUP_KIND;
}
