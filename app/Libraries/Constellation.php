<?php

namespace App\Libraries;

use App\Libraries\Constellation\Star;

/**
 * A constellation is a group of stars finalized and ready for representation depending on the
 * numerical tool.
 */
class Constellation
{
    public readonly string $name;
    public readonly array $stars;

    public function __construct(string $name, array $stars)
    {
        $this->name = $name;
        $this->stars = $stars;
    }
}
