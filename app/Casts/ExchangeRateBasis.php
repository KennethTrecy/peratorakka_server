<?php

namespace App\Casts;

class ExchangeRateBasis extends UnknownableKind
{
    protected static array $KINDS = EXCHANGE_RATE_BASES;
    protected static string $UNKNOWN_KIND = UNKNOWN_EXCHANGE_RATE_BASIS;
}
