<?php

namespace App\Casts;

class OutputFormat extends UnknownableKind
{
    protected static array $KINDS = ACCEPTABLE_FORMULA_OUTPUT_FORMATS;
    protected static string $UNKNOWN_KIND = UNKNOWN_FORMULA_OUTPUT_FORMAT;
}
