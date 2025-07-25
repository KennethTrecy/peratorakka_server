<?php

namespace App\Casts;

class ModifierAtomKind extends UnknownableKind
{
    protected static array $KINDS = MODIFIER_ATOM_KINDS;
    protected static string $UNKNOWN_KIND = UNKNOWN_MODIFIER_ATOM_KIND;
}
