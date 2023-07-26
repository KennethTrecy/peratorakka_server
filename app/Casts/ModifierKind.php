<?php

namespace App\Casts;

class ModifierKind extends UnknownableKind
{
    protected static array $KINDS = MODIFIER_KINDS;
    protected static string $UNKNOWN_KIND = UNKNOWN_MODIFIER_KIND;
}
