<?php

namespace App\Casts;

class FinancialEntryAtomKind extends UnknownableKind
{
    protected static array $KINDS = FINANCIAL_ENTRY_ATOM_KINDS;
    protected static string $UNKNOWN_KIND = UNKNOWN_FINANCIAL_ENTRY_ATOM_KIND;
}
