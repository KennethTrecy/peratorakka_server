<?php

namespace App\Casts;

class ItemConfigurationValuationMethod extends UnknownableKind
{
    protected static array $KINDS = VALUATION_METHODS;
    protected static string $UNKNOWN_KIND = UNKNOWN_VALUATION_METHOD;
}
