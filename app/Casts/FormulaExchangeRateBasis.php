<?php

namespace App\Casts;

class FormulaExchangeRateBasis extends UnknownableKind
{
    protected static array $KINDS = FORMULA_EXCHANGE_RATE_BASES;
    protected static string $UNKNOWN_KIND = UNKNOWN_FORMULA_EXCHANGE_RATE_BASIS;
}
