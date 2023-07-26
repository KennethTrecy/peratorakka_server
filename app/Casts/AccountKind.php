<?php

namespace App\Casts;

class AccountKind extends UnknownableKind
{
    protected static array $KINDS = ACCOUNT_KINDS;
    protected static string $UNKNOWN_KIND = UNKNOWN_ACCOUNT_KIND;
}
