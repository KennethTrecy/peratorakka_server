<?php

namespace App\Casts;

class NumericalToolKind extends UnknownableKind
{
    protected static array $KINDS = NUMERICAL_TOOL_KINDS;
    protected static string $UNKNOWN_KIND = UNKNOWN_NUMERICAL_TOOL_KIND;
}
