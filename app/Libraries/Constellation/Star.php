<?php

namespace App\Libraries\Constellation;

/**
 * A star is an individual data point. It contains a display value and a numerical value.
 */
class Star
{
    public readonly string $display_value;
    public readonly float $numerical_value;

    public function __construct(string $display_value, float $numerical_value)
    {
        $this->display_value = $display_value;
        $this->numerical_value = $numerical_value;
    }
}
