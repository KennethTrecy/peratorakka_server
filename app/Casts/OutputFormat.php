<?php

namespace App\Casts;

class OutputFormat extends UnknownableKind
{
    protected static array $KINDS = OUTPUT_FORMATS;
    protected static string $UNKNOWN_KIND = UNKNOWN_OUTPUT_FORMAT;
}
