<?php

namespace App\Libraries;

use App\Libraries\Constellation\Star;
use App\Libraries\Constellation\AcceptableConstellationKind;

/**
 * A constellation is a group of stars finalized and ready for representation depending on the
 * numerical tool.
 */
class Constellation
{
    public readonly string $name;
    public readonly AcceptableConstellationKind $kind;
    public readonly array $stars;

    public function __construct(string $name, AcceptableConstellationKind $kind, array $stars)
    {
        $this->name = $name;
        $this->kind = $kind;
        $this->stars = $stars;
    }

    public function toArray(): array {
        return [
            "name" => $this->name,
            "kind" => $this->kind->value,
            "stars" => array_map(function ($star) { return $star->toArray(); }, $this->stars)
        ];
    }
}
