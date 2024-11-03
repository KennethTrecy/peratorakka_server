<?php

namespace App\Contracts;

use App\Entities\FlowCalculation;
use App\Entities\SummaryCalculation;
use Brick\Math\BigRational;
use CodeIgniter\I18n\Time;

/**
 * Representation of a numerical tool.
 *
 * All data points would be presented according to the numerical tool.
 */
interface NumericalToolSource
{
    /**
     * Returns the data points to be shown.
     *
     * @return array
     */
    public function calculate(): array;
}
