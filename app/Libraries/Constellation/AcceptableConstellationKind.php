<?php

namespace App\Libraries\Constellation;

/**
 * Note: Update the constellation kind constants in App\Config\Constants if some values were
 * changed.
 */
enum AcceptableConstellationKind: string
{
    case Account = "account";
    case Sum = "sum";
    case Average = "average";
    case Formula = "formula";
}
