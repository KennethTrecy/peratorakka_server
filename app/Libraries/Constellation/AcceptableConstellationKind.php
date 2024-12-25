<?php

namespace App\Libraries\Constellation;

enum AcceptableConstellationKind: string
{
    case Account = ACCOUNT_CONSTELLATION_KIND;
    case Sum = SUM_CONSTELLATION_KIND;
    case Average = AVERAGE_CONSTELLATION_KIND;
    case Formula = FORMULA_CONSTELLATION_KIND;
}
