<?php

namespace App\Casts;

class ModifierAction extends UnknownableKind
{
    protected static array $KINDS = MODIFIER_ACTIONS;
    protected static string $UNKNOWN_KIND = UNKNOWN_MODIFIER_ACTION;
}
