<?php

namespace App\Libraries\Constellation;

use Brick\Math\BigRational;

/**
 * A star is an individual data point. It contains a display value and a numerical value.
 */
class Star
{
    public readonly string $display_value;
    public readonly BigRational $numerical_value;

    public function __construct(string $display_value, BigRational $numerical_value)
    {
        $this->display_value = $display_value;
        $this->numerical_value = $numerical_value;
    }
}
