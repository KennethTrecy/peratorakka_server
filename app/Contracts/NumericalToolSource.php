<?php

namespace App\Contracts;

use App\Entities\FlowCalculation;
use App\Entities\SummaryCalculation;
use App\Libraries\Context;
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
     * Identifies the name of the source tool.
     *
     * @return string
     */
    public static function sourceType(): string;

    /**
     * Converts an array into an instance.
     *
     * @param array $array
     * @return Self|null
     */
    public static function parseConfiguration(Context $context, array $array): ?Self;

    /**
     * Returns the data points to be shown.
     *
     * @return array
     */
    public function calculate(): array;
}
