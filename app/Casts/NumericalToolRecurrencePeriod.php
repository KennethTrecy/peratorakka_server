<?php

namespace App\Casts;

class NumericalToolRecurrencePeriod extends UnknownableKind
{
    protected static array $KINDS = NUMERICAL_TOOL_RECURRENCE_PERIODS;
    protected static string $UNKNOWN_KIND = UNKNOWN_NUMERICAL_TOOL_RECURRENCE_PERIOD;
}
